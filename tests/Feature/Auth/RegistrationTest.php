<?php

test('registration is disabled', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
});

test('users can not self-register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertStatus(404);
});
