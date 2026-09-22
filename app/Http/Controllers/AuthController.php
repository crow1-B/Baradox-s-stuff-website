<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // One message for every failed attempt, so the page never says whether a username exists.
    private const FAILED = 'Wrong username or password.';

    public function showLoginForm()
    {
        return view('loginpage');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ], [
            'required' => 'Enter both your username and password.',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');
        if (Auth::attempt(['username' => $username, 'password' => $password])) {
            $request->session()->regenerate();
            return $this->signedIn($request);
        }

        return $this->failed($request, self::FAILED);
    }

    // The Guest button
    public function guest(Request $request)
    {
        $guest = User::where('is_preview', true)->first();
        if ($guest === null) {
            return $this->failed($request, 'The Guest account has not been set up.');
        }

        Auth::login($guest);
        $request->session()->regenerate();
        return $this->signedIn($request);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    
    private function signedIn(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('homepage')]);
        }
        return redirect()->route('homepage');
    }

    private function failed(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['login' => [$message]]], 422);
        }
        return back()->withErrors(['login' => $message])->onlyInput('username');
    }
}
