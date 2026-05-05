<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\StatusAkunMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function register(Request $request)
    {
        try {
            // VALIDATOR
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'address' => 'required|string',
                'rtrw' => 'required|string|max:20',
                'password' => 'required|min:6|confirmed', // requires password_confirmation
            ]);

            if ($validator->fails()) {
                Log::error('Register Error', $validator->errors()->toArray());

                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // CREATE USER
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'address' => $request->address,
                'rtrw' => $request->rtrw,
                'password' => Hash::make($request->password),
                'role' => 'Kader',
                'status' => 'pending'
            ]);

            // KIRIM EMAIL STATUS PENDING (optional)
            try {
                Mail::to($user->email)->send(new StatusAkunMail('pending', $user->name));
            } catch (\Exception $e) {
                // jika gagal kirim email, jangan crash server, log saja
                Log::error('Mail send failed: '.$e->getMessage());
            }

            // RETURN SUCCESS JSON
            return response()->json([
                'success' => true,
                'message' => 'Register berhasil',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'address' => $user->address,
                    'rtrw' => $user->rtrw,
                    'role' => $user->role,
                    'status' => $user->status
                ]
            ], 201);

        } catch (\Exception $e) {
            // TANGKAP SEMUA EXCEPTION, RETURN JSON
            Log::error('Register Exception: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: '.$e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'error' => 'Email tidak terdaftar'
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Password salah'
            ], 401);
        }

        if ($user->status == 'pending') {
            return response()->json([
                'error' => 'Akun anda sedang menunggu persetujuan admin'
            ], 403);
        }

        if ($user->status == 'rejected') {
            return response()->json([
                'error' => 'Akun anda ditolak oleh admin'
            ], 403);
        }

        return response()->json([
            'message' => 'Login berhasil',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'address' => $user->address,
                'rtrw' => $user->rtrw,
                'role' => $user->role,
                'status' => $user->status,
            ],
        ], 200);
    }

    public function pending()
    {
        $users = User::where('status', 'pending')->get();

        return response()->json([
            'data' => $users
        ]);
    }

     public function approve($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->status = 'approved';
            $user->save();

            // Kirim email secara queue
            try {
                Mail::to($user->email)->queue(new StatusAkunMail('approved', $user->name));
            } catch (\Exception $e) {
                Log::error('Mail approve gagal: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Kader berhasil diterima'
            ]);
        } catch (\Exception $e) {
            Log::error('Approve Kader Exception: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menerima kader: '.$e->getMessage()
            ], 500);
        }
    }

    // =========================
    // REJECT KADER
    // =========================
    public function reject($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->status = 'rejected';
            $user->save();

            // Kirim email secara queue
            try {
                Mail::to($user->email)->queue(new StatusAkunMail('rejected', $user->name));
            } catch (\Exception $e) {
                Log::error('Mail reject gagal: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Kader berhasil ditolak'
            ]);
        } catch (\Exception $e) {
            Log::error('Reject Kader Exception: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak kader: '.$e->getMessage()
            ], 500);
        }
    }
    
    public function RequestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'type' => 'required|in:email,password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 422);
        }

        $type = $request->type;

        if ($type === 'email') {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email tidak terdaftar'
                ], 404);
            }

            $otp = rand(10000, 99999);

            $user->otp = $otp;
            $user->otp_expires_at = now()->addMinutes(10);
            $user->save();

            try {
                Mail::to($user->email)->send(new \App\Mail\OtpMail($otp));
            } catch (\Exception $e) {
                Log::error('Gagal mengirim email: ' . $e->getMessage());
                return response()->json([
                    'error' => 'Gagal mengirim email OTP'
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Kode OTP telah dikirim ke email Anda',
            ], 200);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'otp' => 'required|integer',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'error' => 'Email tidak terdaftar'
            ], 404);
        }

        if ($user->otp != $request->otp || now()->greaterThan($user->otp_expires_at)) {
            return response()->json([
                'error' => 'Kode OTP tidak valid atau telah kedaluwarsa'
            ], 400);
        }

        $user->password = bcrypt($request->password);
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password berhasil diperbarui',
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Password berhasil dibuat'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal membuat password'
            ], 500);
        }
    }

    // =========================================
// MENAMPILKAN SEMUA USER
// =========================================
    public function allUsers()
    {
        $users = User::select(
            'id',
            'name',
            'email',
            'address',
            'rtrw',
            'role',
            'status',
            'created_at'
        )->get();

        return response()->json([
            'message' => 'Data user berhasil diambil',
            'data' => $users
        ], 200);
    }


    // =========================================
// MENAMPILKAN SEMUA KADER
// =========================================
    public function kader()
    {
        $users = User::where('role', 'Kader')->get();

        foreach ($users as $user) {
            if ($user->profile_photo) {
                $user->profile_photo = 'storage/' . $user->profile_photo;
            } else {
                $user->profile_photo = null;
            }
        }

        return response()->json([
            'message' => 'Data kader berhasil diambil',
            'data' => $users
        ]);
    }


    // =========================================
// MENAMPILKAN KADER APPROVED
// =========================================
public function kaderApproved()
{
    $users = User::where('role', 'Kader')
        ->where('status', 'approved')
        ->get()
        ->map(function ($user) {
            $user->photo_url = $user->photo
                ? asset('storage/' . $user->photo)
                : null;
            return $user;
        });

    return response()->json([
        'message' => 'Data kader approved',
        'data' => $users
    ]);
}
    // =========================================
// DETAIL USER
// =========================================
    public function detail($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'data' => $user
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->address = $request->address;
        $user->rtrw = $request->rtrw;

        $user->save();

        return response()->json([
            'message' => 'Data berhasil diperbarui',
            'data' => $user
        ]);
    }

    public function updatePhoto(Request $request)
    {

        $request->validate([
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $user = User::find(Auth::id());

        if ($request->hasFile('profile_photo')) {

            $path = $request->file('profile_photo')
                ->store('profile', 'public');

            $user->profile_photo = $path;
            $user->save();
        }

        return response()->json([
            "success" => true,
            "message" => "Foto profil berhasil diupdate",
            "photo_url" => asset('storage/' . $user->profile_photo)
        ]);

    }

    public function profile(Request $request)
    {
        $user = User::find($request->user_id);
    
        if (!$user) {
            return response()->json([
                "message" => "User tidak ditemukan"
            ], 404);
        }
    
        return response()->json([
            "user" => [
                "id" => $user->id,
                "name" => $user->name,
                "email" => $user->email,
                "address" => $user->address,
                "role" => $user->role,
                "profile_photo" => $user->profile_photo,
                "photo_url" => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null
            ]
        ]);
    }
    // ================================
    // UPLOAD FOTO PROFILE
    // ================================*/*

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'user_id' => 'required'
        ]);
    
        $user = User::find($request->user_id);
    
        if (!$user) {
            return response()->json([
                "message" => "User tidak ditemukan"
            ], 404);
        }
    
        if ($request->hasFile('profile_photo')) {
    
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }
    
            $path = $request->file('profile_photo')->store('profile', 'public');
    
            $user->profile_photo = $path;
            $user->save();
        }
    
        return response()->json([
            "success" => true,
            "photo_url" => asset('storage/' . $user->profile_photo)
        ]);
    }
    //delete user

    public function destroy($id)
{
    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'message' => 'User tidak ditemukan'
        ], 404);
    }

    $user->delete();

    return response()->json([
        'message' => 'User berhasil dihapus'
    ]);
}

}