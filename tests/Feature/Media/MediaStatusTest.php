<?php

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

test('user can view status of their own media', function (): void {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'status' => MediaStatus::Pending,
    ]);

    $response = actingAs($user)->getJson("/api/media/{$media->id}/status");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'type', 'title', 'description', 'status', 'created_at'],
        ]);

    expect($response->json('data.id'))->toBe($media->id)
        ->and($response->json('data.status'))->toBe('pending');
});

test('user cannot view status of another users media', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $owner->id]);

    $response = actingAs($otherUser)->getJson("/api/media/{$media->id}/status");

    $response->assertStatus(403);
});

test('unauthenticated user cannot view media status', function (): void {
    $media = Media::factory()->create();

    $response = getJson("/api/media/{$media->id}/status");

    $response->assertStatus(401);
});

test('status endpoint returns file urls when media is completed', function (): void {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'status' => MediaStatus::Completed,
        'file_path' => 'media/images/1/test.jpg',
        'thumbnail_path' => 'thumbnails/images/1/test_thumb.jpg',
    ]);

    $response = actingAs($user)->getJson("/api/media/{$media->id}/status");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'title',
                'description',
                'status',
                'created_at',
                'file_url',
                'thumbnail_url',
                'file_path',
                'thumbnail_path',
            ],
        ]);

    expect($response->json('data.status'))->toBe('completed')
        ->and($response->json('data.file_url'))->toContain('storage/media/images')
        ->and($response->json('data.thumbnail_url'))->toContain('storage/thumbnails/images');
});

test('status endpoint does not return file urls when media is pending', function (): void {
    $user = User::factory()->create();
    $media = Media::factory()->pending()->create(['user_id' => $user->id]);

    $response = actingAs($user)->getJson("/api/media/{$media->id}/status");

    $response->assertStatus(200)
        ->assertJsonMissing(['file_url', 'thumbnail_url']);

    expect($response->json('data.status'))->toBe('pending');
});

test('status endpoint returns error message when media processing failed', function (): void {
    $user = User::factory()->create();
    $media = Media::factory()->failed()->create([
        'user_id' => $user->id,
        'processing_error' => 'File not found',
    ]);

    $response = actingAs($user)->getJson("/api/media/{$media->id}/status");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'status', 'error'],
        ]);

    expect($response->json('data.status'))->toBe('failed')
        ->and($response->json('data.error'))->toBe('File not found');
});

test('status endpoint returns 404 for non-existent media', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->getJson('/api/media/99999/status');

    $response->assertStatus(404);
});

test('status endpoint includes all media details', function (): void {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Video,
        'title' => 'Test Video',
        'description' => 'Test description',
        'status' => MediaStatus::Processing,
    ]);

    $response = actingAs($user)->getJson("/api/media/{$media->id}/status");

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data['type'])->toBe('video')
        ->and($data['title'])->toBe('Test Video')
        ->and($data['description'])->toBe('Test description')
        ->and($data['status'])->toBe('processing');
});
