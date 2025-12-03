<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Resources\MediaCollection;
use App\Http\Resources\MediaResource;
use App\Jobs\ProcessMediaJob;
use App\Models\Media;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MediaController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Media::class);

        $media = auth()->user()->media()->latest()->get();

        return new MediaCollection($media);
    }

    public function store(StoreMediaRequest $request)
    {
        $this->authorize('create', Media::class);

        $file = $request->file('file');
        $user = $request->user();

        // Determine media type
        $mimeType = $file->getMimeType();
        $type = str_starts_with((string) $mimeType, 'image/') ? MediaType::Image : MediaType::Video;

        // Generate unique filename
        $timestamp = now()->timestamp;
        $randomString = bin2hex(random_bytes(8));
        $extension = $file->getClientOriginalExtension();
        $filename = "{$timestamp}_{$randomString}.{$extension}";

        // Store file in user-specific directory
        $directory = "media/{$type->value}s/{$user->id}";
        $path = $file->storeAs($directory, $filename, 'public');

        // Create media record
        $media = Media::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'file_path' => $path,
            'status' => MediaStatus::Pending,
        ]);

        // Dispatch background job for thumbnail generation
        ProcessMediaJob::dispatch($media);

        // Return 202 Accepted with media details
        return response()->json([
            'message' => 'Media uploaded successfully. Processing in background.',
            'data' => [
                'id' => $media->id,
                'type' => $media->type,
                'title' => $media->title,
                'status' => $media->status,
                'created_at' => $media->created_at,
            ],
        ], 202);
    }

    public function status(Media $media)
    {
        // Authorization check using policy
        $this->authorize('view', $media);

        return new MediaResource($media);
    }
}
