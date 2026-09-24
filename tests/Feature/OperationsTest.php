<?php

namespace Tests\Feature;

use App\Models\Outbox;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use MongoDB\BSON\ObjectId;
use Tests\MongoTestCase;

class OperationsTest extends MongoTestCase
{
    public function test_outbox_delivery_is_leased_and_deduplicated(): void
    {
        app(NotificationService::class)->enqueue('person@example.test', 'Test message', 'Body', 'https://example.test/activate', 'unique-event');
        app(NotificationService::class)->enqueue('person@example.test', 'Test message', 'Body', 'https://example.test/activate', 'unique-event');
        $this->assertSame(1, Outbox::count());
        $this->artisan('fundraiser:outbox')->assertSuccessful();
        $this->assertSame('sent', Outbox::first()->status);
        $this->assertNull(Outbox::first()->encrypted_payload);
        $this->artisan('fundraiser:outbox')->expectsOutput('Processed 0 outbox messages.')->assertSuccessful();
    }

    public function test_failed_mail_retries_without_losing_the_job(): void
    {
        app(NotificationService::class)->enqueue('person@example.test', 'Test message', 'Body');
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP down'));
        $this->artisan('fundraiser:outbox')->assertSuccessful();
        $o = Outbox::first();
        $this->assertSame('pending', $o->status);
        $this->assertSame(1, $o->attempts);
        $this->assertNotNull($o->encrypted_payload);
    }

    public function test_indexes_preflight_reconciliation_and_mongodb_cache(): void
    {
        $o = $this->organization();
        $p = $this->project($o);
        $t = $this->transaction($o, $p);
        app(PaymentService::class)->settle(['id' => 123, 'tx_ref' => $t->tx_ref, 'amount' => 1000, 'currency' => 'NGN', 'status' => 'successful']);
        $this->artisan('fundraiser:preflight')->assertSuccessful();
        $this->artisan('fundraiser:indexes', ['--apply' => true])->assertSuccessful();
        $this->artisan('fundraiser:reconcile')->assertSuccessful();
        Cache::store('mongodb')->put('test', 'value', 60);
        $this->assertSame('value', Cache::store('mongodb')->get('test'));
        $lock = Cache::store('mongodb')->lock('test-lock', 10);
        $this->assertTrue($lock->get());
        $this->assertFalse(Cache::store('mongodb')->lock('test-lock', 10)->get());
        $lock->release();
        $this->wallet($o)->update(['balance' => 999]);
        $this->artisan('fundraiser:reconcile')->assertFailed();
    }

    public function test_hmac_webhook_and_provider_reference_and_currency_checks(): void
    {
        $o = $this->organization();
        $p = $this->project($o);
        $t = $this->transaction($o, $p);
        config(['services.flutterwave.signature_mode' => 'hmac', 'services.flutterwave.secret' => 'secret', 'services.flutterwave.hash' => 'hash']);
        $body = json_encode(['data' => ['id' => 123, 'tx_ref' => $t->tx_ref]]);
        $sig = base64_encode(hash_hmac('sha256', $body, 'hash', true));
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['id' => 123, 'tx_ref' => $t->tx_ref, 'amount' => 1000, 'currency' => 'USD', 'status' => 'successful']])]);
        $this->call('POST', '/api/v1/donation/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_FLUTTERWAVE_SIGNATURE' => $sig], $body)->assertStatus(409);
        $this->assertSame(0.0, $this->wallet($o)->balance);
    }

    public function test_bson_audit_detects_string_references_and_sub_kobo_values(): void
    {
        $o = $this->organization();
        $p = $this->project($o);
        $this->artisan('fundraiser:audit-data')->assertSuccessful();
        DB::connection()->getMongoDB()->projects->updateOne(['_id' => new ObjectId((string) $p->getKey())], ['$set' => ['organization' => 'not-an-object-id', 'target_amount' => 1.001]]);
        $this->artisan('fundraiser:audit-data')->assertFailed();
    }
}
