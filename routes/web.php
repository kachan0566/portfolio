<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryForecastController;
use App\Http\Controllers\MaterialPriceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReceivingController;
use App\Http\Controllers\ReceivingLineController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SalesForecastController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentPlanController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

// ダッシュボード
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// マスタ管理
Route::resource('products', ProductController::class)->except(['show']);
Route::get('/prices', [MaterialPriceController::class, 'index'])->name('prices.index');
Route::get('/prices/create', [MaterialPriceController::class, 'create'])->name('prices.create');
Route::post('/prices', [MaterialPriceController::class, 'store'])->name('prices.store');
Route::get('/prices/{price}/edit', [MaterialPriceController::class, 'edit'])->name('prices.edit');
Route::put('/prices/{price}', [MaterialPriceController::class, 'update'])->name('prices.update');
Route::get('/recipes/greige/create', [RecipeController::class, 'createGreige'])->name('recipes.greige.create');
Route::post('/recipes/greige', [RecipeController::class, 'storeGreige'])->name('recipes.greige.store');
Route::get('/recipes/greige/{greigeSku}/edit', [RecipeController::class, 'editGreige'])->name('recipes.greige.edit');
Route::put('/recipes/greige/{greigeSku}', [RecipeController::class, 'updateGreige'])->name('recipes.greige.update');
Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
Route::get('/recipes/create', [RecipeController::class, 'create'])->name('recipes.create');
Route::post('/recipes', [RecipeController::class, 'store'])->name('recipes.store');
Route::get('/recipes/{product}/edit', [RecipeController::class, 'edit'])->name('recipes.edit');
Route::put('/recipes/{product}', [RecipeController::class, 'update'])->name('recipes.update');
Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');

// 取引
Route::resource('orders', OrderController::class);
Route::post('/orders/{order}/link-purchase/{purchase}', [OrderController::class, 'linkPurchase'])
    ->name('orders.link-purchase');
Route::post('/orders/{order}/clear-allocation', [OrderController::class, 'clearAllocation'])
    ->name('orders.clear-allocation');
Route::post('/orders/{order}/remove-allocation/{purchase}', [OrderController::class, 'removeAllocation'])
    ->name('orders.remove-allocation');
Route::post('/orders/{order}/save-allocation', [OrderController::class, 'saveAllocation'])
    ->name('orders.save-allocation');
Route::post('/purchases/{purchase}/relink-order', [OrderController::class, 'relinkPurchase'])
    ->name('purchases.relink-order');
Route::patch('/purchases/{purchase}/arrival', [PurchaseOrderController::class, 'patchArrival'])
    ->name('purchases.patch-arrival');
Route::resource('purchases', PurchaseOrderController::class);
Route::get('/receivings', [ReceivingController::class, 'index'])->name('receivings.index');
Route::get('/receivings/create', [ReceivingController::class, 'create'])->name('receivings.create');
Route::post('/receivings', [ReceivingController::class, 'store'])->name('receivings.store');
Route::get('/receiving-lines/{line}', [ReceivingLineController::class, 'show'])->name('receiving-lines.show');
Route::get('/receiving-lines/{line}/amendments', [ReceivingLineController::class, 'amendments'])->name('receiving-lines.amendments');
Route::put('/receiving-lines/{line}/greige-rolls/{roll}', [ReceivingLineController::class, 'updateGreigeRoll'])->name('receiving-lines.update-greige-roll');
Route::put('/receiving-lines/{line}/product-rolls/{roll}', [ReceivingLineController::class, 'updateProductRoll'])->name('receiving-lines.update-product-roll');
Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
Route::get('/orders/{order}/shipment-plans/create', [ShipmentPlanController::class, 'create'])->name('shipment-plans.create');
Route::post('/orders/{order}/shipment-plans', [ShipmentPlanController::class, 'store'])->name('shipment-plans.store');

// 集計
Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::get('/inventory/forecast/csv', [InventoryForecastController::class, 'exportCsv'])->name('inventory.forecast.csv');
Route::post('/inventory/forecast/snapshot', [InventoryForecastController::class, 'storeSnapshot'])->name('inventory.forecast.snapshot');
Route::post('/inventory/forecast/adjustments', [InventoryForecastController::class, 'storeAdjustment'])->name('inventory.forecast.adjustments');
Route::get('/inventory/greige-forecast/csv', [InventoryForecastController::class, 'exportGreigeCsv'])->name('inventory.greige-forecast.csv');
Route::post('/inventory/greige-forecast/snapshot', [InventoryForecastController::class, 'storeGreigeSnapshot'])->name('inventory.greige-forecast.snapshot');
Route::post('/inventory/greige-forecast/adjustments', [InventoryForecastController::class, 'storeGreigeAdjustment'])->name('inventory.greige-forecast.adjustments');
Route::post('/inventory/forecast-combined/snapshot', [InventoryForecastController::class, 'storeCombinedSnapshot'])->name('inventory.forecast-combined.snapshot');
Route::get('/inventory/forecast/{product}', [InventoryForecastController::class, 'showProduct'])->name('inventory.forecast.show');
Route::get('/inventory/long-term/{product}', [InventoryForecastController::class, 'longTermDetail'])->name('inventory.long-term.show');
Route::post('/inventory/{product}/allocate', [InventoryController::class, 'allocate'])->name('inventory.allocate');
Route::get('/inventory/{product}', [InventoryController::class, 'show'])->name('inventory.show');
Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
Route::get('/sales/forecast/csv', [SalesForecastController::class, 'exportCsv'])->name('sales.forecast.csv');
Route::post('/sales/forecast/snapshot', [SalesForecastController::class, 'storeSnapshot'])->name('sales.forecast.snapshot');
Route::get('/sales/forecast/{product}', [SalesForecastController::class, 'showProduct'])->name('sales.forecast.show');
Route::post('/sales/forecast/{product}', [SalesForecastController::class, 'storeLines'])->name('sales.forecast.store');
Route::post('/sales/forecast/{product}/reset', [SalesForecastController::class, 'resetLines'])->name('sales.forecast.reset');
