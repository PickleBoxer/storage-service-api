<?php

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake();
});

test('authenticated user can upload image file', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $response = actingAs($user)->post('/api/media', [
        'file' => $file,
        'title' => 'Test Image',
        'description' => 'Test description',
    ]);

    $response->assertStatus(202)
        ->assertJson([
            'message' => 'Media uploaded successfully. Processing in background.',
        ])
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'type', 'title', 'status', 'created_at'],
        ]);

    expect($response->json('data.type'))->toBe('image')
        ->and($response->json('data.title'))->toBe('Test Image')
        ->and($response->json('data.status'))->toBe('pending');

    assertDatabaseHas('media', [
        'user_id' => $user->id,
        'type' => 'image',
        'title' => 'Test Image',
        'description' => 'Test description',
        'status' => 'pending',
    ]);

    Storage::disk('public')->assertExists(Media::latest()->first()->file_path);
});

test('authenticated user can upload video file', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.mp4', 1024, 'video/mp4');

    $response = actingAs($user)->post('/api/media', [
        'file' => $file,
        'title' => 'Test Video',
    ]);

    $response->assertStatus(202);

    expect($response->json('data.type'))->toBe('video');

    assertDatabaseHas('media', [
        'user_id' => $user->id,
        'type' => 'video',
        'title' => 'Test Video',
    ]);
});

test('uploaded file is stored in correct directory', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.png');

    actingAs($user)->post('/api/media', [
        'file' => $file,
        'title' => 'Test',
    ]);

    $media = Media::latest()->first();

    expect($media->file_path)->toContain("media/images/{$user->id}/");
    Storage::disk('public')->assertExists($media->file_path);
});

test('file upload requires authentication', function (): void {
    $file = UploadedFile::fake()->image('test.jpg');

    $response = postJson('/api/media', [
        'file' => $file,
        'title' => 'Test',
    ]);

    $response->assertStatus(401);
});

test('file upload requires file', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->postJson('/api/media', [
        'title' => 'Test',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file'])
        ->assertJson([
            'message' => 'Please upload a file.',
        ]);
});

test('file upload requires title', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $response = actingAs($user)->postJson('/api/media', [
        'file' => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title'])
        ->assertJson([
            'message' => 'Title is required.',
        ]);
});

test('title must not exceed 255 characters', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $response = actingAs($user)->postJson('/api/media', [
        'file' => $file,
        'title' => str_repeat('a', 256),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

test('description is optional', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $response = actingAs($user)->post('/api/media', [
        'file' => $file,
        'title' => 'Test',
    ]);

    $response->assertStatus(202);

    $media = Media::latest()->first();
    expect($media->description)->toBeNull();
});

test('description must not exceed 1000 characters', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $response = actingAs($user)->postJson('/api/media', [
        'file' => $file,
        'title' => 'Test',
        'description' => str_repeat('a', 1001),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

test('file must be valid image or video type', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = actingAs($user)->postJson('/api/media', [
        'file' => $file,
        'title' => 'Test',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file'])
        ->assertJson([
            'message' => 'File must be an image (jpg, png, gif, webp) or video (mp4, mov, avi).',
        ]);
});

test('file size must not exceed 100MB', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('large.jpg', 102401, 'image/jpeg'); // 100MB + 1KB

    $response = actingAs($user)->postJson('/api/media', [
        'file' => $file,
        'title' => 'Test',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('media is created with pending status', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    actingAs($user)->post('/api/media', [
        'file' => $file,
        'title' => 'Test',
    ]);

    $media = Media::latest()->first();

    expect($media->status)->toBe(MediaStatus::Pending)
        ->and($media->isPending())->toBeTrue()
        ->and($media->thumbnail_path)->toBeNull();
});

test('media belongs to authenticated user', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    actingAs($user)->post('/api/media', [
        'file' => $file,
        'title' => 'Test',
    ]);

    $media = Media::latest()->first();

    expect($media->user_id)->toBe($user->id)
        ->and($media->user->id)->toBe($user->id);
});

test('filename is unique with timestamp and random string', function (): void {
    $user = User::factory()->create();
    $file1 = UploadedFile::fake()->image('test.jpg');
    $file2 = UploadedFile::fake()->image('test.jpg');

    actingAs($user)->post('/api/media', [
        'file' => $file1,
        'title' => 'Test 1',
    ]);

    actingAs($user)->post('/api/media', [
        'file' => $file2,
        'title' => 'Test 2',
    ]);

    $media1 = Media::find(1);
    $media2 = Media::find(2);

    expect($media1->file_path)->not->toBe($media2->file_path);
});

test('image type is detected from mime type', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.png');

    actingAs($user)->post('/api/media', [
        'file' => $file,
        'title' => 'Test',
    ]);

    $media = Media::latest()->first();

    expect($media->type)->toBe(MediaType::Image);
});

test('video type is detected from mime type', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.mp4', 1024, 'video/mp4');

    actingAs($user)->post('/api/media', [
        'file' => $file,
        'title' => 'Test',
    ]);

    $media = Media::latest()->first();

    expect($media->type)->toBe(MediaType::Video);
});

test('multiple files can be uploaded by same user', function (): void {
    $user = User::factory()->create();

    for ($i = 1; $i <= 3; $i++) {
        $file = UploadedFile::fake()->image("test{$i}.jpg");

        actingAs($user)->post('/api/media', [
            'file' => $file,
            'title' => "Test {$i}",
        ]);
    }

    expect($user->media()->count())->toBe(3);
});
