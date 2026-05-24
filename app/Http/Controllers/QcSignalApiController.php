<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessQcPriceNotificationJob;
use App\Jobs\SendQcPriceNotificationEventJob;
use App\Models\QcMethod;
use App\Models\QcSignal;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

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

        return $this->forceExitResponse($methodId, $afterId);
    }

    public function forceExitByIdMethods(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $validated = $request->validate([
            'id_methods' => ['required', 'integer', 'min:1'],
        ]);

        $methodId = (int) $validated['id_methods'];

        return $this->forceExitResponse($methodId, 0);
    }

    private function forceExitResponse(int $methodId, int $afterId): JsonResponse
    {
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

    public function updateNotificationThresholds(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $validated = $request->validate([
            'id_methods' => ['required_without:method_id', 'integer', 'min:1'],
            'method_id' => ['required_without:id_methods', 'integer', 'min:1'],
            'percentage_up' => ['required', 'numeric', 'min:0', 'max:100'],
            'percentage_down' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $methodId = $this->methodIdFromPayload($validated);

        $method = DB::connection('methods')->transaction(function () use ($methodId, $validated) {
            $method = QcMethod::query()->lockForUpdate()->find($methodId);

            if (! $method) {
                return null;
            }

            $method->notify_up_percentage = (float) $validated['percentage_up'];
            $method->notify_down_percentage = (float) $validated['percentage_down'];
            $method->save();

            return $method;
        });

        if (! $method) {
            return response()->json([
                'success' => false,
                'method_id' => $methodId,
                'message' => 'Method not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'method_id' => $methodId,
            'percentage_up' => (float) $method->notify_up_percentage,
            'percentage_down' => (float) $method->notify_down_percentage,
            'message' => 'Notification thresholds updated.',
        ]);
    }

    public function dispatchPriceNotification(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        if ($request->has('level_percentage') || $request->has('event_type')) {
            return $this->dispatchQcPriceNotificationEvent($request);
        }

        $validated = $request->validate([
            'id_methods' => ['required_without:method_id', 'integer', 'min:1'],
            'method_id' => ['required_without:id_methods', 'integer', 'min:1'],
            'market_price' => ['required_without:price', 'numeric', 'gt:0'],
            'price' => ['required_without:market_price', 'numeric', 'gt:0'],
            'source' => ['nullable', 'string', 'max:50'],
            'occurred_at' => ['nullable', 'date'],
            'send_now' => ['nullable', 'boolean'],
        ]);

        $methodId = $this->methodIdFromPayload($validated);
        $marketPrice = (float) ($validated['market_price'] ?? $validated['price']);
        $source = $validated['source'] ?? 'quantconnect';

        try {
            $job = new ProcessQcPriceNotificationJob(
                $methodId,
                $marketPrice,
                $source,
                $validated['occurred_at'] ?? null
            );

            if ((bool) ($validated['send_now'] ?? false)) {
                Bus::dispatchSync($job);
            } else {
                Bus::dispatch($job);
            }

            return response()->json([
                'success' => true,
                'method_id' => $methodId,
                'market_price' => $marketPrice,
                'queue' => (bool) ($validated['send_now'] ?? false) ? null : 'telegram-price-alerts',
                'mode' => 'market_price_check',
                'message' => (bool) ($validated['send_now'] ?? false)
                    ? 'Price notification check processed.'
                    : 'Price notification check queued.',
            ], (bool) ($validated['send_now'] ?? false) ? 200 : 202);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'method_id' => $methodId,
                'message' => 'Failed to queue price notification check: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function dispatchQcPriceNotificationEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_methods' => ['required_without:method_id', 'integer', 'min:1'],
            'method_id' => ['required_without:id_methods', 'integer', 'min:1'],
            'event_type' => ['nullable', 'string', 'max:50'],
            'direction' => ['required', 'string', 'in:up,down'],
            'level_percentage' => ['required', 'numeric', 'min:-1000', 'max:1000'],
            'step_percentage' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'entry_price' => ['nullable', 'numeric', 'gt:0'],
            'market_price' => ['required_without:price', 'numeric', 'gt:0'],
            'price' => ['required_without:market_price', 'numeric', 'gt:0'],
            'movement_percentage' => ['nullable', 'numeric', 'min:-1000', 'max:1000'],
            'source' => ['nullable', 'string', 'max:50'],
            'event_uid' => ['nullable', 'string', 'max:100'],
            'external_event_id' => ['nullable', 'string', 'max:100'],
            'occurred_at' => ['nullable', 'date'],
            'send_now' => ['nullable', 'boolean'],
        ]);

        $methodId = $this->methodIdFromPayload($validated);
        $marketPrice = (float) ($validated['market_price'] ?? $validated['price']);

        try {
            $job = new SendQcPriceNotificationEventJob(
                methodId: $methodId,
                marketPrice: $marketPrice,
                direction: $validated['direction'],
                levelPercentage: (float) $validated['level_percentage'],
                qcSignalId: null,
                eventType: $validated['event_type'] ?? 'qc_price_event',
                stepPercentage: isset($validated['step_percentage']) ? (float) $validated['step_percentage'] : null,
                entryPrice: isset($validated['entry_price']) ? (float) $validated['entry_price'] : null,
                movementPercentage: isset($validated['movement_percentage']) ? (float) $validated['movement_percentage'] : null,
                source: $validated['source'] ?? 'quantconnect',
                occurredAt: $validated['occurred_at'] ?? null,
                eventUid: $validated['event_uid'] ?? $validated['external_event_id'] ?? null
            );

            if ((bool) ($validated['send_now'] ?? false)) {
                Bus::dispatchSync($job);
            } else {
                Bus::dispatch($job);
            }

            return response()->json([
                'success' => true,
                'method_id' => $methodId,
                'event_type' => $validated['event_type'] ?? 'qc_price_event',
                'direction' => $validated['direction'],
                'level_percentage' => (float) $validated['level_percentage'],
                'market_price' => $marketPrice,
                'queue' => (bool) ($validated['send_now'] ?? false) ? null : 'telegram-price-alerts',
                'mode' => 'qc_event',
                'message' => (bool) ($validated['send_now'] ?? false)
                    ? 'QC price notification event processed.'
                    : 'QC price notification event queued.',
            ], (bool) ($validated['send_now'] ?? false) ? 200 : 202);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'method_id' => $methodId,
                'message' => 'Failed to process QC price notification event: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function methodIdFromPayload(array $payload): int
    {
        return (int) ($payload['id_methods'] ?? $payload['method_id']);
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
