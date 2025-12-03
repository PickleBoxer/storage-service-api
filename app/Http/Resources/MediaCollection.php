<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MediaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($media) {
                return [
                    'id' => $media->id,
                    'type' => $media->type,
                    'title' => $media->title,
                    'status' => $media->status,
                    'thumbnail_url' => $media->thumbnail_url,
                    'created_at' => $media->created_at,
                ];
            }),
        ];
    }
}
