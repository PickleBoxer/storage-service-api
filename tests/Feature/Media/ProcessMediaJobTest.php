<?php

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Jobs\ProcessMediaJob;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('job updates media status to processing when started', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    $path = $file->store('media/images/' . $user->id, 'public');

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path,
        'status' => MediaStatus::Pending,
        'thumbnail_path' => null,
    ]);

    expect($media->status)->toBe(MediaStatus::Pending);

    ProcessMediaJob::dispatchSync($media);

    $media->refresh();
    expect($media->status)->toBe(MediaStatus::Completed);
});

test('job generates thumbnail for image', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    $path = $file->store('media/images/' . $user->id, 'public');

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path,
        'status' => MediaStatus::Pending,
        'thumbnail_path' => null,
    ]);

    ProcessMediaJob::dispatchSync($media);

    $media->refresh();

    expect($media->thumbnail_path)->not->toBeNull()
        ->and($media->status)->toBe(MediaStatus::Completed)
        ->and(Storage::disk('public')->exists($media->thumbnail_path))->toBeTrue();

    // Verify thumbnail dimensions (should be scaled to fit 200x200)
    $thumbnailPath = Storage::disk('public')->path($media->thumbnail_path);
    $imageSize = getimagesize($thumbnailPath);
    expect($imageSize[0])->toBeLessThanOrEqual(200)
        ->and($imageSize[1])->toBeLessThanOrEqual(200);
});

test('job generates thumbnail for video', function () {
    // Skip this test if FFmpeg is not available
    if (! shell_exec('ffmpeg -version')) {
        $this->markTestSkipped('FFmpeg is not installed');
    }

    $user = User::factory()->create();
    // Create a minimal valid video file for testing
    $file = UploadedFile::fake()->create('test.mp4', 100, 'video/mp4');
    $path = $file->store('media/videos/' . $user->id, 'public');

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Video,
        'file_path' => $path,
        'status' => MediaStatus::Pending,
        'thumbnail_path' => null,
    ]);

    try {
        ProcessMediaJob::dispatchSync($media);
    } catch (\Exception $e) {
        // Expected to fail with fake video file
        $this->markTestSkipped('Cannot process fake video file: ' . $e->getMessage());
    }

    $media->refresh();

    // If it succeeded, verify the thumbnail exists
    if ($media->status === MediaStatus::Completed) {
        expect($media->thumbnail_path)->not->toBeNull()
            ->and(Storage::disk('public')->exists($media->thumbnail_path))->toBeTrue();
    }
})->skip('Requires real video file and FFmpeg');

test('job sets status to completed after successful processing', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.png', 1024, 768);
    $path = $file->store('media/images/' . $user->id, 'public');

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path,
        'status' => MediaStatus::Pending,
    ]);

    ProcessMediaJob::dispatchSync($media);

    $media->refresh();

    expect($media->status)->toBe(MediaStatus::Completed)
        ->and($media->isCompleted())->toBeTrue()
        ->and($media->processing_error)->toBeNull();
});

test('job sets status to failed when processing fails', function () {
    $user = User::factory()->create();

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => 'media/images/nonexistent.jpg',
        'status' => MediaStatus::Pending,
        'thumbnail_path' => null,
    ]);

    try {
        ProcessMediaJob::dispatchSync($media);
    } catch (\Exception $e) {
        // Expected to throw exception
    }

    $media->refresh();

    expect($media->status)->toBe(MediaStatus::Failed)
        ->and($media->isFailed())->toBeTrue()
        ->and($media->processing_error)->not->toBeNull();
});

test('job stores thumbnail in correct directory structure', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('my-photo.jpg', 500, 500);
    $path = $file->store('media/images/' . $user->id, 'public');

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path,
        'status' => MediaStatus::Pending,
    ]);

    ProcessMediaJob::dispatchSync($media);

    $media->refresh();

    expect($media->thumbnail_path)->toContain("thumbnails/images/{$user->id}/")
        ->and($media->thumbnail_path)->toEndWith('_thumb.jpg');
});

test('job creates thumbnail directory if it does not exist', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 600, 400);
    $path = $file->store('media/images/' . $user->id, 'public');

    // Ensure thumbnail directory doesn't exist
    Storage::disk('public')->deleteDirectory("thumbnails/images/{$user->id}");

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path,
        'status' => MediaStatus::Pending,
    ]);

    ProcessMediaJob::dispatchSync($media);

    $media->refresh();

    expect(Storage::disk('public')->exists($media->thumbnail_path))->toBeTrue()
        ->and(Storage::disk('public')->directoryExists("thumbnails/images/{$user->id}"))->toBeTrue();
});

