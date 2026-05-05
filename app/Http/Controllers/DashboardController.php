<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalLaporan = DB::table('laporan')->count();

        $laporanDiproses = DB::table('laporan')
            ->where('status', 'proses')
            ->count();

        $laporanSelesai = DB::table('laporan')
            ->where('status', 'diterima')
            ->count();

        $prosesPercent = $totalLaporan > 0
            ? round(($laporanDiproses / $totalLaporan) * 100)
            : 0;

        $selesaiPercent = $totalLaporan > 0
            ? round(($laporanSelesai / $totalLaporan) * 100)
            : 0;

        $recentReports = DB::table('laporan')
            ->select(
                'id',
                DB::raw("COALESCE(judul, '-') as judul"),
                DB::raw("COALESCE(alamat, '-') as alamat"),
                DB::raw("COALESCE(status, '-') as status"),
                DB::raw("DATE_FORMAT(created_at, '%d-%m-%Y') as tanggal")
            )
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => [
                'totalLaporan' => $totalLaporan,
                'laporanDiproses' => $laporanDiproses,
                'laporanSelesai' => $laporanSelesai,
                'prosesPercent' => $prosesPercent,
                'selesaiPercent' => $selesaiPercent,
            ],
            'recentReports' => $recentReports,
        ]);
    }
    public function dashboardKader($id)
    {
        $userId = $id;

        // Ambil user
        $user = DB::table('users')->where('id', $userId)->first();

        // Kalau user tidak ditemukan
        if (!$user) {
            return response()->json([
                "message" => "User tidak ditemukan",
                "user" => [
                    "name" => "-",
                    "wilayah" => "-"
                ],
                "stats" => [
                    "total_laporan" => 0,
                    "disetujui" => 0,
                    "menunggu" => 0,
                    "ditolak" => 0,
                    "persentase_disetujui" => 0
                ]
            ], 404);
        }

        // Statistik laporan
        $totalLaporan = DB::table('laporan')
            ->where('user_id', $userId)
            ->count();

        $disetujui = DB::table('laporan')
            ->where('user_id', $userId)
            ->where('status', 'diterima')
            ->count();

        $menunggu = DB::table('laporan')
            ->where('user_id', $userId)
            ->where('status', 'proses')
            ->count();

        $ditolak = DB::table('laporan')
            ->where('user_id', $userId)
            ->where('status', 'ditolak')
            ->count();

        $persentase = $totalLaporan > 0
            ? round(($disetujui / $totalLaporan) * 100)
            : 0;

        return response()->json([
            "user" => [
                // pastikan field tidak null
                "name" => $user->name ?? "-",
                "wilayah" => $user->address ?? "-" // cek nama kolom di DB!
            ],
            "stats" => [
                "total_laporan" => $totalLaporan,
                "disetujui" => $disetujui,
                "menunggu" => $menunggu,
                "ditolak" => $ditolak,
                "persentase_disetujui" => $persentase
            ]
        ]);
    }
}