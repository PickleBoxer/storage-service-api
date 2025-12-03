<?php

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

test('user can list their own media', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    // Create media for authenticated user
    Media::factory()->count(3)->create(['user_id' => $user->id]);

    // Create media for other user (should not appear)
    Media::factory()->count(2)->create(['user_id' => $otherUser->id]);

    $response = actingAs($user)->getJson('/api/media');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'type', 'title', 'status', 'thumbnail_url', 'created_at'],
            ],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('unauthenticated user cannot list media', function () {
    $response = getJson('/api/media');

    $response->assertStatus(401);
});

test('media list returns latest first', function () {
    $user = User::factory()->create();

    $oldMedia = Media::factory()->create([
        'user_id' => $user->id,
        'title' => 'Old Media',
        'created_at' => now()->subDays(2),
    ]);

    $newMedia = Media::factory()->create([
        'user_id' => $user->id,
        'title' => 'New Media',
        'created_at' => now(),
    ]);

    $response = actingAs($user)->getJson('/api/media');

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data[0]['title'])->toBe('New Media')
        ->and($data[1]['title'])->toBe('Old Media');
});

test('media list includes thumbnail url', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'status' => MediaStatus::Completed,
        'thumbnail_path' => 'thumbnails/images/1/test_thumb.jpg',
    ]);

    $response = actingAs($user)->getJson('/api/media');

    $response->assertStatus(200);

    expect($response->json('data.0.thumbnail_url'))->toContain('storage/thumbnails');
});

test('media list returns empty array when user has no media', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->getJson('/api/media');

    $response->assertStatus(200);

    expect($response->json('data'))->toBe([]);
});

test('media list includes all statuses', function () {
    $user = User::factory()->create();

    Media::factory()->create(['user_id' => $user->id, 'status' => MediaStatus::Pending]);
    Media::factory()->create(['user_id' => $user->id, 'status' => MediaStatus::Processing]);
    Media::factory()->create(['user_id' => $user->id, 'status' => MediaStatus::Completed]);
    Media::factory()->failed()->create(['user_id' => $user->id]);

    $response = actingAs($user)->getJson('/api/media');

    $response->assertStatus(200);

    expect($response->json('data'))->toHaveCount(4);
});

test('media list shows correct data structure', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Media',
        'type' => 'image',
        'status' => MediaStatus::Completed,
    ]);

    $response = actingAs($user)->getJson('/api/media');

    $response->assertStatus(200);

    $item = $response->json('data.0');
    expect($item['id'])->toBe($media->id)
        ->and($item['type'])->toBe('image')
        ->and($item['title'])->toBe('Test Media')
        ->and($item['status'])->toBe('completed')
        ->and($item)->toHaveKey('thumbnail_url')
        ->and($item)->toHaveKey('created_at');
});
