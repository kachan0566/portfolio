<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Inventory\CombinedMonthEndForecastEngine;
use App\Services\Inventory\GreigeMonthEndForecastEngine;
use App\Services\Inventory\LongTermInventoryEngine;
use App\Services\Inventory\MonthEndForecastEngine;
use App\Support\AllocationConversion;
use App\Support\BusinessDate;
use App\Support\DemoData;
use App\Support\GreigeInventory;
use App\Support\ListSearch;
use App\Support\MasterCatalog;
use App\Support\ProductStock;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use App\Support\StockAllocation;
use App\Support\YarnInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $ym = BusinessDate::currentYm();
        $search = ListSearch::params($request);

        $products = MasterCatalog::products()->map(function ($p) use ($ym) {
            $p->stock = ProductStock::effectiveStock($p->id);
            $unitCost = DemoData::unitCost($p->id, $ym);
            $p->unit_cost = $unitCost !== null ? (int) round($unitCost) : null;
            $p->cost_calculable = $unitCost !== null;
            $p->stock_value = $p->unit_cost !== null
                ? $p->unit_cost * ProductStock::effectiveStock($p->id)
                : null;

            return $p;
        });

        $products = ListSearch::filter($products, $search, [
            'code_fields' => [],
            'sku_fields' => ['sku'],
            'status_resolver' => function ($product, $status) {
                if (! in_array($status, ['在庫不足', '残少なめ', '十分'], true)) {
                    return false;
                }
                $stock = ProductStock::effectiveStock($product->id);
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
            DemoData::purchaseOrders()
                ->filter(fn ($po) => ($po->type ?? '') === PurchaseOrderType::PRODUCT)
                ->filter(fn ($po) => ! in_array($po->stage ?? '', [
                    PurchaseOrderStages::LABEL_RECEIVED,
                    PurchaseOrderStages::LABEL_CANCELLED,
                    PurchaseOrderStages::LABEL_DRAFT,
                ], true))
                ->values(),
            $productionSearch,
            [
                'date_field' => 'finish_date',
                'status_field' => 'stage',
            ]
        );

        $tab = $request->query('tab', 'product');
        if (! in_array($tab, ['product', 'forecast_combined', 'forecast', 'greige_forecast', 'long_term', 'greige', 'yarn'], true)) {
            $tab = 'product';
        }

        $forecastYm = $this->resolveForecastYm($request);
        $combinedForecast = $tab === 'forecast_combined' ? CombinedMonthEndForecastEngine::build($forecastYm) : null;
        $forecast = $tab === 'forecast' ? MonthEndForecastEngine::build($forecastYm) : null;
        $forecastLines = null;
        $forecastSummary = null;
        $greigeForecast = $tab === 'greige_forecast' ? GreigeMonthEndForecastEngine::build($forecastYm) : null;
        $greigeForecastLines = null;
        $greigeForecastSummary = null;

        if ($forecast !== null) {
            $forecastLines = ListSearch::filter($forecast->lines, $search, [
                'code_fields' => [],
                'sku_fields' => ['sku'],
                'status_resolver' => function ($line, $status) {
                    return match ($status) {
                        '正常' => $line->warnings === [],
                        '原価未登録' => in_array('原価未登録', $line->warnings, true),
                        '在庫不足予想' => $line->is_negative,
                        '安全在庫割れ' => $line->is_shortage && ! $line->is_negative,
                        default => false,
                    };
                },
            ]);
            $forecastSummary = MonthEndForecastEngine::summarizeLines($forecastLines, $forecastYm);
        }

        if ($greigeForecast !== null) {
            $greigeForecastLines = ListSearch::filter($greigeForecast->lines, $search, [
                'code_fields' => [],
                'sku_fields' => ['greige_sku', 'sku'],
                'status_resolver' => function ($line, $status) {
                    return match ($status) {
                        '正常' => $line->warnings === [],
                        '原価未登録' => in_array('原価未登録', $line->warnings, true),
                        '在庫不足予想' => $line->is_negative,
                        default => false,
                    };
                },
            ]);
            $greigeForecastSummary = GreigeMonthEndForecastEngine::summarizeLines($greigeForecastLines, $forecastYm);
        }

        $longTerm = $tab === 'long_term' ? LongTermInventoryEngine::build() : null;

        $greigeEntries = GreigeInventory::entries();

        $yarnRows = MasterCatalog::yarnMaterials()->map(function ($m) {
            return (object) [
                'material_id' => $m->id,
                'sku' => $m->sku,
                'name' => $m->name,
                'stock_kg' => YarnInventory::effectiveStockKg($m->id),
                'on_order_kg' => YarnInventory::onOrderRemainingKg($m->id),
                'allocated_kg' => YarnInventory::allocatedKg($m->id),
                'available_kg' => YarnInventory::availableKg($m->id),
            ];
        });

        $yarnMovements = collect(YarnInventory::stockMovements())->map(function ($m) {
            $material = MasterCatalog::findMaterial($m['material_id']);

            return (object) [
                'date' => $m['date'],
                'sku' => $material?->sku ?? '—',
                'name' => $material?->name ?? '—',
                'qty_kg' => $m['qty_kg'],
                'note' => $m['note'],
            ];
        });

        return view('inventory.index', [
            'products' => $products,
            'movements' => $movements,
            'inProduction' => $inProduction,
            'greigeEntries' => $greigeEntries,
            'greigeTotalMeters' => GreigeInventory::totalMeters(),
            'greigeTotalTan' => $greigeEntries->sum(fn ($g) => QtyHelper::tanCount($g->qty_meters, null, true, $g->greige_sku)),
            'yarnRows' => $yarnRows,
            'yarnMovements' => $yarnMovements,
            'yarnTotalKg' => $yarnRows->sum('stock_kg'),
            'tab' => $tab,
            'lowStockCount' => $products->filter(fn ($p) => ProductStock::effectiveStock($p->id) < $p->stock_min)->count(),
            'totalStock' => $products->sum(fn ($p) => ProductStock::effectiveStock($p->id)),
            'stockValue' => $products->sum(fn ($p) => $p->stock_value ?? 0),
            'hasUncalculableCost' => $products->contains(fn ($p) => ! $p->cost_calculable),
            'costWarnings' => DemoData::collectCostWarnings(
                $products->where('cost_calculable', false)->pluck('id'),
                $ym
            ),
            'search' => $search,
            'forecastYm' => $forecastYm,
            'forecastMonthOptions' => DemoData::forecastMonthOptions(),
            'combinedForecast' => $combinedForecast,
            'forecast' => $forecast,
            'forecastLines' => $forecastLines,
            'forecastSummary' => $forecastSummary,
            'greigeForecast' => $greigeForecast,
            'greigeForecastLines' => $greigeForecastLines,
            'greigeForecastSummary' => $greigeForecastSummary,
            'longTerm' => $longTerm,
        ]);
    }

    private function resolveForecastYm(Request $request): string
    {
        $currentYm = BusinessDate::currentYm();
        $ym = (string) $request->query('ym', $currentYm);

        return DemoData::isValidForecastMonth($ym) ? $ym : $currentYm;
    }

    public function show(int $product): View
    {
        $ym = BusinessDate::currentYm();
        $target = MasterCatalog::findProductOrFail($product);
        $effectiveStock = ProductStock::effectiveStock($product);

        $orders = DemoData::orders()
            ->where('product_id', $product)
            ->map(function ($o) {
                $o->remaining = Order::remainingFor($o->id);

                return $o;
            })
            ->values();

        $outstanding = $orders->sum('remaining');
        $balance = $effectiveStock - $outstanding;
        $unitCostValue = DemoData::unitCost($product, $ym);
        $unitCost = $unitCostValue !== null ? (int) round($unitCostValue) : null;
        $costWarnings = DemoData::costWarningMessages($product, $ym);

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
            'costCalculable' => $unitCost !== null,
            'costWarnings' => $costWarnings,
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
        $target = MasterCatalog::findProductOrFail($product);
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
