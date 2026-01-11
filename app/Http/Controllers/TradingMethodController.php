<?php

namespace App\Http\Controllers;

use App\Models\QcMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TradingMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $methods = QcMethod::orderBy('created_at', 'desc')->get();
            return response()->json(['data' => $methods]);
        }
        return view('trading-methods.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_metode' => 'required|string|max:150|unique:qc_methods,nama_metode',
            'market_type' => 'required|in:SPOT,FUTURES',
            'pair' => 'required|string|max:30',
            'tf' => 'required|string|max:30',
            'exchange' => 'required|string|max:30',
            'qc_url' => 'required|url',
            'qc_project_id' => 'nullable|string|max:100',
            'webhook_token' => 'nullable|string|max:255',
            'api_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'cagr' => 'nullable|numeric',
            'drawdown' => 'nullable|numeric',
            'winrate' => 'nullable|numeric',
            'lossrate' => 'nullable|numeric',
            'prob_sr' => 'nullable|numeric',
            'sharpen_ratio' => 'nullable|numeric',
            'sortino_ratio' => 'nullable|numeric',
            'information_ratio' => 'nullable|numeric',
            'turnover' => 'nullable|numeric',
            'total_orders' => 'nullable|numeric',
            'kpi_extra' => 'nullable|json',
            'risk_settings' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate webhook token if not provided
            if (!$request->webhook_token) {
                $request->merge(['webhook_token' => bin2hex(random_bytes(32))]);
            }

            $method = QcMethod::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Trading method created successfully',
                'data' => $method
            ], 201);
        } catch (\Exception $e) {
            Log::error('Trading Method Creation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create trading method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $method = QcMethod::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $method
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trading method not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $method = QcMethod::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nama_metode' => 'required|string|max:150|unique:qc_methods,nama_metode,' . $id,
            'market_type' => 'required|in:SPOT,FUTURES',
            'pair' => 'required|string|max:30',
            'tf' => 'required|string|max:30',
            'exchange' => 'required|string|max:30',
            'qc_url' => 'required|url',
            'qc_project_id' => 'nullable|string|max:100',
            'webhook_token' => 'nullable|string|max:255',
            'api_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'cagr' => 'nullable|numeric',
            'drawdown' => 'nullable|numeric',
            'winrate' => 'nullable|numeric',
            'lossrate' => 'nullable|numeric',
            'prob_sr' => 'nullable|numeric',
            'sharpen_ratio' => 'nullable|numeric',
            'sortino_ratio' => 'nullable|numeric',
            'information_ratio' => 'nullable|numeric',
            'turnover' => 'nullable|numeric',
            'total_orders' => 'nullable|numeric',
            'kpi_extra' => 'nullable|json',
            'risk_settings' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $method->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Trading method updated successfully',
                'data' => $method
            ]);
        } catch (\Exception $e) {
            Log::error('Trading Method Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update trading method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $method = QcMethod::findOrFail($id);
            $method->delete();

            return response()->json([
                'success' => true,
                'message' => 'Trading method deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Trading Method Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete trading method'
            ], 500);
        }
    }

    /**
     * Toggle active status
     */
    public function toggleActive($id)
    {
        try {
            $method = QcMethod::findOrFail($id);
            $method->is_active = !$method->is_active;
            $method->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'is_active' => $method->is_active
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }

    /**
     * Toggle auto-trade status
     */
    public function toggleAutoTrade($id)
    {
        try {
            $method = QcMethod::findOrFail($id);
            $method->auto_trade_enabled = !$method->auto_trade_enabled;
            $method->save();

            return response()->json([
                'success' => true,
                'message' => 'Auto-trade status updated successfully',
                'auto_trade_enabled' => $method->auto_trade_enabled
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update auto-trade status'
            ], 500);
        }
    }

    /**
     * Export methods to JSON
     */
    public function export(Request $request)
    {
        try {
            $query = QcMethod::query();
            
            // If specific IDs provided, export only those
            if ($request->has('ids')) {
                $ids = is_array($request->ids) ? $request->ids : explode(',', $request->ids);
                $query->whereIn('id', $ids);
            }
            
            $methods = $query->get()->makeVisible(['api_key', 'secret_key']);
            
            $export = [
                'exported_at' => now()->toIso8601String(),
                'total_methods' => $methods->count(),
                'methods' => $methods->toArray()
            ];

            return response()->json($export)
                ->header('Content-Disposition', 'attachment; filename="trading-methods-' . date('Y-m-d-His') . '.json"');
        } catch (\Exception $e) {
            Log::error('Trading Method Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export trading methods'
            ], 500);
        }
    }

    /**
     * Import methods from JSON
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file format',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $data = json_decode($content, true);

            if (!isset($data['methods']) || !is_array($data['methods'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON structure. Expected "methods" array.'
                ], 422);
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($data['methods'] as $methodData) {
                try {
                    // Check if method already exists
                    if (QcMethod::where('nama_metode', $methodData['nama_metode'])->exists()) {
                        $skipped++;
                        $errors[] = "Method '{$methodData['nama_metode']}' already exists";
                        continue;
                    }

                    QcMethod::create($methodData);
                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Failed to import '{$methodData['nama_metode']}': " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Import completed. Imported: {$imported}, Skipped: {$skipped}",
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            Log::error('Trading Method Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to import trading methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
