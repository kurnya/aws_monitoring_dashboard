<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class WeatherController extends Controller
{
    public function index()
    {
        return view('weather');
    }
    public function latest()
    {
        $url = "http://202.90.199.132/aws-new/data/station/latest/3000000011";

        try {
            $response = Http::timeout(10)->get($url);
            if (!$response->successful()) {
                return response()->json(['error' => 'Gagal mengambil data dari BMKG'], 502);
            }

            $data = $response->json();
            $stats = DB::table('aws_stat')
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('
                MAX(temp) as max_temp, MIN(temp) as min_temp, AVG(temp) as avg_temp,
                MAX(rh) as max_rh, MIN(rh) as min_rh, AVG(rh) as avg_rh,
                MAX(windspeed) as max_windspeed, MIN(windspeed) as min_windspeed, AVG(windspeed) as avg_windspeed,
                MAX(winddir) as max_winddir, MIN(winddir) as min_winddir, AVG(winddir) as avg_winddir,
                MAX(pressure) as max_pressure, MIN(pressure) as min_pressure, AVG(pressure) as avg_pressure,
                MAX(rain) as max_rain, MIN(rain) as min_rain, AVG(rain) as avg_rain,
                MAX(solrad) as max_solrad, MIN(solrad) as min_solrad, AVG(solrad) as avg_solrad,
                MAX(netrad) as max_netrad, MIN(netrad) as min_netrad, AVG(netrad) as avg_netrad,
                MAX(watertemp) as max_watertemp, MIN(watertemp) as min_watertemp, AVG(watertemp) as avg_watertemp,
                MAX(waterlevel) as max_waterlevel, MIN(waterlevel) as min_waterlevel, AVG(waterlevel) as avg_waterlevel
            ')->first();

            $data['hist'] = $stats;

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function historical()
{
    return view('historical');
}

public function getHistoricalData(Request $request)
{
    $tanggal = $request->query('tanggal', now('Asia/Jakarta')->toDateString());

    $data = DB::table('aws_stat')
        ->select(
            'created_at', 
            'windspeed', 'winddir', 'temp', 'rh', 'pressure',
            'rain', 'solrad', 'netrad', 'watertemp', 'waterlevel'
        )
        ->whereDate('created_at', $tanggal)
        ->orderBy('created_at', 'asc')
        ->get();

    return response()->json($data);
}

}


