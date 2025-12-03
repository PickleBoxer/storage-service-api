<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Media;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProcessMediaJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 120;

    public $backoff = [10, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Media $media
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Update status to processing
            $this->media->update(['status' => MediaStatus::Processing]);

            // Get full file path
            $filePath = Storage::disk('public')->path($this->media->file_path);

            // Generate thumbnail based on type
            if ($this->media->type === MediaType::Image) {
                $thumbnailPath = $this->processImage($filePath);
            } else {
                $thumbnailPath = $this->processVideo($filePath);
            }

            // Update media record with thumbnail path
            $this->media->update([
                'thumbnail_path' => $thumbnailPath,
                'status' => MediaStatus::Completed,
            ]);

        } catch (\Exception $e) {
            // Mark as failed with error message
            $this->media->update([
                'status' => MediaStatus::Failed,
                'processing_error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }

    protected function processImage(string $filePath): string
    {
        // Load image
        $image = Image::read($filePath);

        // Resize to fit within 200x200 (maintain aspect ratio)
        $image->scale(width: 200, height: 200);

        // Generate thumbnail filename
        $thumbnailFilename = $this->getThumbnailFilename();
        $thumbnailPath = "thumbnails/images/{$this->media->user_id}/{$thumbnailFilename}";

        // Ensure directory exists
        Storage::disk('public')->makeDirectory("thumbnails/images/{$this->media->user_id}");

        // Save thumbnail
        $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);
        $image->save($fullThumbnailPath, quality: 85);

        return $thumbnailPath;
    }

    protected function processVideo(string $filePath): string
    {
        // Initialize FFmpeg
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => 'ffmpeg',
            'ffprobe.binaries' => 'ffprobe',
        ]);

        // Open video
        $video = $ffmpeg->open($filePath);

        // Extract frame at 1 second
        // @phpstan-ignore-next-line
        $frame = $video->frame(TimeCode::fromSeconds(1));

        // Generate thumbnail filename
        $thumbnailFilename = $this->getThumbnailFilename();
        $thumbnailPath = "thumbnails/videos/{$this->media->user_id}/{$thumbnailFilename}";

        // Ensure directory exists
        Storage::disk('public')->makeDirectory("thumbnails/videos/{$this->media->user_id}");

        // Save frame as JPEG
        $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);
        $frame->save($fullThumbnailPath);

        // Resize with Intervention Image
        $image = Image::read($fullThumbnailPath);
        $image->scale(width: 200, height: 200);
        $image->save($fullThumbnailPath, quality: 85);

        return $thumbnailPath;
    }

    protected function getThumbnailFilename(): string
    {
        $pathInfo = pathinfo($this->media->file_path);

        return $pathInfo['filename'].'_thumb.jpg';
    }

    public function failed(\Throwable $exception): void
    {
        // Called when job fails after all retries
        $this->media->update([
            'status' => MediaStatus::Failed,
            'processing_error' => $exception->getMessage(),
        ]);
    }
}
