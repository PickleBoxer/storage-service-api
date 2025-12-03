<?php

namespace Database\Factories;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([MediaType::Image, MediaType::Video]);

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'file_path' => 'media/' . $type->value . 's/test_file.jpg',
            'thumbnail_path' => 'thumbnails/' . $type->value . 's/test_thumb.jpg',
            'status' => MediaStatus::Completed,
            'processing_error' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MediaStatus::Pending,
            'thumbnail_path' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MediaStatus::Failed,
            'processing_error' => 'Test error message',
            'thumbnail_path' => null,
        ]);
    }
}
