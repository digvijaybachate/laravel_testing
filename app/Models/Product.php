<?php

namespace App\Models;

use App\Services\CurrencyService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price'
    ];

    protected function priceEur(): Attribute
    {
        return Attribute::make(
            get: fn () => (new CurrencyService())->convert($this->price, 'usd', 'eur'),
        );
    }
}
