<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\DashboardController;

// ================= AUTH =================
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

// ================= USER =================
Route::get('/users', [UserController::class, 'allUsers']);
Route::get('/users/pending', [UserController::class, 'pending']);
Route::get('/users/kader', [UserController::class, 'kader']);
Route::get('/users/kader/approved', [UserController::class, 'kaderApproved']);
Route::get('/users/{id}', [UserController::class, 'detail']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
Route::post('/users/approve/{id}', [UserController::class, 'approve']);
Route::post('/users/reject/{id}', [UserController::class, 'reject']);

Route::get('/profile', [UserController::class, 'profile']);
Route::post('/upload-photo', [UserController::class, 'uploadPhoto']);

// ================= OTP =================
Route::post('/RequestOtp', [UserController::class, 'RequestOtp']);
Route::post('/send-otp', [OtpController::class, 'sendOtp']);
Route::post('/verify-otp', [OtpController::class, 'verifyOtp']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);

// ================= LAPORAN =================

// List semua laporan untuk petugas/admin
Route::get('/laporan', [LaporanController::class, 'index']);

// Tambah laporan dari kader
Route::post('/laporan', [LaporanController::class, 'store']);

// History laporan berdasarkan user id
Route::get('/laporan/user/{id}', [LaporanController::class, 'laporanByUser']);

// History laporan login via auth/token
Route::get('/laporan-user', [LaporanController::class, 'laporanUser']);

// Detail laporan untuk kader dan petugas
Route::get('/laporan/detail/{id}', [LaporanController::class, 'detailLaporan']);
Route::get('/laporan/{id}', [LaporanController::class, 'show']);

// Update status laporan oleh petugas
Route::patch('/laporan/{id}/status', [LaporanController::class, 'updateStatus']);

// Hapus laporan
Route::delete('/laporan/{id}', [LaporanController::class, 'destroy']);

// ================= DASHBOARD =================
Route::get('/dashboard-petugas', [DashboardController::class, 'index']);
Route::get('/dashboard/{id}', [LaporanController::class, 'dashboardKader']);
Route::get('/dashboard/kader/{id}', [LaporanController::class, 'dashboardKader']);

// ================= VERIFIKASI KADER =================
Route::get('/kader/pending', [UserController::class, 'pending']);
Route::post('/kader/approve/{id}', [UserController::class, 'approve']);
Route::post('/kader/reject/{id}', [UserController::class, 'reject']);