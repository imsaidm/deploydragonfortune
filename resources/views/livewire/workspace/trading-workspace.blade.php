<div class="d-flex flex-column flex-xl-row gap-3">
    <aside class="df-sidebar text-white p-4 d-flex flex-column gap-4">
        <div>
            <h5 class="fw-semibold text-uppercase small mb-3">Workspace</h5>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-primary">Live</span>
                <small class="text-secondary">Real-time market board</small>
            </div>
        </div>

        <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Watchlist</h6>
                <button class="btn btn-sm btn-outline-light px-3">+
                    Add</button>
            </div>
            <div class="list-group df-scrollbar" style="max-height: 60vh; overflow-y: auto;">
                @foreach ($watchlist as $item)
                    <button
                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center bg-transparent text-white border-0 py-2 {{ $selectedSymbol === $item['symbol'] ? 'active' : '' }}"
                        wire:click="$set('selectedSymbol', '{{ $item['symbol'] }}')">
                        <div class="text-start">
                            <div class="fw-semibold">{{ $item['symbol'] }}</div>
                            <small class="text-secondary">{{ $item['name'] }} · {{ $item['exchange'] }}</small>
                        </div>
                        <span class="badge {{ $selectedSymbol === $item['symbol'] ? 'text-bg-light text-dark' : 'text-bg-dark' }}">
                            View
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="mt-auto border-top border-secondary pt-3 d-grid gap-3">
            <button class="btn btn-outline-light" wire:click="refreshQuote">
                Refresh
            </button>
            <small class="text-secondary">Auto refresh disabled. Use the button to update.</small>
        </div>
    </aside>

    <section class="flex-grow-1 d-flex flex-column gap-3">
        <header class="df-toolbar rounded-4 px-4 py-3 d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div>
                <div class="text-secondary small">Symbol</div>
                <h4 class="mb-0 text-white">{{ $selectedQuote['symbol'] ?? $selectedSymbol }}</h4>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-light">Indicators</button>
                <button class="btn btn-outline-light">Strategy Builder</button>
                <button class="btn btn-primary">Fullscreen</button>
            </div>
        </header>

        <div class="df-panel flex-grow-1 p-3">
            <div class="d-flex flex-column flex-lg-row gap-3 h-100">
                <div class="flex-grow-1 position-relative" x-data="{ symbol: @js($selectedSymbol) }"
                    x-init="window.initTradingWidget($refs.chartContainer, { symbol })"
                    @workspace\:quote-updated.window="window.updateTradingWidget($event.detail)"
                    style="min-height: 520px;">
                    <div x-ref="chartContainer" class="h-100 w-100"></div>
                </div>
                <div class="border-start border-secondary-subtle ps-3 d-flex flex-column gap-3" style="min-width: 240px;">
                    <div>
                        <div class="text-secondary small">Last price</div>
                        <div class="display-6 fw-bold">
                            {{ number_format($selectedQuote['price'] ?? 0, 2) }}
                        </div>
                        <div class="fw-semibold {{ ($selectedQuote['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($selectedQuote['change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($selectedQuote['change'] ?? 0, 2) }}
                            ({{ ($selectedQuote['change_percent'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($selectedQuote['change_percent'] ?? 0, 2) }}%)
                        </div>
                    </div>
                    <div>
                        <div class="text-secondary small">Updated at</div>
                        <p class="mb-0">{{ $selectedQuote['updated_at'] ?? now()->toDateTimeString() }}</p>
                    </div>
                    <div class="mt-auto">
                        <h6 class="text-secondary text-uppercase small">Actions</h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-success">Buy</button>
                            <button class="btn btn-danger">Sell</button>
                            <button class="btn btn-outline-light">Add Alert</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
