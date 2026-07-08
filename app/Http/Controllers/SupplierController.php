<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Support\DemoData;
use App\Support\ListSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $suppliers = ListSearch::filter(Supplier::query()->orderBy('id')->get(), $search, [
            'code_fields' => [],
            'supplier_field' => 'name',
            'sku_fields' => [],
        ]);

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'purchases' => DemoData::purchaseOrders(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('suppliers.create');
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('suppliers.index')
            ->with('success', '仕入先を登録しました。（テストデータのため保存はされません）');
    }

    public function show(Request $request, int $supplier): View
    {
        $target = Supplier::query()->find($supplier) ?? abort(404);
        $search = ListSearch::params($request);
        $purchases = ListSearch::filter(
            DemoData::purchaseOrders()->where('supplier', $target->name)->values(),
            $search,
            [
                'date_field' => 'eta',
                'status_field' => 'stage',
            ]
        );

        return view('suppliers.show', [
            'supplier' => $target,
            'purchases' => $purchases,
            'search' => $search,
        ]);
    }
}
