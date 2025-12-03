<?php

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

test('media can be created with required fields', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test Image',
        'description' => 'Test description',
        'file_path' => 'media/images/test.jpg',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->id)->toBeInt()
        ->and($media->user_id)->toBe($user->id)
        ->and($media->type)->toBe(MediaType::Image)
        ->and($media->title)->toBe('Test Image')
        ->and($media->description)->toBe('Test description')
        ->and($media->file_path)->toBe('media/images/test.jpg')
        ->and($media->status)->toBe(MediaStatus::Pending);

    assertDatabaseHas('media', [
        'id' => $media->id,
        'user_id' => $user->id,
        'type' => 'image',
        'status' => 'pending',
    ]);
});

test('media type is cast to MediaType enum', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Video,
        'title' => 'Test Video',
        'file_path' => 'media/videos/test.mp4',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->type)->toBeInstanceOf(MediaType::class)
        ->and($media->type)->toBe(MediaType::Video)
        ->and($media->type->value)->toBe('video');
});

test('media status is cast to MediaStatus enum', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Completed,
    ]);

    expect($media->status)->toBeInstanceOf(MediaStatus::class)
        ->and($media->status)->toBe(MediaStatus::Completed)
        ->and($media->status->value)->toBe('completed');
});

test('isPending returns true when status is pending', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->isPending())->toBeTrue()
        ->and($media->isProcessing())->toBeFalse()
        ->and($media->isCompleted())->toBeFalse()
        ->and($media->isFailed())->toBeFalse();
});

test('isProcessing returns true when status is processing', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Processing,
    ]);

    expect($media->isProcessing())->toBeTrue()
        ->and($media->isPending())->toBeFalse()
        ->and($media->isCompleted())->toBeFalse()
        ->and($media->isFailed())->toBeFalse();
});

test('isCompleted returns true when status is completed', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Completed,
        'thumbnail_path' => 'thumbnails/test_thumb.jpg',
    ]);

    expect($media->isCompleted())->toBeTrue()
        ->and($media->isPending())->toBeFalse()
        ->and($media->isProcessing())->toBeFalse()
        ->and($media->isFailed())->toBeFalse();
});

test('isFailed returns true when status is failed', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Failed,
        'processing_error' => 'Test error',
    ]);

    expect($media->isFailed())->toBeTrue()
        ->and($media->isPending())->toBeFalse()
        ->and($media->isProcessing())->toBeFalse()
        ->and($media->isCompleted())->toBeFalse();
});

test('file_url accessor returns correct url when file_path exists', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'media/images/1/test.jpg',
        'status' => MediaStatus::Completed,
    ]);

    expect($media->file_url)->toContain('storage/media/images/1/test.jpg');
});

test('file_url accessor returns null when file_path is empty', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => '',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->file_url)->toBeNull();
});

test('thumbnail_url accessor returns correct url when thumbnail_path exists', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'media/images/test.jpg',
        'thumbnail_path' => 'thumbnails/images/1/test_thumb.jpg',
        'status' => MediaStatus::Completed,
    ]);

    expect($media->thumbnail_url)->toContain('storage/thumbnails/images/1/test_thumb.jpg');
});

test('thumbnail_url accessor returns null when thumbnail_path is null', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->thumbnail_url)->toBeNull();
});

test('media belongs to user', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->user)->toBeInstanceOf(User::class)
        ->and($media->user->id)->toBe($user->id);
});

test('description can be nullable', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->description)->toBeNull();
});

test('thumbnail_path can be nullable', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->thumbnail_path)->toBeNull();
});

test('processing_error can be nullable', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->processing_error)->toBeNull();
});

test('media has timestamps', function () {
    $user = User::factory()->create();

    $media = Media::create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'title' => 'Test',
        'file_path' => 'test.jpg',
        'status' => MediaStatus::Pending,
    ]);

    expect($media->created_at)->toBeInstanceOf(DateTime::class)
        ->and($media->updated_at)->toBeInstanceOf(DateTime::class);
});
