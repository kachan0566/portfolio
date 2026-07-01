<?php

namespace App\Http\Controllers;

use App\Support\AllocationConversion;
use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\ListSearch;
use App\Support\StockAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $ym = DemoData::CURRENT_YM;
        $search = ListSearch::params($request);

        $products = DemoData::products()->map(function ($p) use ($ym) {
            $p->unit_cost = (int) round(DemoData::unitCost($p->id, $ym));
            $p->stock_value = $p->unit_cost * DemoState::effectiveStock($p->id);

            return $p;
        });

        $products = ListSearch::filter($products, $search, [
            'code_fields' => [],
            'sku_fields' => ['sku'],
            'status_resolver' => function ($product, $status) {
                if (! in_array($status, ['在庫不足', '残少なめ', '十分'], true)) {
                    return false;
                }
                $stock = DemoState::effectiveStock($product->id);
                if ($status === '在庫不足') {
                    return $stock < $product->stock_min;
                }
                if ($status === '残少なめ') {
                    return $stock >= $product->stock_min
                        && $stock < $product->stock_min * 1.5;
                }

                return $stock >= $product->stock_min * 1.5;
            },
        ]);

        $movementSearch = $search;
        $movementSearch['status'] = '';
        $movements = ListSearch::filter(DemoData::stockMovements(), $movementSearch, [
            'code_fields' => [],
            'sku_fields' => ['sku'],
            'date_field' => 'date',
        ]);

        $productionSearch = $search;
        if (in_array($productionSearch['status'], ['在庫不足', '残少なめ', '十分'], true)) {
            $productionSearch['status'] = '';
        }
        $inProduction = ListSearch::filter(
            DemoData::purchaseOrders()->whereNotIn('stage', ['原材料未発注', '製品出荷済'])->values(),
            $productionSearch,
            [
                'date_field' => 'finish_date',
                'status_field' => 'stage',
            ]
        );

        return view('inventory.index', [
            'products' => $products,
            'movements' => $movements,
            'inProduction' => $inProduction,
            'lowStockCount' => $products->filter(fn ($p) => DemoState::effectiveStock($p->id) < $p->stock_min)->count(),
            'totalStock' => $products->sum(fn ($p) => DemoState::effectiveStock($p->id)),
            'stockValue' => $products->sum('stock_value'),
            'search' => $search,
        ]);
    }

    public function show(int $product): View
    {
        $ym = DemoData::CURRENT_YM;
        $target = DemoData::findProduct($product) ?? abort(404);
        $effectiveStock = DemoState::effectiveStock($product);

        $orders = DemoData::orders()
            ->where('product_id', $product)
            ->map(function ($o) {
                $o->remaining = DemoState::orderRemaining($o->id);

                return $o;
            })
            ->values();

        $outstanding = $orders->sum('remaining');
        $balance = $effectiveStock - $outstanding;
        $unitCost = (int) round(DemoData::unitCost($product, $ym));

        $purchases = DemoData::purchaseOrders()
            ->where('product_id', $product)
            ->values();

        $productForResolve = (object) array_merge((array) $target, ['stock' => $effectiveStock]);
        $allocation = StockAllocation::resolveForProduct($productForResolve, $orders, $purchases);
        $usage = StockAllocation::usageByPoAndType($product);
        $poOptions = StockAllocation::poOptionsForProduct($product);
        $conversionHistory = AllocationConversion::forProduct($product);

        $allocationOrders = $allocation['allocations']->map(function ($a) {
            $o = $a->order;
            $o->allocation_status = $a->status;
            $o->allocation_badge = $a->badge_class;
            $o->stock_allocated = $a->stock_allocated;
            $o->po_allocated = $a->po_allocated;
            $o->stock_lines = $a->stock_lines;
            $o->po_lines = $a->po_lines;
            $o->allocated = $a->allocated;

            return $o;
        });

        return view('inventory.show', [
            'product' => $target,
            'effectiveStock' => $effectiveStock,
            'unitCost' => $unitCost,
            'orders' => $orders,
            'purchases' => $purchases,
            'outstanding' => $outstanding,
            'balance' => $balance,
            'allocations' => $allocation['allocations'],
            'allocationOrders' => $allocationOrders,
            'allocatedTotal' => $allocation['allocatedTotal'],
            'stockAllocatedTotal' => $allocation['stockAllocatedTotal'],
            'poAllocatedTotal' => $allocation['poAllocatedTotal'],
            'unallocatedStock' => $allocation['unallocatedStock'],
            'allocationShortage' => $allocation['allocationShortage'],
            'allocationRecorded' => $allocation['isRecorded'],
            'stockUsageByPo' => $usage['stock'],
            'poUsageByPo' => $usage['po'],
            'stockPoOptions' => $poOptions['stock'],
            'poPoOptions' => $poOptions['po'],
            'conversionHistory' => $conversionHistory,
            'movements' => DemoData::stockMovements()->where('product_id', $product)->values(),
        ]);
    }

    public function allocate(Request $request, int $product): RedirectResponse
    {
        $target = DemoData::findProduct($product) ?? abort(404);
        $input = $request->input('allocations', []);

        $error = StockAllocation::validateSubmission($product, $input);
        if ($error !== null) {
            return redirect()->route('inventory.show', $target->id)
                ->with('error', $error)
                ->withInput();
        }

        $toSave = StockAllocation::parseSubmission($product, $input);
        StockAllocation::saveFromTypedMaps($product, $toSave);

        return redirect()->route('inventory.show', $target->id)
            ->with('success', '引当を更新しました。');
    }
}
