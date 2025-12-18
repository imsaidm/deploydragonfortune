@extends('layouts.app')

@section('title', "Backtest Result | {$file}")

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
                        <span class="font-monospace">{{ $file }}</span>
                    </p>
                </div>

                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <a class="btn btn-outline-primary d-flex align-items-center gap-2" href="{{ route('backtest-result.index') }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                        Back
                    </a>
                </div>
            </div>
        </div>

        <div class="df-panel p-4">
            <div class="d-flex flex-wrap gap-3 small text-secondary mb-3">
                <div>Disk: <span class="font-monospace">{{ config('backtest_results.disk') }}</span></div>
                <div>Folder: <span class="font-monospace">{{ $directory }}</span></div>
                <div>Size: <span class="font-monospace">{{ number_format($size) }}</span> bytes</div>
                @if ($lastModified)
                    <div>Modified: <span class="font-monospace">{{ \Carbon\Carbon::createFromTimestamp($lastModified)->toDateTimeString() }}</span></div>
                @endif
            </div>

            <pre class="mb-0" style="white-space: pre-wrap; word-break: break-word;">{{ $content }}</pre>
        </div>
    </div>
@endsection
