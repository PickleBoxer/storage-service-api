<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->created_at,

            // Only include file URLs when completed
            'file_url' => $this->when($this->isCompleted(), $this->file_url),
            'thumbnail_url' => $this->when($this->isCompleted(), $this->thumbnail_url),
            'file_path' => $this->when($this->isCompleted(), $this->file_path),
            'thumbnail_path' => $this->when($this->isCompleted(), $this->thumbnail_path),

            // Only include error when failed
            'error' => $this->when($this->isFailed(), $this->processing_error),
        ];
    }
}
