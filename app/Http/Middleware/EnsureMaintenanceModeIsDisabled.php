<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMaintenanceModeIsDisabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request) || ! SystemSetting::maintenanceModeEnabled()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Dragon Fortune is temporarily unavailable for maintenance.',
                'status' => 'maintenance',
            ], Response::HTTP_SERVICE_UNAVAILABLE)
                ->header('Retry-After', (string) config('maintenance.retry_after', 3600));
        }

        return response()
            ->view('maintenance', [], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Retry-After', (string) config('maintenance.retry_after', 3600));
    }

    private function shouldBypass(Request $request): bool
    {
        return $request->is('up')
            || $request->is('favicon.ico')
            || $request->is('robots.txt')
            || $request->is('build/*');
    }
}
