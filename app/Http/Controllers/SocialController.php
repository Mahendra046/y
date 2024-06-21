<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class SocialController extends Controller
{
    const GOOGLE_TYPE = 'google';

    public function redirect(Request $request)
    {
        // Simpan ID meja dari request ke session
        $idMeja = $request->query('id_meja');
        session(['id_meja' => $idMeja]);

        return Socialite::driver('google')
            ->with(['prompt' => 'consent'])
            ->redirect();
    }

    public function googleCallback()
    {
        try {
            $user = Socialite::driver('google')->stateless()->user();
            Log::info('Received OAuth ID: ' . $user->id);
        } catch (\Exception $e) {
            Log::error('Error during Google OAuth callback: ' . $e->getMessage());
            return redirect('/login')->withErrors(['message' => 'Error logging in with Google. Please try again.']);
        }

        $userExisted = User::where('email', $user->email)->first();

        if ($userExisted) {
            Auth::login($userExisted);
            Log::info('User existed, logged in.');
        } else {
            $newUser = User::create([
                'name' => $user->name,
                'email' => $user->email,
                'password' => Hash::make($user->id),
            ]);

            Auth::login($newUser);
            Log::info('New user created, logged in.');
        }

        return $this->manageRedirect($userExisted);
    }

    public function manageRedirect($userExisted)
    {
        // Dapatkan ID meja dari session
        $idMeja = session('id_meja');

        if ($userExisted) {
            return redirect()->route('user.paket')->with([
                'status' => 'success',
                'message' => 'Selamat datang kembali!',
                'id_meja' => $idMeja
            ]);
        } else {
            return redirect()->route('profil')->with([
                'status' => 'success',
                'message' => 'Selamat datang di CPW!',
                'id_meja' => $idMeja
            ]);
        }
    }

    public function logout(Request $request)
    {
        Session::forget('google_access_token');

        if (Auth::guard('admin')->check()) {
            Auth::guard('admin')->logout();
        }

        if (Auth::guard('user')->check()) {
            Auth::guard('user')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Anda telah berhasil keluar.');
    }
}
