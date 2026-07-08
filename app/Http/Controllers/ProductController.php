<?php

namespace App\Http\Controllers;

use App\Models\Greige;
use App\Models\Product;
use App\Support\DemoData;
use App\Support\ListSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $products = ListSearch::filter(Product::displayCatalog(), $search, [
            'code_fields' => [],
            'sku_fields' => ['sku', 'greige_sku', 'color'],
        ]);

        return view('products.index', [
            'products' => $products,
            'categories' => DemoData::categories(),
            'greiges' => Greige::query()->orderBy('id')->get(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => DemoData::categories(),
            'greiges' => Greige::query()->orderBy('id')->get(),
        ]);
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('products.index')
            ->with('success', '商品を登録しました。（テストデータのため保存はされません）');
    }

    public function edit(int $product): View
    {
        $target = Product::query()->with('greige')->find($product) ?? abort(404);

        return view('products.edit', [
            'product' => $target->toDisplayObject(),
            'categories' => DemoData::categories(),
            'greiges' => Greige::query()->orderBy('id')->get(),
        ]);
    }

    public function update(int $product): RedirectResponse
    {
        return redirect()->route('products.index')
            ->with('success', '商品を更新しました。（テストデータのため保存はされません）');
    }

    public function destroy(int $product): RedirectResponse
    {
        return redirect()->route('products.index')
            ->with('success', '商品を削除しました。（テストデータのため保存はされません）');
    }
}
