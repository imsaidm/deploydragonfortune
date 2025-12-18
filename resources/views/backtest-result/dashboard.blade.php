@extends('layouts.app')

@section('title', 'Backtest Result | DragonFortune')

@section('content')
    <div class="d-flex flex-column h-100 gap-3">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Backtest Result</h1>
                    </div>
                    <p class="mb-0 text-secondary">
                        List file dari <span class="font-monospace">{{ $directory }}</span> (disk: <span class="font-monospace">{{ config('backtest_results.disk') }}</span>) — klik untuk baca isi.
                    </p>
                </div>
            </div>
        </div>

        <div class="df-panel p-4">
            @if ($files->isEmpty())
                <div class="text-secondary">Belum ada file di folder ini.</div>
            @else
                <div class="list-group">
                    @foreach ($files as $file)
                        <a href="{{ route('backtest-result.show', ['file' => $file]) }}"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span class="font-monospace">{{ $file }}</span>
                            <span class="text-secondary small">Open</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
