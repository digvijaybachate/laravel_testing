<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_products_list()
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $response = $this->getJson('/api/products');

        $response->assertJsonFragment([
            'name' => $product1->name,
            'price' => $product1->price,
        ]);

        $response->assertJsonCount(2, 'data');
    }

    public function test_api_product_store_successful()
    {
        $product = [
            'name' => 'Product 1',
            'price' => 123
        ];
        $response = $this->postJson('/api/products', $product);

        $response->assertStatus(201);
        $response->assertJson([
            'name' => 'Product 1',
            'price' => 12300
        ]);
    }

    public function test_api_product_show_successful()
    {
        $productData = [
            'name' => 'Product 1',
            'price' => 123
        ];
        $product = Product::create($productData);

        $response = $this->getJson('/api/products/' . $product->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', $productData['name']);
        $response->assertJsonMissingPath('data.created_at');
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'price',
            ]
        ]);
    }

    public function test_api_product_update_successful()
    {
        $productData = [
            'name' => 'Product 1',
            'price' => 123
        ];
        $product = Product::create($productData);

        $response = $this->putJson('/api/products/' . $product->id, [
            'name' => 'Product 123',
            'price' => 124
        ]);

        $response->assertStatus(200);
        $response->assertJsonMissing($productData);
    }

    public function test_api_product_invalid_store_returns_error()
    {
        $product = [
            'name' => '',
            'price' => 123
        ];
        $response = $this->postJson('/api/products', $product);

        $response->assertStatus(422);
        $response->assertInvalid('name');
    }
}
