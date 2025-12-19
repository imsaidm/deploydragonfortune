<?php

namespace App\Http\Controllers;

use App\Services\QuantConnectClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BacktestResultController extends Controller
{
    public function index(QuantConnectClient $client): View
    {
        $mask = static function (string $value): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            if (strlen($value) <= 4) {
                return str_repeat('*', strlen($value));
            }
            return str_repeat('*', strlen($value) - 4) . substr($value, -4);
        };

        return view('backtest-result.dashboard', [
            'qc' => [
                'configured' => $client->isConfigured(),
                'base_url' => $client->getBaseUrl(),
                'user_id_masked' => $mask($client->getUserId()),
                'organization_id_masked' => $mask($client->getOrganizationId()),
            ],
        ]);
    }

    public function show(string $file): RedirectResponse
    {
        return redirect()->route('backtest-result.index');
    }
}
