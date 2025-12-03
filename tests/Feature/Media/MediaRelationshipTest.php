<?php

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\User;

test('user has many media', function (): void {
    $user = User::factory()->create();

    $media1 = Media::factory()->create(['user_id' => $user->id]);
    $media2 = Media::factory()->create(['user_id' => $user->id]);
    $media3 = Media::factory()->create(['user_id' => $user->id]);

    expect($user->media)->toHaveCount(3)
        ->and($user->media->pluck('id')->toArray())->toContain($media1->id, $media2->id, $media3->id);
});

test('user media relationship returns only users media', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Media::factory()->count(3)->create(['user_id' => $user1->id]);
    Media::factory()->count(2)->create(['user_id' => $user2->id]);

    expect($user1->media)->toHaveCount(3)
        ->and($user2->media)->toHaveCount(2);
});

test('user can access media relationship', function (): void {
    $user = User::factory()->create();

    Media::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Media',
    ]);

    $media = $user->media()->first();

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->title)->toBe('Test Media');
});

test('deleting user cascades delete to media', function (): void {
    $user = User::factory()->create();

    $media1 = Media::factory()->create(['user_id' => $user->id]);
    $media2 = Media::factory()->create(['user_id' => $user->id]);

    expect(Media::count())->toBe(2);

    $user->delete();

    expect(Media::count())->toBe(0)
        ->and(Media::find($media1->id))->toBeNull()
        ->and(Media::find($media2->id))->toBeNull();
});

test('user media relationship can be filtered by status', function (): void {
    $user = User::factory()->create();

    Media::factory()->pending()->count(2)->create(['user_id' => $user->id]);
    Media::factory()->count(3)->create(['user_id' => $user->id]); // completed by default

    $pendingMedia = $user->media()->where('status', MediaStatus::Pending->value)->get();
    $completedMedia = $user->media()->where('status', MediaStatus::Completed->value)->get();

    expect($pendingMedia)->toHaveCount(2)
        ->and($completedMedia)->toHaveCount(3);
});

test('user media relationship can be filtered by type', function (): void {
    $user = User::factory()->create();

    Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Image,
    ]);

    Media::factory()->create([
        'user_id' => $user->id,
        'type' => MediaType::Video,
    ]);

    $images = $user->media()->where('type', MediaType::Image->value)->get();
    $videos = $user->media()->where('type', MediaType::Video->value)->get();

    expect($images)->toHaveCount(1)
        ->and($videos)->toHaveCount(1);
});

test('user media relationship can be ordered', function (): void {
    $user = User::factory()->create();

    $media1 = Media::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(3),
    ]);

    $media2 = Media::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDay(),
    ]);

    $media3 = Media::factory()->create([
        'user_id' => $user->id,
        'created_at' => now(),
    ]);

    $latestMedia = $user->media()->latest()->get();

    expect($latestMedia->first()->id)->toBe($media3->id)
        ->and($latestMedia->last()->id)->toBe($media1->id);
});

test('user has no media by default', function (): void {
    $user = User::factory()->create();

    expect($user->media)->toHaveCount(0)
        ->and($user->media()->count())->toBe(0);
});

test('user media relationship returns collection', function (): void {
    $user = User::factory()->create();

    Media::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->media)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('user can count media without loading relationship', function (): void {
    $user = User::factory()->create();

    Media::factory()->count(5)->create(['user_id' => $user->id]);

    $count = $user->media()->count();

    expect($count)->toBe(5);
});

test('media relationship can be eager loaded', function (): void {
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        Media::factory()->count(2)->create(['user_id' => $user->id]);
    }

    $usersWithMedia = User::with('media')->get();

    expect($usersWithMedia)->toHaveCount(3);

    foreach ($usersWithMedia as $user) {
        expect($user->relationLoaded('media'))->toBeTrue()
            ->and($user->media)->toHaveCount(2);
    }
});

test('user can access latest media', function (): void {
    $user = User::factory()->create();

    Media::factory()->create([
        'user_id' => $user->id,
        'title' => 'Old Media',
        'created_at' => now()->subDays(5),
    ]);

    Media::factory()->create([
        'user_id' => $user->id,
        'title' => 'New Media',
        'created_at' => now(),
    ]);

    $latestMedia = $user->media()->latest()->first();

    expect($latestMedia->title)->toBe('New Media');
});
