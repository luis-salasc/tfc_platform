<?php

test('el registro público está desactivado', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'Usuario no invitado',
        'email' => 'intruso@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Sign up');
});
