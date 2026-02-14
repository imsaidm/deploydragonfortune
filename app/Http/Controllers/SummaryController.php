<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\SummaryService;

class SummaryController extends Controller
{
    private SummaryService $summaryService;

    public function __construct(SummaryService $summaryService)
    {
        $this->summaryService = $summaryService;
    }

    public function index()
    {

        return view('summary.index');
    }

    public function DTable(Request $request)
    {
        $data = $this->summaryService->DTable($request);

        return $data;
    }

    public function getAccount(Request $request)
    {
        $data = $this->summaryService->ExchangeAccount($request);

        return $data;
    }

}
