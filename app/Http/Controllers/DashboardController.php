<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'data' => DemoData::dashboard(),
            'orders' => DemoData::recentOrders(),
        ]);
    }
}
