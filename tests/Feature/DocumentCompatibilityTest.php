<?php

namespace Tests\Feature;

use App\Models\Activity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use MongoDB\BSON\ObjectId;
use Tests\MongoTestCase;

class DocumentCompatibilityTest extends MongoTestCase
{
    public function test_embedded_mongoose_ids_are_strings_in_json_and_remain_objectids_in_storage(): void
    {
        $organization = $this->organization();
        $mediaId = new ObjectId('507f1f77bcf86cd799439010');
        $project = $this->project($organization, ['media' => [['_id' => $mediaId, 'url' => 'https://images.example.test/photo.jpg', 'type' => 'image']]]);

        $this->getJson('/api/v1/project/'.$project->getKey())
            ->assertOk()
            ->assertJsonPath('data.media.0._id', '507f1f77bcf86cd799439010');

        $raw = DB::connection()->getMongoDB()->projects->findOne(['_id' => new ObjectId((string) $project->getKey())]);
        $this->assertInstanceOf(ObjectId::class, $raw->media[0]->_id);
    }

    public function test_new_project_media_receives_a_compatible_embedded_id(): void
    {
        $organization = $this->organization();
        config(['services.cloudinary.cloud' => 'test', 'services.cloudinary.key' => 'key', 'services.cloudinary.secret' => 'secret']);
        Http::fake(['https://api.cloudinary.com/v1_1/test/auto/upload' => Http::response(['secure_url' => 'https://images.example.test/photo.jpg', 'resource_type' => 'image'])]);

        $response = $this->post('/api/v1/project/create', [
            'description' => 'Project with media',
            'target_amount' => 10000,
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            'media' => [UploadedFile::fake()->image('media.jpg')],
        ], $this->token($organization))->assertOk();

        $this->assertIsString($response->json('data.media.0._id'));
        $this->assertSame(24, strlen($response->json('data.media.0._id')));
        Http::assertSentCount(2);
    }

    public function test_partial_activity_update_cannot_reverse_the_existing_date_range(): void
    {
        $organization = $this->organization();
        $activity = Activity::create(['organization' => $organization->getKey(), 'name' => 'Meeting', 'start' => '2026-10-01', 'end' => '2026-10-02']);

        $this->putJson('/api/v1/activity/'.$activity->getKey(), ['end' => '2026-09-30'], $this->token($organization))
            ->assertBadRequest()->assertJsonPath('message', 'The end field must be a date after or equal to start.');

        $this->assertSame('2026-10-02', $activity->fresh()->end);
        $this->putJson('/api/v1/activity/'.$activity->getKey(), ['end' => '2026-10-03'], $this->token($organization))->assertOk();
        $this->assertSame('2026-10-03', $activity->fresh()->end);
    }
}
