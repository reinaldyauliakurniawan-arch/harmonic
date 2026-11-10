<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Onboarding;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:190', 'password' => 'required|string|max:200']);
        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password'], 'anonymized_at' => null], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => "That email and password don't match an account."]);
        }
        $request->session()->regenerate();
        $request->session()->put('auth_at', now()->timestamp);
        $user = $request->user();
        $flag = strtolower((string) config('app.super_admin_email'));
        if ($flag !== '' && $user->email === $flag && ! $user->is_super_admin) {
            $user->forceFill(['is_super_admin' => true])->save();
        }

        return redirect()->intended('/home');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function showSignup()
    {
        return view('auth.signup');
    }

    public function signup(Request $request, Onboarding $onboarding)
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'organization' => 'required|string|min:2|max:120',
        ], ['email.unique' => 'An account with this email already exists. Sign in instead.']);

        $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
        $onboarding->createOrganization($data['organization'], 'pending', 'self', $user);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/home');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::defaults()]]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['password' => 'This reset link has expired or was already used. Ask your admin for a new one.']);
        }

        return redirect('/login')->with('status', 'Password changed. Sign in with the new one.');
    }

    public function forgot()
    {
        return view('auth.forgot');
    }
}
