<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

abstract class ApiController extends Controller
{
    protected function success(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    protected function customer(bool $required = true): ?Customer
    {
        $customer = Auth::guard('customer')->user();
        if ((! $customer || ! $customer->is_active) && $required) {
            throw new ApiException('authentication_required', 'Please sign in to continue.', 401);
        }

        return $customer?->is_active ? $customer : null;
    }

    protected function admin(bool $required = true): ?Admin
    {
        $admin = Auth::guard('admin')->user();
        if ((! $admin || ! $admin->is_active) && $required) {
            throw new ApiException('admin_authentication_required', 'Administrator sign-in is required.', 401);
        }

        return $admin?->is_active ? $admin : null;
    }

    protected function customerPayload(Customer $customer): array
    {
        return ['id' => $customer->id, 'phone' => $customer->phone, 'fullName' => $customer->full_name, 'address' => $customer->address];
    }

    protected function adminPayload(Admin $admin): array
    {
        return ['id' => $admin->id, 'username' => $admin->username, 'displayName' => $admin->display_name];
    }

    protected function csrf(): string
    {
        return csrf_token();
    }
}
