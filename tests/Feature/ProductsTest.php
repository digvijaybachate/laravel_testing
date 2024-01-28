<?php

namespace Tests\Feature;

use App\Jobs\NewProductNotifyJob;
use App\Jobs\ProductPublishJob;
use App\Mail\NewProductCreated;
use App\Models\Product;
use App\Models\User;
use App\Notifications\NewProductCreatedNotification;
use App\Services\ProductService;
use Brick\Math\Exception\NumberFormatException;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser();
        $this->admin = $this->createUser(isAdmin: true);
    }

    public function test_homepage_contains_empty_table(): void
    {


        $response = $this->actingAs($this->user)->get('/products');

        $response->assertStatus(200);

        $response->assertSee(__('No products found'));
    }

    public function test_homepage_contains_non_empty_table(): void
    {
        $product = Product::create([
            'name' => 'Product 1',
            'price' => '123',
            'published_at' => now()
        ]);

        $response = $this->actingAs($this->user)->get('/products');

        $response->assertStatus(200);

        $response->assertDontSee(__('No products found'));

        $response->assertViewHas('products', function (LengthAwarePaginator $collection) use ($product) {
            return $collection->contains($product);
        });
    }

    public function test_paginated_products_table_doesnt_contain_11th_record()
    {
        $products = Product::factory(11)->create();
        $lastProduct = $products->last();

        $response = $this->actingAs($this->user)->get('/products');

        $response->assertStatus(200);
        $response->assertViewHas('products', function (LengthAwarePaginator $collection) use ($lastProduct) {
            return $collection->doesntContain($lastProduct);
        });
    }

    public function test_admin_can_see_product_create_button()
    {
        $response = $this->actingAs($this->admin)->get('/products');

        $response->assertStatus(200);

        $response->assertSee('Add new product');
    }

    public function test_non_admin_cannot_see_products_create_button()
    {
        $response = $this->actingAs($this->user)->get('/products');

        $response->assertStatus(200);

        $response->assertDontSee('Add new product');
    }

    public function test_admin_can_access_products_create_page()
    {
        $response = $this->actingAs($this->admin)->get('/products/create');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_product_create_page()
    {
        $response = $this->actingAs($this->user)->get('/products/create');

        $response->assertStatus(403);
    }

    public function test_create_product_successful()
    {
        $product = [
            'name' => 'Product 123',
            'price' => '123',
            'published_at' => now()
        ];

        $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

        $response->assertStatus(200);

        //$response->assertSeeText($product['name']);

        $this->assertDatabaseHas('products',  [
            'name' => 'Product 123',
            'price' => '12300'
        ]);

        $lastProduct  = Product::latest()->first();

        $this->assertEquals($product['name'], $lastProduct->name);
        $this->assertEquals($product['price'] * 100, $lastProduct->price);
    }

    public function test_product_edit_contains_correct_value()
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)->get('products/' . $product->id . '/edit');

        $response->assertStatus(200);
        $response->assertSee('value="' . $product->name . '"', false);
        $response->assertSee('value="' . $product->price . '"', false);
        $response->assertViewHas('product', $product);
    }

    public function test_product_update_validation_error_redirects_back_to_form()
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)->put('products/' . $product->id, [
            'name' => '',
            'price' => ''
        ]);

        $response->assertStatus(302);
        $response->assertInvalid(['name', 'price']);
        $response->assertSessionHasErrors(['name', 'price']);
    }

    public function test_product_delete_successful()
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)->delete('products/' . $product->id);

        $response->assertStatus(302);
        $response->assertRedirect('products');

        $this->assertDatabaseMissing('products', $product->toArray());
        $this->assertDatabaseCount('products', 0);

        $this->assertModelMissing($product);
        $this->assertDatabaseEmpty('products');
    }

    public function test_product_service_create_returns_product()
    {
        $product = (new ProductService())->create(name: 'Test', price: 123);

        $this->assertInstanceOf(Product::class, $product);
    }

    public function test_product_service_create_return_validation()
    {
        try {
            $product = (new ProductService())->create(name: 'Test', price: 1234567);
        } catch (Exception $e) {
            $this->assertInstanceOf(NumberFormatException::class, $e);
        }
    }

    public function test_download_product_success()
    {
        $response = $this->get('/download');
        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename=product-specification.pdf'
        );
    }

    public function test_artisan_product_publish_command_fail()
    {
        $this->artisan('product:publish 1')->assertExitCode(-1)->expectsOutput('Product not found');
    }

    public function test_artisan_product_publish_command_successful()
    {
        $product = Product::factory()->create();

        $this->artisan('product:publish ' . $product->id)->assertSuccessful();
    }

    public function test_job_product_publish_successful()
    {
        $product = Product::factory()->create();
        $this->assertNull($product->published_at);

        (new ProductPublishJob($product->id))->handle();

        $product->refresh();
        $this->assertNotNull($product->published_at);
    }

    public function test_product_shows_when_published_at_correct_time()
    {
        $product = Product::factory()->create([
            'published_at' => now()->addDay()->setTime(14, 00),
        ]);

        $this->freezeTime(function () use ($product) {
            $this->travelTo(now()->addDay()->setTime(14, 01));
            $response = $this->actingAs($this->user)->get('/products');
            $response->assertSeeText($product->name);
        });
    }

    public function test_product_create_photo_upload_successful()
    {
        Storage::fake();
        $filename = 'photo1.jpg';

        $product = [
            'name' => 'Product 123',
            'price' => 1234,
            'photo' => UploadedFile::fake()->image($filename),
        ];
        $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

        $response->assertStatus(200);

        $lastProduct = Product::latest()->first();
        $this->assertEquals($filename, $lastProduct->photo);

        Storage::assertExists('products/' . $filename);
    }

    public function test_product_create_job_notification_dispatched_successfully()
    {
        Bus::fake();

        $product = [
            'name' => 'product 123',
            'price' => 123
        ];

        $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

        $response->assertStatus(200);

        Bus::assertDispatched(NewProductNotifyJob::class);
    }

    public function test_product_create_mail_send_successfully()
    {
        Mail::fake();
        Notification::fake();

        $product = [
            'name' => 'product 123',
            'price' => 123
        ];

        $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

        $response->assertStatus(200);

        Mail::assertSent(NewProductCreated::class);
        Notification::assertSentTo($this->admin,NewProductCreatedNotification::class);
    }

    private function createUser(bool $isAdmin = false)
    {
        return User::factory()->create([
            'is_admin' => $isAdmin,
        ]);
    }
}
