<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Jobs\NewProductNotifyJob;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::published()->paginate(10);
        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $productData = $request->validated();

        if ($request->hasFile('photo')) {
            $filename = $request->file('photo')->getClientOriginalName();
            $request->file('photo')->storeAs('products', $filename);
            $productData['photo'] = $filename;
        }

        $product = Product::create($productData);

        NewProductNotifyJob::dispatch($product);

        return redirect()->route('products.index');
    }

    public function edit(Product $product): View
    {
        return view('products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index');
    }

    public function download()
    {
        return response()->download(public_path('files/product-specification.pdf'));
    }
}
