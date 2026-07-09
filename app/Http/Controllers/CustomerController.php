<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\DemoData;
use App\Support\ListSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $customers = ListSearch::filter(Customer::query()->orderBy('id')->get(), $search, [
            'code_fields' => [],
            'customer_field' => 'name',
            'sku_fields' => [],
        ]);

        return view('customers.index', [
            'customers' => $customers,
            'orders' => DemoData::orders(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('customers.index')
            ->with('success', '得意先を登録しました。（テストデータのため保存はされません）');
    }

    public function show(Request $request, int $customer): View
    {
        $target = Customer::query()->find($customer) ?? abort(404);
        $search = ListSearch::params($request);
        $orders = ListSearch::filter(
            DemoData::orders()->where('customer', $target->name)->values(),
            $search
        );

        return view('customers.show', [
            'customer' => $target,
            'orders' => $orders,
            'search' => $search,
        ]);
    }
}
