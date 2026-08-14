<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AdminAuthController extends ApiController
{
    public function login(Request $request)
    {
        $username = strtolower(trim((string) $request->input('username', '')));
        $password = (string) $request->input('password', '');
        if (! preg_match('/^[a-z0-9_.-]{3,50}$/', $username) || strlen($password) < 10 || strlen($password) > 72) {
            throw new ApiException('invalid_admin_credentials', 'Username or password is incorrect.', 401);
        }
        $key = "admin_login|{$request->ip()}|{$username}";
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw new ApiException('too_many_attempts', 'Too many attempts. Please wait 15 minutes and try again.', 429);
        }
        RateLimiter::hit($key, 900);
        $admin = Admin::where('username', $username)->where('is_active', true)->first();
        if (! $admin || ! Hash::check($password, $admin->password_hash)) {
            throw new ApiException('invalid_admin_credentials', 'Username or password is incorrect.', 401);
        }
        if (Hash::needsRehash($admin->password_hash)) {
            $admin->password_hash = Hash::make($password);
        }
        $admin->last_login_at = now();
        $admin->save();
        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        RateLimiter::clear($key);

        return $this->success(['admin' => $this->adminPayload($admin), 'csrfToken' => $this->csrf()]);
    }

    public function session()
    {
        $admin = $this->admin(false);

        return $this->success($admin
            ? ['authenticated' => true, 'admin' => $this->adminPayload($admin), 'csrfToken' => $this->csrf()]
            : ['authenticated' => false, 'csrfToken' => $this->csrf()]);
    }

    public function logout(Request $request)
    {
        $this->admin();
        Auth::guard('admin')->logout();
        $request->session()->regenerateToken();

        return $this->success(['loggedOut' => true]);
    }
}
