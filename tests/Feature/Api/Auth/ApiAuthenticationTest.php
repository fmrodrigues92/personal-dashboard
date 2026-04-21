<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('users can authenticate through the api and receive a sanctum token', function () {
    $user = User::factory()->create();

    $response = postJson(route('api.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'postman',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'token_type',
            'token',
            'user' => [
                'id',
                'name',
                'email',
            ],
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', $user->email);

    expect($user->tokens()->count())->toBe(1);
});

test('api login fails with invalid credentials', function () {
    $user = User::factory()->create();

    $response = postJson(route('api.login'), [
        'email' => $user->email,
        'password' => 'invalid-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
