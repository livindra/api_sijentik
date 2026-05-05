<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email tidak terdaftar'], 404);
        }

        // OTP 5 digit string (bisa ada nol di depan)
        $otp = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);

        $user->otp = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        Log::info("OTP DB: $otp");

        try {
            Mail::to($user->email)->send(new OtpMail($otp));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Gagal kirim OTP'], 500);
        }

        return response()->json(['message' => 'OTP dikirim'], 200);
    }

    public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp'   => 'required'
    ]);

    $user = User::where('email', $request->email)->first();
    if (!$user) {
        return response()->json(['message' => 'Email tidak terdaftar'], 404);
    }

    // DEBUG LOG
    Log::info("INPUT OTP: ".$request->otp);
    Log::info("DB OTP: ".$user->otp);
    Log::info("EXPIRED AT: ".$user->otp_expires_at);

    if (!$user->otp || !$user->otp_expires_at) {
        return response()->json(['message' => 'OTP belum dibuat'], 400);
    }

    if (now()->greaterThan($user->otp_expires_at)) {
        return response()->json(['message' => 'OTP kedaluwarsa'], 400);
    }

    // 🔥 PENTING: SAMAKAN TIPE JADI STRING
    if ((string)$user->otp !== (string)$request->otp) {
        return response()->json(['message' => 'OTP salah'], 400);
    }

    $user->otp = null;
    $user->otp_expires_at = null;
    $user->save();

    return response()->json(['message' => 'OTP valid'], 200);
}
}

