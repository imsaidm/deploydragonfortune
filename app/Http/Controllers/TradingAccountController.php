<?php

namespace App\Http\Controllers;

use App\Models\TradingAccount;
use App\Http\Requests\TradingAccountRequest;
use App\Services\BinanceService;
use Illuminate\Http\Request;

class TradingAccountController extends Controller
{
    protected $binanceService;

    public function __construct(BinanceService $binanceService)
    {
        $this->binanceService = $binanceService;
    }

    public function index()
    {
        $accounts = TradingAccount::with('strategies')->get();
        return view('trading-accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('trading-accounts.create');
    }

    public function store(TradingAccountRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->has('is_active');
        TradingAccount::create($data);
        return redirect()->route('trading-accounts.index')->with('success', 'Trading account created successfully.');
    }

    public function edit(TradingAccount $tradingAccount)
    {
        return view('trading-accounts.edit', compact('tradingAccount'));
    }

    public function update(TradingAccountRequest $request, TradingAccount $tradingAccount)
    {
        $data = $request->validated();
        
        // Ensure is_active is captured (handles unchecked checkbox)
        $data['is_active'] = $request->has('is_active');

        if (empty($data['secret_key'])) {
            unset($data['secret_key']);
        }
        $tradingAccount->update($data);
        return redirect()->route('trading-accounts.index')->with('success', 'Trading account updated successfully.');
    }

    public function destroy(TradingAccount $tradingAccount)
    {
        $tradingAccount->delete();
        return redirect()->route('trading-accounts.index')->with('success', 'Trading account deleted successfully.');
    }

    public function getBalance(TradingAccount $tradingAccount)
    {
        try {
            $balance = $this->binanceService->getBalance($tradingAccount);
            return response()->json([
                'success' => true,
                'balance' => $balance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get list of available strategies and current links.
     */
    public function getStrategies(TradingAccount $tradingAccount)
    {
        $allStrategies = \App\Models\QcMethod::all(['id', 'nama_metode', 'pair', 'onactive']);
        $linkedStrategyIds = $tradingAccount->strategies()->wherePivot('is_active', true)->pluck('strategy_id')->toArray();

        return response()->json([
            'success' => true,
            'strategies' => $allStrategies,
            'linked_ids' => $linkedStrategyIds,
        ]);
    }

    /**
     * Sync strategies for a trading account.
     */
    public function syncStrategies(Request $request, TradingAccount $tradingAccount)
    {
        $strategyIds = $request->input('strategy_ids', []);
        
        // Syncing with pivot data: all selected strategies are active
        $syncData = [];
        foreach ($strategyIds as $id) {
            $syncData[$id] = ['is_active' => true];
        }

        $tradingAccount->strategies()->sync($syncData);

        return response()->json([
            'success' => true,
            'message' => 'Strategies linked successfully.',
        ]);
    }
}
