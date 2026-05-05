<?php

namespace App\Http\Controllers;

use App\Models\Laporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LaporanController extends Controller
{
    // =====================================================
    // HELPER: UPLOAD GAMBAR KE PUBLIC
    // =====================================================
    private function uploadImagesToPublic(Request $request, string $folder): array
    {
        $gambarPaths = [];

        if (!$request->hasFile('gambar')) {
            return $gambarPaths;
        }

        $destination = public_path($folder);

        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }

        $files = $request->file('gambar');

        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (!$file) {
                continue;
            }

            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move($destination, $filename);

            // Yang disimpan ke database:
            // laporan/namafile.jpg atau verifikasi/namafile.jpg
            $gambarPaths[] = $folder . '/' . $filename;
        }

        return $gambarPaths;
    }

    // =====================================================
    // HELPER: PARSE GAMBAR DARI DB
    // =====================================================
    private function parseGambar($gambar): array
    {
        if (!$gambar) {
            return [];
        }

        if (is_array($gambar)) {
            return $gambar;
        }

        $decoded = json_decode($gambar, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return [$gambar];
    }

    // =====================================================
    // HELPER: URL GAMBAR
    // Karena gambar kamu disimpan di public/laporan,
    // maka asset('laporan/namafile.jpg') sudah benar.
    // =====================================================
    private function gambarUrls($gambar): array
    {
        return collect($this->parseGambar($gambar))
            ->filter()
            ->map(function ($g) {
                if (str_starts_with($g, 'http')) {
                    return $g;
                }

                $g = ltrim($g, '/');
                $g = str_replace('storage/', '', $g);

                return asset($g);
            })
            ->values()
            ->toArray();
    }

    // =====================================================
    // HELPER: HAPUS GAMBAR
    // =====================================================
    private function deleteImagesFromPublic($gambar): void
    {
        $gambarArray = $this->parseGambar($gambar);

        foreach ($gambarArray as $g) {
            if (!$g) {
                continue;
            }

            $g = ltrim($g, '/');
            $g = str_replace('storage/', '', $g);

            $filePath = public_path($g);

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    // =====================================================
    // HELPER: NORMALISASI STATUS
    // =====================================================
    private function normalizeStatus($status): string
    {
        $status = strtolower(trim((string) $status));

        if (in_array($status, ['diterima', 'disetujui', 'selesai'])) {
            return 'diterima';
        }

        if ($status === 'ditolak') {
            return 'ditolak';
        }

        return 'proses';
    }

    private function statusLabel($status): string
    {
        $status = $this->normalizeStatus($status);

        if ($status === 'diterima') {
            return 'Diterima';
        }

        if ($status === 'ditolak') {
            return 'Ditolak';
        }

        return 'Diproses';
    }

    private function formatTemuan($adaJentik): string
    {
        if ($adaJentik === null) {
            return '-';
        }

        return (bool) $adaJentik ? 'Ada jentik' : 'Tidak ada jentik';
    }

    // =====================================================
    // HELPER: FORMAT RESPONSE LAPORAN
    // Dibuat cocok untuk:
    // - Riwayat kader
    // - Detail riwayat kader
    // - Laporan petugas
    // - Detail verifikasi petugas
    // =====================================================
    private function formatLaporan($item): array
    {
        $status = $this->normalizeStatus($item->status ?? 'proses');
        $gambarUrl = $this->gambarUrls($item->gambar);
        $tanggal = $item->tanggal ?? $item->created_at;

        return [
            'id' => $item->id,
            'user_id' => $item->user_id,

            'judul' => $item->judul ?? '-',

            'ada_jentik' => (bool) $item->ada_jentik,
            'finding' => $this->formatTemuan($item->ada_jentik),

            'tanggal' => $tanggal,
            'date' => $tanggal,

            'latitude' => $item->latitude,
            'longitude' => $item->longitude,

            'alamat' => $item->alamat ?? '-',
            'address' => $item->alamat ?? '-',

            'status' => $status,
            'status_label' => $this->statusLabel($status),

            'catatan_petugas' => $item->catatan_petugas,
            'notes' => $item->catatan_petugas,

            'gambar' => $this->parseGambar($item->gambar),
            'gambar_url' => $gambarUrl,
            'images' => $gambarUrl,

            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }

    // =====================================================
    // TAMBAH LAPORAN
    // =====================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'judul' => 'required|string|max:255',
            'ada_jentik' => 'required|boolean',
            'tanggal' => 'required|date',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'alamat' => 'nullable|string',
            'gambar' => 'nullable',
            'gambar.*' => 'image|mimes:jpg,jpeg,png|max:50048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $gambarPaths = $this->uploadImagesToPublic($request, 'laporan');

            $laporan = Laporan::create([
                'user_id' => $request->user_id,
                'judul' => $request->judul,
                'ada_jentik' => $request->boolean('ada_jentik'),
                'tanggal' => $request->tanggal,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'alamat' => $request->alamat,
                'gambar' => json_encode($gambarPaths),
                'status' => 'proses',
                'catatan_petugas' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil ditambahkan',
                'data' => $this->formatLaporan($laporan),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error menyimpan laporan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan laporan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // =====================================================
    // HISTORY LAPORAN LOGIN VIA AUTH
    // =====================================================
    public function laporanUser()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User belum login atau token tidak valid',
            ], 401);
        }

        $laporan = Laporan::where('user_id', $userId)
            ->latest()
            ->get()
            ->map(function ($item) {
                return $this->formatLaporan($item);
            });

        return response()->json([
            'success' => true,
            'message' => 'History laporan berhasil diambil',
            'data' => $laporan,
        ], 200);
    }

    // =====================================================
    // HISTORY LAPORAN BY USER ID
    // GET /laporan/user/{id}
    // =====================================================
    public function laporanByUser($id)
    {
        $laporan = Laporan::where('user_id', $id)
            ->latest()
            ->get()
            ->map(function ($item) {
                return $this->formatLaporan($item);
            });

        return response()->json([
            'success' => true,
            'message' => 'History laporan berhasil diambil',
            'data' => $laporan,
        ], 200);
    }

    public function laporanKader($id)
    {
        return $this->laporanByUser($id);
    }

    // =====================================================
    // LIST SEMUA LAPORAN UNTUK PETUGAS
    // GET /laporan
    // =====================================================
    public function index()
    {
        $laporan = Laporan::latest()
            ->get()
            ->map(function ($item) {
                return $this->formatLaporan($item);
            });

        return response()->json([
            'success' => true,
            'message' => 'Data laporan berhasil diambil',
            'data' => $laporan,
        ], 200);
    }

    // =====================================================
    // DETAIL LAPORAN
    // GET /laporan/{id}
    // =====================================================
    public function show($id)
    {
        $laporan = Laporan::find($id);

        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail laporan berhasil diambil',
            'data' => $this->formatLaporan($laporan),
        ], 200);
    }

    // =====================================================
    // DETAIL LAPORAN
    // GET /laporan/detail/{id}
    // =====================================================
    public function detailLaporan($id)
    {
        return $this->show($id);
    }

    // =====================================================
    // UPDATE STATUS LAPORAN
    // PATCH /laporan/{id}/status
    // =====================================================
    public function updateStatus(Request $request, $id)
    {
        $laporan = Laporan::find($id);

        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:proses,diterima,ditolak',
            'catatan_petugas' => 'nullable|string',
            'gambar' => 'nullable',
            'gambar.*' => 'image|mimes:jpg,jpeg,png|max:50048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $gambarPaths = $this->uploadImagesToPublic($request, 'verifikasi');
            $existingImages = $this->parseGambar($laporan->gambar);

            $laporan->status = $this->normalizeStatus($request->status);

            if ($request->has('catatan_petugas')) {
                $laporan->catatan_petugas = $request->catatan_petugas;
            }

            if (!empty($gambarPaths)) {
                $laporan->gambar = json_encode(array_merge($existingImages, $gambarPaths));
            }

            $laporan->save();

            return response()->json([
                'success' => true,
                'message' => 'Status laporan berhasil diupdate',
                'data' => $this->formatLaporan($laporan),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error update status laporan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat update status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // =====================================================
    // HAPUS LAPORAN
    // DELETE /laporan/{id}
    // =====================================================
    public function destroy($id)
    {
        $laporan = Laporan::find($id);

        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan',
            ], 404);
        }

        try {
            $this->deleteImagesFromPublic($laporan->gambar);

            $laporan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error hapus laporan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus laporan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // =====================================================
    // DASHBOARD KADER
    // GET /dashboard/{id}
    // GET /dashboard/kader/{id}
    // =====================================================
    public function dashboardKader($userId)
    {
        $baseQuery = Laporan::where('user_id', $userId);

        $totalLaporan = (clone $baseQuery)->count();

        $menunggu = (clone $baseQuery)
            ->whereRaw('LOWER(TRIM(status)) IN (?, ?, ?)', [
                'proses',
                'menunggu',
                'diproses',
            ])
            ->count();

        $disetujui = (clone $baseQuery)
            ->whereRaw('LOWER(TRIM(status)) IN (?, ?, ?)', [
                'diterima',
                'disetujui',
                'selesai',
            ])
            ->count();

        $ditolak = (clone $baseQuery)
            ->whereRaw('LOWER(TRIM(status)) = ?', [
                'ditolak',
            ])
            ->count();

        $persentaseDisetujui = $totalLaporan > 0
            ? round(($disetujui / $totalLaporan) * 100)
            : 0;

        $recentReports = Laporan::where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return $this->formatLaporan($item);
            });

        return response()->json([
            'success' => true,
            'message' => 'Dashboard kader berhasil diambil',
            'stats' => [
                'total_laporan' => $totalLaporan,
                'menunggu' => $menunggu,
                'disetujui' => $disetujui,
                'ditolak' => $ditolak,
                'persentase_disetujui' => $persentaseDisetujui,
            ],
            'recentReports' => $recentReports,
        ], 200);
    }
}