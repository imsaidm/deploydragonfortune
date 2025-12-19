<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BacktestResultController extends Controller
{
    public function index(): View
    {
        return view('backtest-result.dashboard');
    }

    public function show(string $file): RedirectResponse
    {
        return redirect()->route('backtest-result.index');
    }
}

