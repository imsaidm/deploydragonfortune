<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MarketAlternative;
use App\Models\MarketSantiment;
use Carbon\Carbon;


class SentimentController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\MarketAlternative::orderBy('api_timestamp', 'desc');

        if ($request->filled('start_date')) {
            $query->whereDate('api_timestamp', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('api_timestamp', '<=', $request->end_date);
        }

        $sentiments = $query->paginate(20)->withQueryString();

        return view('sentiment.index', compact('sentiments'));
    }
    public function fetchHistoricalData()
    {
        // Memanggil REST API dengan limit 730 untuk mendapatkan data 2 tahun terakhir
        $response = Http::get('https://api.alternative.me/fng/?limit=730');

        if ($response->successful()) {
            $data = $response->json()['data']; // Mengambil array 'data' dari JSON

            foreach ($data as $item) {
                // Konversi unixtime dari API ke format datetime MySQL
                $date = Carbon::createFromTimestamp($item['timestamp']);

                // Menyimpan ke database MySQL (Update jika sudah ada tanggal yang sama)
                MarketAlternative::updateOrCreate(
                    ['api_timestamp' => $date],
                    [
                        'value' => $item['value'],
                        'value_classification' => $item['value_classification']
                    ]
                );
            }

            return redirect()->back()->with('success', 'Data historis 2 tahun berhasil ditarik dan disimpan ke database!');
        }

        return redirect()->back()->with('error', 'Gagal menarik data dari API');
    }

    public function santimentIndex(Request $request)
    {
        // 1. Inisialisasi query untuk tanggal unik
        $dateQuery = \App\Models\MarketSantiment::query()
            ->select('api_timestamp')
            ->groupBy('api_timestamp')
            ->orderBy('api_timestamp', 'desc');

        // Filter berdasarkan tanggal
        if ($request->filled('start_date')) {
            $dateQuery->whereDate('api_timestamp', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $dateQuery->whereDate('api_timestamp', '<=', $request->end_date);
        }

        // Ambil list tanggal unik secara terpaginasi (10 hari per halaman)
        $dates = $dateQuery->paginate(10)->withQueryString();

        // 2. Ambil data lengkap untuk tanggal-tanggal yang ada di halaman ini
        $allData = \App\Models\MarketSantiment::whereIn('api_timestamp', $dates->pluck('api_timestamp'))->get();

        // 3. Kelompokkan data: Tanggal -> Slug (BTC/ETH) -> Metric -> Item
        $groupedData = $allData->groupBy(function ($item) {
            return $item->api_timestamp->format('Y-m-d');
        })->map(function ($dateGroup) {
            return $dateGroup->groupBy('slug')->map(function ($slugGroup) {
                return $slugGroup->keyBy('metric');
            });
        });

        return view('sentiment.index_santiment', [
            'dates' => $dates,
            'groupedData' => $groupedData
        ]);
    }

    public function santimentFetchhistory()
    {
        // Set timeout lebih lama karena kita menarik 10 data historis x 730 hari sekaligus
        ini_set('max_execution_time', 120);

        // Query raksasa (Ultimate) untuk 5 Metrik Utama (BTC & ETH) selama 2 tahun
        $query = <<<GQL
        {
          btc_active_address: getMetric(metric: "daily_active_addresses") {
            timeseriesDataJson(slug: "bitcoin", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
          btc_inflow: getMetric(metric: "exchange_inflow") {
            timeseriesDataJson(slug: "bitcoin", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
          btc_outflow: getMetric(metric: "exchange_outflow") {
            timeseriesDataJson(slug: "bitcoin", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
          btc_social: getMetric(metric: "social_volume_total") {
            timeseriesDataJson(slug: "bitcoin", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
          btc_mvrv: getMetric(metric: "mvrv_usd") {
            timeseriesDataJson(slug: "bitcoin", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
          
          eth_active_address: getMetric(metric: "daily_active_addresses") {
            timeseriesDataJson(slug: "ethereum", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
          eth_inflow: getMetric(metric: "exchange_inflow") {
            timeseriesDataJson(slug: "ethereum", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
          eth_outflow: getMetric(metric: "exchange_outflow") {
            timeseriesDataJson(slug: "ethereum", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
          eth_social: getMetric(metric: "social_volume_total") {
            timeseriesDataJson(slug: "ethereum", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
          eth_mvrv: getMetric(metric: "mvrv_usd") {
            timeseriesDataJson(slug: "ethereum", from: "utc_now-730d", to: "utc_now", interval: "1d")
          }
        }
        GQL;

        // Tembak API dengan timeout 90 detik agar tidak putus di tengah jalan
        $response = Http::timeout(90)->withHeaders([
            'Authorization' => 'Apikey ' . env('SANTIMENT_API_KEY'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.santiment.net/graphql', ['query' => $query]);

        if ($response->successful()) {
            $responseData = $response->json()['data'];

            // Peta raksasa untuk menghubungkan 10 alias GraphQL dengan database
            $mapping = [
                'btc_active_address' => ['slug' => 'bitcoin', 'metric' => 'daily_active_addresses'],
                'btc_inflow'         => ['slug' => 'bitcoin', 'metric' => 'exchange_inflow'],
                'btc_outflow'        => ['slug' => 'bitcoin', 'metric' => 'exchange_outflow'],
                'btc_social'         => ['slug' => 'bitcoin', 'metric' => 'social_volume_total'],
                'btc_mvrv'           => ['slug' => 'bitcoin', 'metric' => 'mvrv_usd'],

                'eth_active_address' => ['slug' => 'ethereum', 'metric' => 'daily_active_addresses'],
                'eth_inflow'         => ['slug' => 'ethereum', 'metric' => 'exchange_inflow'],
                'eth_outflow'        => ['slug' => 'ethereum', 'metric' => 'exchange_outflow'],
                'eth_social'         => ['slug' => 'ethereum', 'metric' => 'social_volume_total'],
                'eth_mvrv'           => ['slug' => 'ethereum', 'metric' => 'mvrv_usd'],
            ];

            // Looping cerdas untuk memasukkan ke tabel MySQL
            foreach ($mapping as $alias => $info) {
                if (isset($responseData[$alias]['timeseriesDataJson'])) {
                    $timeseries = $responseData[$alias]['timeseriesDataJson'];

                    if (is_string($timeseries)) {
                        $timeseries = json_decode($timeseries, true);
                    }

                    if (is_array($timeseries)) {
                        foreach ($timeseries as $item) {
                            \App\Models\MarketSantiment::updateOrCreate(
                                [
                                    'api_timestamp' => \Carbon\Carbon::parse($item['datetime']),
                                    'metric'        => $info['metric'],
                                    'slug'          => $info['slug']
                                ],
                                [
                                    'value'         => $item['value']
                                ]
                            );
                        }
                    }
                }
            }

            return redirect()->back()->with('success', 'Kelima Metrik Master (BTC & ETH) selama 2 Tahun Berhasil Disimpan!');
        }

        return redirect()->back()->with('error', 'Gagal mengambil data. Cek API Key atau Rate Limit Anda.');
    }
    public function apiAlternative(Request $request)
    {
        $limit = $request->get('limit', 100);
        $query = \App\Models\MarketAlternative::orderBy('api_timestamp', 'desc');

        if ($limit !== 'all') {
            $query->limit((int)$limit);
        }

        $data = $query->get();

        return response()->json([
            'status' => 'success',
            'source' => 'Alternative.me',
            'count' => count($data),
            'data' => $data
        ]);
    }

    public function apiSantiment(Request $request)
    {
        $limit = $request->get('limit', 100);
        $query = \App\Models\MarketSantiment::orderBy('api_timestamp', 'desc');

        if ($limit !== 'all') {
            $query->limit((int)$limit);
        }

        $data = $query->get();

        return response()->json([
            'status' => 'success',
            'source' => 'Santiment',
            'count' => count($data),
            'data' => $data
        ]);
    }
}
