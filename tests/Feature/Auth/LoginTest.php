<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('user can login with valid credentials', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $credentials = [
        'email' => 'john@example.com',
        'password' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/login', $credentials);

    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'created_at'],
            'token',
        ])
        ->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => 'john@example.com',
            ],
        ]);

    expect($response['token'])->toBeString()->not->toBeEmpty();
});

test('user cannot login with incorrect password', function () {
    // Arrange
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'correct123',
    ]);

    $credentials = [
        'email' => 'john@example.com',
        'password' => 'wrong123',
    ];

    // Act
    $response = $this->postJson('/api/login', $credentials);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user cannot login with non-existent email', function () {
    // Arrange
    $credentials = [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/login', $credentials);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login requires email', function () {
    // Arrange
    $credentials = [
        'password' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/login', $credentials);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login requires password', function () {
    // Arrange
    $credentials = [
        'email' => 'john@example.com',
    ];

    // Act
    $response = $this->postJson('/api/login', $credentials);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('login requires valid email format', function () {
    // Arrange
    $credentials = [
        'email' => 'invalid-email',
        'password' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/login', $credentials);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login generates new token each time', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $credentials = [
        'email' => 'john@example.com',
        'password' => 'password123',
    ];

    // Act
    $firstResponse = $this->postJson('/api/login', $credentials);
    $secondResponse = $this->postJson('/api/login', $credentials);

    // Assert
    expect($firstResponse['token'])->not->toBe($secondResponse['token']);
});
