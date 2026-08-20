<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\PushSubscription as PushSubscriptionModel;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function configured(): bool
    {
        return config('emc.web_push.public_key') !== ''
            && config('emc.web_push.private_key') !== ''
            && config('emc.web_push.subject') !== '';
    }

    public function publicKey(): string
    {
        return (string) config('emc.web_push.public_key');
    }

    public function notifyCustomer(int $customerId, array $payload): void
    {
        $this->send(PushSubscriptionModel::where('customer_id', $customerId)->get(), $payload);
    }

    public function notifyAdmins(array $payload): void
    {
        $this->send(PushSubscriptionModel::whereIn('admin_id', Admin::where('is_active', true)->select('id'))->get(), $payload);
    }

    private function send(iterable $subscriptions, array $payload): void
    {
        if (! $this->configured()) {
            return;
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => config('emc.web_push.subject'),
                'publicKey' => config('emc.web_push.public_key'),
                'privateKey' => config('emc.web_push.private_key'),
            ]], [], 5);
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            foreach ($subscriptions as $record) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $record->endpoint,
                    'publicKey' => $record->public_key,
                    'authToken' => $record->auth_token,
                    'contentEncoding' => $record->content_encoding,
                ]), $json, ['TTL' => 86400, 'urgency' => 'high']);
            }

            foreach ($webPush->flush() as $report) {
                $hash = hash('sha256', (string) $report->getRequest()->getUri());
                if ($report->isSubscriptionExpired()) {
                    PushSubscriptionModel::where('endpoint_hash', $hash)->delete();
                } elseif ($report->isSuccess()) {
                    PushSubscriptionModel::where('endpoint_hash', $hash)->update(['last_used_at' => now()]);
                } else {
                    Log::warning('Web Push delivery failed.', ['reason' => $report->getReason()]);
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Web Push could not be sent.', ['exception' => $exception->getMessage()]);
        }
    }
}
