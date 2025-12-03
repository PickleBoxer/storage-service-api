<?php

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\User;

test('media factory creates completed media by default', function () {
    $media = Media::factory()->create();

    expect($media->status)->toBe(MediaStatus::Completed)
        ->and($media->thumbnail_path)->not->toBeNull()
        ->and($media->processing_error)->toBeNull();
});

test('media factory creates image type media', function () {
    $media = Media::factory()->create();

    expect($media->type)->toBeInstanceOf(MediaType::class)
        ->and($media->type)->toBeIn([MediaType::Image, MediaType::Video]);
});

test('media factory creates media with user', function () {
    $media = Media::factory()->create();

    expect($media->user)->toBeInstanceOf(User::class)
        ->and($media->user_id)->toBeInt();
});

test('media factory creates media with title and description', function () {
    $media = Media::factory()->create();

    expect($media->title)->toBeString()->not->toBeEmpty()
        ->and($media->description)->toBeString()->not->toBeEmpty();
});

test('media factory creates media with file paths', function () {
    $media = Media::factory()->create();

    expect($media->file_path)->toBeString()->not->toBeEmpty()
        ->and($media->thumbnail_path)->toBeString()->not->toBeEmpty();
});

test('media factory pending state creates media without thumbnail', function () {
    $media = Media::factory()->pending()->create();

    expect($media->status)->toBe(MediaStatus::Pending)
        ->and($media->thumbnail_path)->toBeNull()
        ->and($media->processing_error)->toBeNull();
});

test('media factory failed state creates media with error', function () {
    $media = Media::factory()->failed()->create();

    expect($media->status)->toBe(MediaStatus::Failed)
        ->and($media->thumbnail_path)->toBeNull()
        ->and($media->processing_error)->toBe('Test error message');
});

test('media factory can create multiple media records', function () {
    $mediaCollection = Media::factory()->count(5)->create();

    expect($mediaCollection)->toHaveCount(5);
});

test('media factory can create media for specific user', function () {
    $user = User::factory()->create();

    $media = Media::factory()->create(['user_id' => $user->id]);

    expect($media->user_id)->toBe($user->id)
        ->and($media->user->id)->toBe($user->id);
});

test('media factory file path matches type', function () {
    $media = Media::factory()->create();

    if ($media->type === MediaType::Image) {
        expect($media->file_path)->toContain('media/images');
    } else {
        expect($media->file_path)->toContain('media/videos');
    }
});

test('media factory thumbnail path matches type', function () {
    $media = Media::factory()->create();

    if ($media->type === MediaType::Image) {
        expect($media->thumbnail_path)->toContain('thumbnails/images');
    } else {
        expect($media->thumbnail_path)->toContain('thumbnails/videos');
    }
});

test('media factory can override default attributes', function () {
    $media = Media::factory()->create([
        'title' => 'Custom Title',
        'description' => 'Custom Description',
    ]);

    expect($media->title)->toBe('Custom Title')
        ->and($media->description)->toBe('Custom Description');
});

test('media factory can create pending media and then update to completed', function () {
    $media = Media::factory()->pending()->create();

    expect($media->status)->toBe(MediaStatus::Pending);

    $media->update([
        'status' => MediaStatus::Completed,
        'thumbnail_path' => 'thumbnails/test_thumb.jpg',
    ]);

    expect($media->fresh()->status)->toBe(MediaStatus::Completed)
        ->and($media->fresh()->thumbnail_path)->toBe('thumbnails/test_thumb.jpg');
});

test('media factory can create failed media with custom error', function () {
    $media = Media::factory()->failed()->create([
        'processing_error' => 'Custom error message',
    ]);

    expect($media->status)->toBe(MediaStatus::Failed)
        ->and($media->processing_error)->toBe('Custom error message');
});
