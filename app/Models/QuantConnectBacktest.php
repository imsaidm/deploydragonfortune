<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuantConnectBacktest extends Model
{
    use HasFactory;

    protected $table = 'quantconnect_backtests';

    protected $fillable = [
        'backtest_id',
        'project_id',
        'name',
        'strategy_type',
        'description',
        'backtest_start',
        'backtest_end',
        'duration_days',
        'total_return',
        'cagr',
        'sharpe_ratio',
        'sortino_ratio',
        'calmar_ratio',
        'max_drawdown',
        'recovery_days',
        'total_trades',
        'winning_trades',
        'losing_trades',
        'win_rate',
        'profit_factor',
        'expectancy',
        'avg_win',
        'avg_loss',
        'largest_win',
        'largest_loss',
        'longest_win_streak',
        'longest_loss_streak',
        'starting_capital',
        'ending_capital',
        'total_fees',
        'equity_curve',
        'monthly_returns',
        'trades',
        'parameters',
        'raw_result',
        'status',
        'import_source',
    ];

    protected $casts = [
        'backtest_start' => 'datetime',
        'backtest_end' => 'datetime',
        'total_return' => 'float',
        'cagr' => 'float',
        'sharpe_ratio' => 'float',
        'sortino_ratio' => 'float',
        'calmar_ratio' => 'float',
        'max_drawdown' => 'float',
        'win_rate' => 'float',
        'profit_factor' => 'float',
        'expectancy' => 'float',
        'avg_win' => 'float',
        'avg_loss' => 'float',
        'largest_win' => 'float',
        'largest_loss' => 'float',
        'starting_capital' => 'float',
        'ending_capital' => 'float',
        'total_fees' => 'float',
        'equity_curve' => 'array',
        'monthly_returns' => 'array',
        'trades' => 'array',
        'parameters' => 'array',
        'raw_result' => 'array',
    ];

    /**
     * Parse QuantConnect JSON result and create/update backtest record
     */
    public static function importFromQuantConnect(array $data, string $source = 'manual'): self
    {
        $backtestId = $data['BacktestId'] ?? $data['backtestId'] ?? uniqid('bt_');
        
        // Extract statistics - handle both formats
        $stats = $data['Statistics'] ?? $data['statistics'] ?? [];
        $runtime = $data['RuntimeStatistics'] ?? $data['runtimeStatistics'] ?? [];
        
        // Parse dates
        $startDate = $data['StartDate'] ?? $data['startDate'] ?? null;
        $endDate = $data['EndDate'] ?? $data['endDate'] ?? null;
        
        // Calculate duration
        $durationDays = null;
        if ($startDate && $endDate) {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);
            $durationDays = $start->diffInDays($end);
        }

        // Extract metrics
        $totalReturn = self::parsePercent($stats['Total Net Profit'] ?? $stats['Net Profit'] ?? null);
        $sharpe = floatval($stats['Sharpe Ratio'] ?? $runtime['Sharpe Ratio'] ?? 0);
        $winRate = self::parsePercent($stats['Win Rate'] ?? null);
        $maxDrawdown = self::parsePercent($stats['Drawdown'] ?? $stats['Max Drawdown'] ?? null);
        $profitFactor = floatval($stats['Profit-Loss Ratio'] ?? $stats['Profit Factor'] ?? 0);
        
        // Parse trades
        $orders = $data['Orders'] ?? $data['orders'] ?? [];
        $trades = self::parseOrders($orders);
        $totalTrades = count($trades);
        $winningTrades = count(array_filter($trades, fn($t) => ($t['pnl'] ?? 0) > 0));
        $losingTrades = count(array_filter($trades, fn($t) => ($t['pnl'] ?? 0) < 0));
        
        // Parse equity curve
        $profitLoss = $data['ProfitLoss'] ?? $data['profitLoss'] ?? [];
        $equityCurve = self::parseEquityCurve($profitLoss);
        
        // Calculate monthly returns
        $monthlyReturns = self::calculateMonthlyReturns($equityCurve);

        return self::updateOrCreate(
            ['backtest_id' => $backtestId],
            [
                'project_id' => $data['ProjectId'] ?? $data['projectId'] ?? null,
                'name' => $data['Name'] ?? $data['name'] ?? 'Unnamed Strategy',
                'strategy_type' => $data['AlgorithmType'] ?? $data['algorithmType'] ?? 'Unknown',
                'description' => $data['Description'] ?? $data['description'] ?? null,
                'backtest_start' => $startDate ? \Carbon\Carbon::parse($startDate) : null,
                'backtest_end' => $endDate ? \Carbon\Carbon::parse($endDate) : null,
                'duration_days' => $durationDays,
                'total_return' => $totalReturn,
                'cagr' => self::parsePercent($stats['Compounding Annual Return'] ?? null),
                'sharpe_ratio' => $sharpe,
                'sortino_ratio' => floatval($stats['Sortino Ratio'] ?? 0),
                'calmar_ratio' => floatval($stats['Calmar Ratio'] ?? 0),
                'max_drawdown' => $maxDrawdown,
                'recovery_days' => intval($stats['Recovery Days'] ?? 0),
                'total_trades' => $totalTrades ?: intval($stats['Total Trades'] ?? 0),
                'winning_trades' => $winningTrades,
                'losing_trades' => $losingTrades,
                'win_rate' => $winRate,
                'profit_factor' => $profitFactor,
                'expectancy' => floatval($stats['Expectancy'] ?? 0),
                'avg_win' => self::parsePercent($stats['Average Win'] ?? null),
                'avg_loss' => self::parsePercent($stats['Average Loss'] ?? null),
                'largest_win' => self::parsePercent($stats['Largest Win'] ?? null),
                'largest_loss' => self::parsePercent($stats['Largest Loss'] ?? null),
                'longest_win_streak' => intval($stats['Longest Win Streak'] ?? 0),
                'longest_loss_streak' => intval($stats['Longest Loss Streak'] ?? 0),
                'starting_capital' => floatval($stats['Starting Capital'] ?? $runtime['Equity'] ?? 100000),
                'ending_capital' => floatval($stats['Ending Capital'] ?? $runtime['Net Value'] ?? 0),
                'total_fees' => floatval($stats['Total Fees'] ?? 0),
                'equity_curve' => $equityCurve,
                'monthly_returns' => $monthlyReturns,
                'trades' => $trades,
                'parameters' => $data['Parameters'] ?? $data['parameters'] ?? [],
                'raw_result' => $data,
                'status' => $data['Status'] ?? 'completed',
                'import_source' => $source,
            ]
        );
    }

    protected static function parsePercent($value): ?float
    {
        if ($value === null) return null;
        if (is_numeric($value)) return floatval($value);
        
        // Remove % sign and parse
        $cleaned = str_replace(['%', ',', ' '], '', $value);
        return floatval($cleaned);
    }

    protected static function parseOrders(array $orders): array
    {
        $trades = [];
        $positions = [];

        foreach ($orders as $order) {
            $symbol = $order['Symbol'] ?? $order['symbol'] ?? 'UNKNOWN';
            $quantity = floatval($order['Quantity'] ?? $order['quantity'] ?? 0);
            $price = floatval($order['Price'] ?? $order['price'] ?? 0);
            $time = $order['Time'] ?? $order['time'] ?? null;
            $direction = $order['Direction'] ?? $order['direction'] ?? ($quantity > 0 ? 'Buy' : 'Sell');

            // Simplified trade tracking
            if (!isset($positions[$symbol])) {
                $positions[$symbol] = [
                    'quantity' => 0,
                    'entry_price' => 0,
                    'entry_time' => null,
                ];
            }

            $pos = &$positions[$symbol];

            // Opening position
            if ($pos['quantity'] == 0 && $quantity != 0) {
                $pos['quantity'] = $quantity;
                $pos['entry_price'] = $price;
                $pos['entry_time'] = $time;
            }
            // Closing position
            elseif ($pos['quantity'] != 0 && (($pos['quantity'] > 0 && $quantity < 0) || ($pos['quantity'] < 0 && $quantity > 0))) {
                $pnl = ($price - $pos['entry_price']) * abs($pos['quantity']);
                if ($pos['quantity'] < 0) $pnl *= -1;
                
                $returnPct = $pos['entry_price'] > 0 ? (($price - $pos['entry_price']) / $pos['entry_price']) * 100 : 0;
                if ($pos['quantity'] < 0) $returnPct *= -1;

                $entryTime = $pos['entry_time'] ? \Carbon\Carbon::parse($pos['entry_time']) : null;
                $exitTime = $time ? \Carbon\Carbon::parse($time) : null;
                $durationHours = ($entryTime && $exitTime) ? $entryTime->diffInHours($exitTime) : null;

                $trades[] = [
                    'id' => count($trades) + 1,
                    'symbol' => $symbol,
                    'direction' => $pos['quantity'] > 0 ? 'LONG' : 'SHORT',
                    'entryPrice' => $pos['entry_price'],
                    'exitPrice' => $price,
                    'quantity' => abs($pos['quantity']),
                    'pnl' => $pnl,
                    'returnPct' => $returnPct,
                    'entryTime' => $pos['entry_time'],
                    'exitTime' => $time,
                    'durationHours' => $durationHours,
                ];

                $pos['quantity'] = 0;
            }
        }

        return array_reverse($trades); // Most recent first
    }

    protected static function parseEquityCurve(array $profitLoss): array
    {
        $curve = [];
        $equity = 100000; // Default starting capital

        foreach ($profitLoss as $date => $pnl) {
            $equity += floatval($pnl);
            $curve[] = [
                'date' => $date,
                'equity' => round($equity, 2),
            ];
        }

        return $curve;
    }

    protected static function calculateMonthlyReturns(array $equityCurve): array
    {
        if (empty($equityCurve)) return [];

        $monthlyReturns = [];
        $monthlyEquity = [];

        foreach ($equityCurve as $point) {
            $date = \Carbon\Carbon::parse($point['date']);
            $month = $date->format('Y-m');
            $monthlyEquity[$month] = $point['equity'];
        }

        $months = array_keys($monthlyEquity);
        for ($i = 1; $i < count($months); $i++) {
            $prevEquity = $monthlyEquity[$months[$i - 1]];
            $currEquity = $monthlyEquity[$months[$i]];
            $return = $prevEquity > 0 ? (($currEquity - $prevEquity) / $prevEquity) * 100 : 0;

            $monthlyReturns[] = [
                'month' => \Carbon\Carbon::parse($months[$i] . '-01')->format('M Y'),
                'return' => round($return, 2),
            ];
        }

        return $monthlyReturns;
    }

    /**
     * Format for API response
     */
    public function toApiFormat(): array
    {
        return [
            'id' => $this->backtest_id,
            'name' => $this->name,
            'strategyType' => $this->strategy_type,
            'description' => $this->description,
            'startDate' => $this->backtest_start?->toIso8601String(),
            'endDate' => $this->backtest_end?->toIso8601String(),
            'durationDays' => $this->duration_days,
            'totalReturn' => $this->total_return,
            'cagr' => $this->cagr,
            'sharpeRatio' => $this->sharpe_ratio,
            'sortinoRatio' => $this->sortino_ratio,
            'calmarRatio' => $this->calmar_ratio,
            'maxDrawdown' => $this->max_drawdown,
            'recoveryDays' => $this->recovery_days,
            'totalTrades' => $this->total_trades,
            'winningTrades' => $this->winning_trades,
            'losingTrades' => $this->losing_trades,
            'winRate' => $this->win_rate,
            'profitFactor' => $this->profit_factor,
            'expectancy' => $this->expectancy,
            'avgWin' => $this->avg_win,
            'avgLoss' => $this->avg_loss,
            'largestWin' => $this->largest_win,
            'largestLoss' => $this->largest_loss,
            'longestWinStreak' => $this->longest_win_streak,
            'longestLossStreak' => $this->longest_loss_streak,
            'startingCapital' => $this->starting_capital,
            'endingCapital' => $this->ending_capital,
            'totalFees' => $this->total_fees,
            'equityCurve' => $this->equity_curve ?? [],
            'monthlyReturns' => $this->monthly_returns ?? [],
            'trades' => $this->trades ?? [],
            'parameters' => $this->parameters ?? [],
            'status' => $this->status,
            'importSource' => $this->import_source,
            'importedAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

