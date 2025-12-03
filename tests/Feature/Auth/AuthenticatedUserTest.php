<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('authenticated user can access their profile', function (): void {
    // Arrange
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    Sanctum::actingAs($user);

    // Act
    $response = $this->getJson('/api/user');

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'id' => $user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
});

test('unauthenticated user cannot access profile endpoint', function (): void {
    // Act
    $response = $this->getJson('/api/user');

    // Assert
    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('authenticated user data includes all expected fields', function (): void {
    // Arrange
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Act
    $response = $this->getJson('/api/user');

    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'name',
            'email',
            'email_verified_at',
            'created_at',
            'updated_at',
        ]);
});

test('user can access profile with valid token from login', function (): void {
    // Arrange
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    // Act - Login to get token
    $loginResponse = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $token = $loginResponse['token'];

    // Act - Use token to access profile
    $response = $this->withToken($token)->getJson('/api/user');

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'id' => $user->id,
            'email' => 'john@example.com',
        ]);
});

test('user cannot access profile with invalid token', function (): void {
    // Act
    $response = $this->withToken('invalid-token')->getJson('/api/user');

    // Assert
    $response->assertStatus(401);
});

test('user can access profile with token from registration', function (): void {
    // Arrange
    $userData = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    // Act - Register to get token
    $registerResponse = $this->postJson('/api/register', $userData);
    $token = $registerResponse['token'];

    // Act - Use token to access profile
    $response = $this->withToken($token)->getJson('/api/user');

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
});
