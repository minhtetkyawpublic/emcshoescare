<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;

class PushSubscriptionController extends ApiController
{
    public function publicKey(WebPushService $push)
    {
        return $this->success(['enabled' => $push->configured(), 'publicKey' => $push->publicKey()]);
    }

    public function storeCustomer(Request $request)
    {
        return $this->store($request, $this->customer()->id, null);
    }

    public function storeAdmin(Request $request)
    {
        return $this->store($request, null, $this->admin()->id);
    }

    public function destroyCustomer(Request $request)
    {
        return $this->destroy($request, $this->customer()->id, null);
    }

    public function destroyAdmin(Request $request)
    {
        return $this->destroy($request, null, $this->admin()->id);
    }

    private function store(Request $request, ?int $customerId, ?int $adminId)
    {
        $values = $this->validated($request);
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $values['endpoint']), 'customer_id' => $customerId, 'admin_id' => $adminId],
            $values,
        );

        return $this->success(['subscribed' => true, 'csrfToken' => $this->csrf()]);
    }

    private function destroy(Request $request, ?int $customerId, ?int $adminId)
    {
        $endpoint = trim((string) $request->input('endpoint', ''));
        if ($endpoint !== '') {
            PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))
                ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
                ->when($adminId, fn ($query) => $query->where('admin_id', $adminId))
                ->delete();
        }

        return $this->success(['subscribed' => false, 'csrfToken' => $this->csrf()]);
    }

    private function validated(Request $request): array
    {
        $endpoint = trim((string) $request->input('endpoint', ''));
        $publicKey = trim((string) $request->input('keys.p256dh', ''));
        $authToken = trim((string) $request->input('keys.auth', ''));
        $encoding = trim((string) $request->input('contentEncoding', 'aes128gcm'));
        if (mb_strlen($endpoint) > 2048 || ! filter_var($endpoint, FILTER_VALIDATE_URL) || ! str_starts_with($endpoint, 'https://')) {
            throw new ApiException('invalid_push_subscription', 'The notification subscription is invalid.', 422);
        }
        if (! preg_match('/^[A-Za-z0-9_-]{20,200}$/', $publicKey) || ! preg_match('/^[A-Za-z0-9_-]{8,100}$/', $authToken)) {
            throw new ApiException('invalid_push_subscription', 'The notification subscription keys are invalid.', 422);
        }
        if (! in_array($encoding, ['aes128gcm', 'aesgcm'], true)) {
            $encoding = 'aes128gcm';
        }

        return [
            'endpoint' => $endpoint,
            'public_key' => $publicKey,
            'auth_token' => $authToken,
            'content_encoding' => $encoding,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'last_used_at' => now(),
        ];
    }
}
