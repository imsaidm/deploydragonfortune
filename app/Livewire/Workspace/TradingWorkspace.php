<?php

namespace App\Livewire\Workspace;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Component;

class TradingWorkspace extends Component
{
    public array $watchlist = [];

    public string $selectedSymbol = 'BTCUSD';

    public array $selectedQuote = [];

    public function mount(): void
    {
        $this->watchlist = [
            ['symbol' => 'BTCUSD', 'name' => 'Bitcoin', 'exchange' => 'Binance'],
            ['symbol' => 'ETHUSD', 'name' => 'Ethereum', 'exchange' => 'Coinbase'],
            ['symbol' => 'SOLUSD', 'name' => 'Solana', 'exchange' => 'Kraken'],
        ];

        $this->loadQuote($this->selectedSymbol);
    }

    public function updatedSelectedSymbol(string $symbol): void
    {
        $this->loadQuote($symbol);
    }

    public function refreshQuote(): void
    {
        $this->loadQuote($this->selectedSymbol, force: true);
    }

    protected function loadQuote(string $symbol, bool $force = false): void
    {
        $endpoint = config('services.market-data.quote_url');

        $payload = [
            'symbol' => $symbol,
            'force' => $force,
            'request_id' => Str::uuid()->toString(),
        ];

        $response = null;

        if ($endpoint) {
            $response = rescue(
                fn () => Http::timeout(5)->acceptJson()->post($endpoint, $payload)->json(),
                report: false
            );
        }

        $data = Arr::wrap($response);

        $this->selectedQuote = array_filter([
            'symbol' => $data['symbol'] ?? $symbol,
            'price' => $data['price'] ?? random_int(25_000, 65_000) / 100,
            'change' => $data['change'] ?? random_int(-300, 300) / 100,
            'change_percent' => $data['change_percent'] ?? random_int(-150, 150) / 100,
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->dispatch('workspace:quote-updated',
            symbol: $this->selectedQuote['symbol'],
            price: $this->selectedQuote['price'],
            changePercent: $this->selectedQuote['change_percent'],
        );
    }

    public function render()
    {
        return view('livewire.workspace.trading-workspace');
    }
}
