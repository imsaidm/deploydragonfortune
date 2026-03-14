<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DataController extends Controller
{
    public function getStatus()
    {
        return [
            'server' => 'Quantwaru API',
            'status' => 'running'
        ];
    }

    public function getData(Request $request)
    {

        // GET /api/getdata?table=cq_exchange_inflow_cdd&sort_by=date&sort_order=desc

        $table = $request->query('table');
        $sortBy = $request->query('sort_by');
        $sortOrder = $request->query('sort_order', 'asc');
        $limit = $request->query('limit', 10);
        
        $hiddenColumns = [
            'id', 
            'password', 
            'remember_token', 
            'api_token', 
            'secret_key', 
            'deleted_at',
            'email_verified_at'
        ];

        if (!$table) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter "table" wajib diisi.'
            ], 400);
        }

        if (!Schema::hasTable($table)) {
            return response()->json([
                'status' => 'error',
                'message' => "Tabel '$table' tidak ditemukan di database."
            ], 404);
        }

        // $allowedTables = ['products', 'categories', 'articles'];
        // if (!in_array($table, $allowedTables)) {
        //     return response()->json(['error' => 'Akses ke tabel ini dilarang'], 403);
        // }
        $cekTable = substr($table, 0, 3);
        $allowedTables = ( $cekTable == "cg_" || $cekTable == "cq_" ?? true );
        if (!$allowedTables) {
             return response()->json([
                'status' => 'error',
                'message' => "Tabel '$table' tidak ditemukan di database."
            ], 403);
        }

        $allColumns = Schema::getColumnListing($table);
        $safeColumns = array_diff($allColumns, $hiddenColumns);

        if (empty($safeColumns)) {
            return response()->json(['status' => 'error', 'message' => 'Tidak ada kolom yang diizinkan untuk diakses.'], 403);
        }

        $query = DB::table($table)->select($safeColumns);
        $filters = $request->except(['table', 'sort_by', 'sort_order', 'limit', 'page']);

        foreach ($filters as $column => $value) {
            if (in_array($column, $safeColumns) && !empty($value)) {
                $query->where($column, 'LIKE', "%$value%");
            }
        }

        if ($sortBy && in_array($sortBy, $safeColumns)) {
            $direction = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sortBy, $direction);
        }

        try {
            $data = $query->paginate($limit);
            $data->appends($request->all());

            return response()->json([
                'status' => 'success',
                'table' => $table,
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

}
