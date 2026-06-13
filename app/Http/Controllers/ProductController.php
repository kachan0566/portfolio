<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::orderBy('id')->get();

        return view('products.index', compact('products'));
    }

    public function create(): View
{
    return view('products.create');
}

public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
        'price' => ['required', 'integer', 'min:0'],
        'category' => ['required', 'string', 'max:255'],
        'unit' => ['required', 'string', 'max:255'],
    ]);

    Product::create($validated);

    return redirect()
        ->route('products.index')
        ->with('success', '商品を登録しました。');
}

public function edit(Product $product): View
{
    return view('products.edit', compact('product'));
}

public function update(Request $request, Product $product): RedirectResponse
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'sku' => ['required', 'string', 'max:255', 'unique:products,sku,' . $product->id],
        'price' => ['required', 'integer', 'min:0'],
        'category' => ['required', 'string', 'max:255'],
        'unit' => ['required', 'string', 'max:255'],
    ]);

    $product->update($validated);

    return redirect()
        ->route('products.index')
        ->with('success', '商品を更新しました。');
}

public function destroy(Product $product): RedirectResponse
{
    $product->delete();

    return redirect()
        ->route('products.index')
        ->with('success', '商品を削除しました。');
}
}

