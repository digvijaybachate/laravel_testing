<?php

test('unauthenticated user cannot access products', function () {
    $this->get('/products')
        ->assertStatus(302)
        ->assertRedirect('login');
});
