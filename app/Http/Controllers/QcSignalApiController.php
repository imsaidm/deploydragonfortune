<?php

namespace App\Http\Controllers;

use App\Models\QcSignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QcSignalApiController extends Controller
{
    public function forceExit(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $validated = $request->validate([
            'method_id' => ['required', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $methodId = (int) $validated['method_id'];
        $afterId = (int) ($validated['after_id'] ?? 0);

        $query = QcSignal::query()
            ->where('id_method', $methodId)
            ->where('type', 'exit')
            ->where('force_exit', true);

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $signal = $query->orderByDesc('id')->first();

        return response()->json([
            'success' => true,
            'method_id' => $methodId,
            'after_id' => $afterId,
            'has_force_exit' => (bool) $signal,
            'force_exit' => $signal ? 1 : 0,
            'signal' => $signal ? [
                'id' => $signal->id,
                'id_method' => $signal->id_method,
                'datetime' => optional($signal->datetime)->toISOString(),
                'created_at' => optional($signal->created_at)->toISOString(),
                'type' => $signal->type,
                'jenis' => $signal->jenis,
                'market_type' => $signal->market_type,
                'leverage' => (int) ($signal->leverage ?: 1),
                'price_entry' => (float) $signal->price_entry,
                'price_exit' => (float) $signal->price_exit,
                'quantity' => (float) $signal->quantity,
                'force_exit' => (bool) $signal->force_exit,
                'message' => $signal->message,
            ] : null,
        ]);
    }

    private function authorized(Request $request): bool
    {
        $configuredToken = (string) config('services.df_qc_signal_api.token', '');
        if ($configuredToken === '') {
            return true;
        }

        $requestToken = (string) $request->bearerToken();
        if ($requestToken === '') {
            $requestToken = (string) $request->header('X-DF-API-KEY', $request->query('api_key', ''));
        }

        return hash_equals($configuredToken, $requestToken);
    }
}
