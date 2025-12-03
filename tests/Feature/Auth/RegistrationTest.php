<?php

use App\Models\User;

test('user can register with valid credentials', function () {
    // Arrange
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/register', $userData);

    // Assert
    $response->assertStatus(201)
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'created_at'],
            'token',
        ])
        ->assertJson([
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'name' => 'John Doe',
    ]);

    expect($response['token'])->toBeString()->not->toBeEmpty();
});

test('user cannot register with existing email', function () {
    // Arrange
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $userData = [
        'name' => 'John Doe',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/register', $userData);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration requires name', function () {
    // Arrange
    $userData = [
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/register', $userData);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('registration requires email', function () {
    // Arrange
    $userData = [
        'name' => 'John Doe',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/register', $userData);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration requires valid email format', function () {
    // Arrange
    $userData = [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/register', $userData);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration requires password', function () {
    // Arrange
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password_confirmation' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/register', $userData);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration requires password with minimum 8 characters', function () {
    // Arrange
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ];

    // Act
    $response = $this->postJson('/api/register', $userData);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration requires password confirmation', function () {
    // Arrange
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different123',
    ];

    // Act
    $response = $this->postJson('/api/register', $userData);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registered user password is hashed', function () {
    // Arrange
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    // Act
    $this->postJson('/api/register', $userData);

    // Assert
    $user = User::where('email', 'john@example.com')->first();
    expect($user->password)->not->toBe('password123');
    expect(password_verify('password123', $user->password))->toBeTrue();
});
