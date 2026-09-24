<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Operation\FindOneAndUpdate;

class ProcessOutbox extends Command
{
    protected $signature = 'fundraiser:outbox {--limit=100}';

    protected $description = 'Deliver pending MongoDB outbox emails with atomic leases and bounded retries';

    public function handle(): int
    {
        $collection = DB::connection('mongodb')->getMongoDB()->selectCollection('outbox');
        $count = 0;
        $collection->updateMany(['status' => 'processing', 'attempts' => ['$gte' => 10], 'lockedAt' => ['$lt' => new UTCDateTime(now()->subMinutes(5))]], ['$set' => ['status' => 'failed'], '$unset' => ['lease' => '']]);
        while ($count < max(1, min(1000, (int) $this->option('limit')))) {
            $now = new UTCDateTime;
            $lease = (string) Str::uuid();
            $job = $collection->findOneAndUpdate(['attempts' => ['$lt' => 10], '$or' => [['status' => 'pending', 'availableAt' => ['$lte' => $now]], ['status' => 'processing', 'lockedAt' => ['$lt' => new UTCDateTime(now()->subMinutes(5))]]]], ['$set' => ['status' => 'processing', 'lockedAt' => $now, 'lease' => $lease], '$inc' => ['attempts' => 1]], ['sort' => ['availableAt' => 1], 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]);
            if (! $job) {
                break;
            }
            $filter = ['_id' => $job->_id, 'lease' => $lease];
            try {
                $payload = json_decode(Crypt::decryptString($job->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
                Mail::send('mail.notification', ['subject' => $job->subject, 'messageText' => $payload['message'], 'url' => $payload['url']], function ($message) use ($job) {
                    $message->to($job->recipient)->subject($job->subject);
                    $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', hash('sha256', $job->event_key).'@nabaku.local');
                });
                $collection->updateOne($filter, ['$set' => ['status' => 'sent', 'sentAt' => new UTCDateTime], '$unset' => ['encrypted_payload' => '', 'lease' => '']]);
            } catch (\Throwable $e) {
                $collection->updateOne($filter, ['$set' => ['status' => $job->attempts >= 10 ? 'failed' : 'pending', 'availableAt' => new UTCDateTime(now()->addSeconds(min(3600, 30 * (2 ** $job->attempts)))), 'error_class' => $e::class], '$unset' => ['lease' => '']]);
                Log::warning('Outbox delivery failed', ['outbox_id' => (string) $job->_id, 'exception_class' => $e::class]);
            }
            $count++;
        }
        $this->info("Processed $count outbox messages.");

        return self::SUCCESS;
    }
}
