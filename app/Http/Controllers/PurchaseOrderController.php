<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use App\Support\ListSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $purchases = ListSearch::filter(DemoData::purchaseOrders(), $search, [
            'date_field' => 'eta',
            'status_field' => 'stage',
        ]);

        return view('purchases.index', [
            'purchases' => $purchases,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $sourceOrder = null;
        if ($request->filled('order_id')) {
            $sourceOrder = DemoData::orders()->firstWhere('id', (int) $request->query('order_id'));
        }

        return view('purchases.create', [
            'suppliers' => DemoData::suppliers(),
            'products' => DemoData::products(),
            'customers' => DemoData::customers(),
            'sourceOrder' => $sourceOrder,
            'suggestedQty' => $request->filled('qty') ? (int) $request->query('qty') : null,
        ]);
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('purchases.index')
            ->with('success', '発注を登録しました。（テストデータのため保存はされません）');
    }

    public function edit(int $purchase): View
    {
        $target = DemoData::purchaseOrders()->firstWhere('id', $purchase) ?? abort(404);

        return view('purchases.edit', [
            'purchase' => $target,
            'suppliers' => DemoData::suppliers(),
            'products' => DemoData::products(),
            'customers' => DemoData::customers(),
        ]);
    }

    public function update(int $purchase): RedirectResponse
    {
        return redirect()->route('purchases.index')
            ->with('success', '発注を更新しました。（テストデータのため保存はされません）');
    }

    public function destroy(int $purchase): RedirectResponse
    {
        return redirect()->route('purchases.index')
            ->with('success', '発注を削除しました。（テストデータのため保存はされません）');
    }
}
