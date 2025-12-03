<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property MediaType $type
 * @property string $title
 * @property string|null $description
 * @property string $file_path
 * @property string|null $thumbnail_path
 * @property MediaStatus $status
 * @property string|null $processing_error
 */
class Media extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'file_path',
        'thumbnail_path',
        'status',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'status' => MediaStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === MediaStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === MediaStatus::Processing;
    }

    public function isCompleted(): bool
    {
        return $this->status === MediaStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === MediaStatus::Failed;
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path
            ? asset('storage/'.$this->file_path)
            : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path
            ? asset('storage/'.$this->thumbnail_path)
            : null;
    }
}