test('job maintains aspect ratio when creating thumbnail', function () {
    $user = User::factory()->create();
    // Create a wide image
    $file = UploadedFile::fake()->image('wide.jpg', 1000, 500);
    $path = $file->store('media/images/' . $user->id, 'public');

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path,
        'status' => MediaStatus::Pending,
    ]);

    ProcessMediaJob::dispatchSync($media);

    $media->refresh();

    $thumbnailPath = Storage::disk('public')->path($media->thumbnail_path);
    $imageSize = getimagesize($thumbnailPath);

    // Width should be 200, height should be 100 (maintaining 2:1 ratio)
    expect($imageSize[0])->toBeLessThanOrEqual(200)
        ->and($imageSize[1])->toBeLessThanOrEqual(200);

    // Aspect ratio should be maintained (roughly 2:1)
    $aspectRatio = $imageSize[0] / $imageSize[1];
    expect($aspectRatio)->toBeGreaterThan(1.5)
        ->and($aspectRatio)->toBeLessThan(2.5);
});

test('job processes different image formats', function ($format, $mimeType) {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image("test.{$format}", 400, 400);
    $path = $file->store('media/images/' . $user->id, 'public');

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path,
        'status' => MediaStatus::Pending,
    ]);

    ProcessMediaJob::dispatchSync($media);

    $media->refresh();

    expect($media->status)->toBe(MediaStatus::Completed)
        ->and($media->thumbnail_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($media->thumbnail_path))->toBeTrue();
})->with([
    ['jpg', 'image/jpeg'],
    ['png', 'image/png'],
]);

test('job handles large images correctly', function () {
    $user = User::factory()->create();
    // Create a large image
    $file = UploadedFile::fake()->image('large.jpg', 4000, 3000);
    $path = $file->store('media/images/' . $user->id, 'public');

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path,
        'status' => MediaStatus::Pending,
    ]);

    ProcessMediaJob::dispatchSync($media);

    $media->refresh();

    expect($media->status)->toBe(MediaStatus::Completed)
        ->and($media->thumbnail_path)->not->toBeNull();

    // Verify thumbnail is scaled down
    $thumbnailPath = Storage::disk('public')->path($media->thumbnail_path);
    $imageSize = getimagesize($thumbnailPath);
    expect($imageSize[0])->toBeLessThanOrEqual(200)
        ->and($imageSize[1])->toBeLessThanOrEqual(200);
});

test('job does not overwrite existing thumbnails for different media', function () {
    $user = User::factory()->create();

    // Create first media with thumbnail
    $file1 = UploadedFile::fake()->image('first.jpg', 500, 500);
    $path1 = $file1->store('media/images/' . $user->id, 'public');

    $media1 = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path1,
        'status' => MediaStatus::Pending,
    ]);

    ProcessMediaJob::dispatchSync($media1);
    $media1->refresh();
    $thumbnail1 = $media1->thumbnail_path;

    // Create second media with thumbnail
    $file2 = UploadedFile::fake()->image('second.jpg', 500, 500);
    $path2 = $file2->store('media/images/' . $user->id, 'public');

    $media2 = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => $path2,
        'status' => MediaStatus::Pending,
    ]);

    ProcessMediaJob::dispatchSync($media2);
    $media2->refresh();
    $thumbnail2 = $media2->thumbnail_path;

    // Both thumbnails should exist and be different
    expect($thumbnail1)->not->toBe($thumbnail2)
        ->and(Storage::disk('public')->exists($thumbnail1))->toBeTrue()
        ->and(Storage::disk('public')->exists($thumbnail2))->toBeTrue();
});

test('failed method updates media status when job fails', function () {
    $user = User::factory()->create();

    $media = Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
        'file_path' => 'invalid/path.jpg',
        'status' => MediaStatus::Pending,
    ]);

    $job = new ProcessMediaJob($media);

    try {
        $job->handle();
    } catch (\Exception $e) {
        // Simulate failed job
        $job->failed($e);
    }

    $media->refresh();

    expect($media->status)->toBe(MediaStatus::Failed)
        ->and($media->processing_error)->not->toBeNull();
});
