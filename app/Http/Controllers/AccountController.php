<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AccountController extends ApiController
{
    private function phone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (str_starts_with($digits, '959')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    private function credentials(Request $request, bool $registration): array
    {
        $phone = $this->phone($request->input('phone'));
        $password = (string) $request->input('password', '');
        $name = trim((string) $request->input('fullName', ''));
        $address = trim((string) $request->input('address', ''));
        $fields = [];
        if (! preg_match('/^09\d{7,9}$/', $phone)) {
            $fields['phone'] = 'Enter a valid Myanmar phone number.';
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            $fields['password'] = 'Password must be between 8 and 72 characters.';
        }
        if ($registration && (mb_strlen($name) < 2 || mb_strlen($name) > 120)) {
            $fields['fullName'] = 'Name must be between 2 and 120 characters.';
        }
        if ($registration && mb_strlen($address) > 500) {
            $fields['address'] = 'Address cannot be longer than 500 characters.';
        }
        if ($fields) {
            throw new ApiException('validation_failed', 'Please check the highlighted fields.', 422, $fields);
        }

        return compact('phone', 'password', 'name', 'address');
    }

    private function limit(Request $request, string $action, string $identity, int $maximum): string
    {
        $key = "{$action}|{$request->ip()}|{$identity}";
        if (RateLimiter::tooManyAttempts($key, $maximum)) {
            throw new ApiException('too_many_attempts', 'Too many attempts. Please wait 15 minutes and try again.', 429);
        }
        RateLimiter::hit($key, 900);

        return $key;
    }

    public function register(Request $request)
    {
        $input = $this->credentials($request, true);
        $key = $this->limit($request, 'register', $input['phone'], 5);
        if (Customer::where('phone', $input['phone'])->exists()) {
            throw new ApiException('phone_in_use', 'An account already exists for this phone number.', 409, ['phone' => 'Phone number already registered.']);
        }
        try {
            $customer = Customer::create(['phone' => $input['phone'], 'password_hash' => Hash::make($input['password']), 'full_name' => $input['name'], 'address' => $input['address'], 'last_login_at' => now()]);
        } catch (QueryException) {
            throw new ApiException('phone_in_use', 'An account already exists for this phone number.', 409, ['phone' => 'Phone number already registered.']);
        }
        Auth::guard('customer')->login($customer, $request->boolean('remember', true));
        $request->session()->regenerate();
        RateLimiter::clear($key);

        return $this->success(['customer' => $this->customerPayload($customer), 'csrfToken' => $this->csrf()], 201);
    }

    public function login(Request $request)
    {
        $input = $this->credentials($request, false);
        $key = $this->limit($request, 'login', $input['phone'], 8);
        $customer = Customer::where('phone', $input['phone'])->where('is_active', true)->first();
        if (! $customer || ! Hash::check($input['password'], $customer->password_hash)) {
            throw new ApiException('invalid_credentials', 'Phone number or password is incorrect.', 401);
        }
        if (Hash::needsRehash($customer->password_hash)) {
            $customer->password_hash = Hash::make($input['password']);
        }
        $customer->last_login_at = now();
        $customer->save();
        Auth::guard('customer')->login($customer, $request->boolean('remember', true));
        $request->session()->regenerate();
        RateLimiter::clear($key);

        return $this->success(['customer' => $this->customerPayload($customer), 'csrfToken' => $this->csrf()]);
    }

    public function session()
    {
        $customer = $this->customer(false);

        return $this->success($customer
            ? ['authenticated' => true, 'customer' => $this->customerPayload($customer), 'csrfToken' => $this->csrf()]
            : ['authenticated' => false, 'csrfToken' => $this->csrf()]);
    }

    public function logout(Request $request)
    {
        $this->customer();
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return $this->success(['loggedOut' => true]);
    }

    public function profile(Request $request)
    {
        return $this->success(['customer' => $this->customerPayload($this->customer())]);
    }

    public function updateProfile(Request $request)
    {
        $customer = $this->customer();
        $name = trim((string) $request->input('fullName', ''));
        $address = trim((string) $request->input('address', ''));
        $fields = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $fields['fullName'] = 'Name must be between 2 and 120 characters.';
        }
        if (mb_strlen($address) > 500) {
            $fields['address'] = 'Address cannot be longer than 500 characters.';
        }
        if ($fields) {
            throw new ApiException('validation_failed', 'Please check the highlighted fields.', 422, $fields);
        }
        $customer->update(['full_name' => $name, 'address' => $address]);

        return $this->success(['customer' => $this->customerPayload($customer), 'csrfToken' => $this->csrf()]);
    }
}
