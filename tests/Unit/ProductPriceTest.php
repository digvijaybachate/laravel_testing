<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductPriceTest extends TestCase
{

    public function test_product_price_set_successfuly()
    {
        $product = new Product([
            'name' => 'Test 123',
            'price' => 1.23
        ]);

        $this->assertEquals(123, $product->price);
    }
}
