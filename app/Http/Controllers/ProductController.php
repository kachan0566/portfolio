<?php

namespace App\Http\Controllers;

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
        $products = ListSearch::filter(DemoData::products(), $search, [
            'code_fields' => [],
            'sku_fields' => ['sku', 'greige_sku', 'color'],
        ]);

        return view('products.index', [
            'products' => $products,
            'categories' => DemoData::categories(),
            'greiges' => DemoData::greiges(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => DemoData::categories(),
            'greiges' => DemoData::greiges(),
        ]);
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('products.index')
            ->with('success', '商品を登録しました。（テストデータのため保存はされません）');
    }

    public function edit(int $product): View
    {
        return view('products.edit', [
            'product' => DemoData::findProduct($product) ?? abort(404),
            'categories' => DemoData::categories(),
            'greiges' => DemoData::greiges(),
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
