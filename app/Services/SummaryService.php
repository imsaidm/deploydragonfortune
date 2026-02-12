<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\BinanceSpotController;
use App\Http\Controllers\BinanceFuturesController;
use App\Http\Controllers\BybitController;

class SummaryService
{
    public function Dtable(Request $r)
    {
        $query = DB::connection('methods')
            ->table('qc_method as qm')
            ->leftJoinSub(function ($query) {
                $query->from('qc_signal')
                    ->select('id_method')
                    ->selectRaw('COUNT(CASE WHEN real_tp != 0 THEN 1 END) AS total_tp')
                    ->selectRaw('COUNT(CASE WHEN real_sl != 0 THEN 1 END) AS total_sl')
                    ->selectRaw('COUNT(1) AS total_signal')
                    ->groupBy('id_method');
            }, 'stat', 'qm.id', '=', 'stat.id_method')
            ->select('qm.*')
            
            ->selectRaw("COALESCE(qm.opening_balance, 0) as opening_balance")
            ->selectRaw("COALESCE(qm.closing_balance, 0) as closing_balance")
            ->selectRaw("'********' as api_key") 
            ->selectRaw("'********' as secret_key") 
            ->selectRaw('COALESCE(stat.total_signal, 0) AS total_signal')
            ->selectRaw('COALESCE(stat.total_tp, 0) AS total_tp')
            ->selectRaw('COALESCE(stat.total_sl, 0) AS total_sl')
            ->where('qm.onactive', 1);

        if ($r->filled('search')) {
            $query->where(function($q) use ($r) {
                $q->where('qm.nama_metode', 'like', '%' . $r->search . '%')
                ->orWhere('qm.creator', 'like', '%' . $r->search . '%');
            });
        }
        // dd($r->all(), $r->input('order_by', 'id'));
        $orderBy = $r->input('order_by', 'id');
        $orderDir = $r->input('order_dir', 'asc');
        $perPage = min($r->input('per_page',10), 500);

        $validColumns = ['id', 'nama_metode', 'total_orders', 'total_tp', 'total_sl', 
                        'closing_balance', 'opening_balance', 'cagr', 'winrate', 
                        'lossrate', 'drawdown', 'prob_sr', 'sharpen_ratio', 'sortino_ratio', 
                        'information_ratio', 'turnover', 'total_signal'];
        if (in_array($orderBy, $validColumns)) {
            $query->orderBy($orderBy, $orderDir);
        }

        return response()->json($query->paginate($perPage));
    }

    public function ExchangeAccount(Request $r)
    {

        $new_request = new Request([
            'method_id' => $r["method_id"],
        ]);

        switch ($r->exchange) {
            case 'binance':
                if($r->market_type == "spot"){
                    $account = app(BinanceSpotController::class)->summary($new_request);
                }else{
                    $account = app(BinanceFuturesController::class)->summary($new_request);
                }
                break;
            case 'bybit':
                    $account = app(BybitController::class)->summary($new_request);
                break;
            default:
                $account = false;
        }

        $data = $account->getData(true);
        
        if ($data["success"]) {
            $new_request['balance'] = $data['summary']['total_usdt'];
            self::updateBalance($new_request);      
        }

        return $account;
    }

    public function updateBalance(Request $request)
    {
        try {
            DB::connection('methods')->transaction(function () use ($request) {            
                DB::connection('methods')->table('qc_method')
                    ->where('id', $request->method_id)
                    ->update([
                        'closing_balance' => $request->input('balance')
                    ]);            
            });

            return response()->json(['success' => true, 'message' => 'Update Closing Balance Completed!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

}
