<?php

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

beforeEach(function () {
    $this->user = createUser();
    $this->admin = createUser(isAdmin: true);
});

test('homepage contains empty table', function () {
    $this->actingAs($this->user)->get('/products')
        ->assertStatus(200)
        ->assertSee(__('No products found'));
});


test('homepage contains non empty table', function () {
    $product = Product::factory()->create(['published_at' => now()]);

    $this->actingAs($this->user)
        ->get('/products')
        ->assertStatus(200)
        ->assertDontSee(__('No products found'))
        ->assertViewHas('products', function (LengthAwarePaginator $collection) use ($product) {
            return $collection->contains($product);
        });
});

test('create product successful', function () {
    $product = [
        'name' => 'Product 123',
        'price' => 1234
    ];

    $this->actingAs($this->admin)
        ->post('/products', $product)
        ->assertRedirect('products');

    $this->assertDatabaseHas('products', [
        'name' => 'Product 123',
        'price' => 123400
    ]);

    $lastProduct = Product::latest()->first();

    expect($lastProduct->name)->toBe($product['name'])->and($lastProduct->price)->toBe($product['price'] * 100);
});
