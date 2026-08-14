<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Carbon;

class OrderPresenter
{
    public static function transitions(Order $order): array
    {
        $pickup = $order->fulfillment_method === 'pickup';

        return match ($order->status) {
            'submitted' => ['confirmed', 'cancelled'],
            'confirmed' => $pickup ? ['pickup_scheduled', 'cancelled'] : ['shoes_received', 'cancelled'],
            'pickup_scheduled' => $pickup ? ['rider_on_way', 'cancelled'] : [],
            'rider_on_way' => $pickup ? ['shoes_received', 'cancelled'] : [],
            'shoes_received' => ['repairing', 'cancelled'],
            'repairing' => ['ready', 'cancelled'],
            'ready' => ['done'],
            default => [],
        };
    }

    public static function make(Order $order, bool $detailed = false): array
    {
        $photos = $detailed ? $order->photos : collect();
        $history = $detailed ? $order->history : collect();
        $latestValue = $order->getAttribute('latest_status_at');
        $latest = $latestValue ? Carbon::parse($latestValue) : null;
        $unread = $latest && (! $order->customer_seen_at || $order->customer_seen_at->lt($latest));

        return [
            'id' => $order->id,
            'orderNumber' => $order->order_number,
            'package' => ['id' => $order->package_id, 'nameEn' => $order->package_name_en, 'nameMm' => $order->package_name_mm, 'priceKs' => $order->package_price_ks],
            'pickupFeeKs' => $order->pickup_fee_ks,
            'totalPriceKs' => $order->total_price_ks,
            'handover' => $order->fulfillment_method,
            'customer' => ['name' => $order->customer_name, 'phone' => $order->customer_phone, 'address' => $order->customer_address],
            'notes' => $order->customer_notes,
            'status' => $order->status,
            'unreadStatus' => (bool) $unread,
            'lastStatusAt' => $latest?->toIso8601String(),
            'nextStatuses' => self::transitions($order),
            'history' => $history->map(fn (OrderStatusHistory $entry) => [
                'id' => $entry->id,
                'fromStatus' => $entry->from_status,
                'status' => $entry->to_status,
                'noteEn' => $entry->note_en,
                'noteMm' => $entry->note_mm,
                'changedBy' => $entry->admin?->display_name,
                'createdAt' => $entry->created_at->toIso8601String(),
            ])->values()->all(),
            'photoCount' => (int) ($order->getAttribute('photos_count') ?? $photos->count()),
            'photos' => $photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => "/orders/{$order->id}/photos/{$photo->id}",
                'width' => $photo->width_px,
                'height' => $photo->height_px,
            ])->values()->all(),
            'createdAt' => $order->created_at->toIso8601String(),
            'updatedAt' => $order->updated_at->toIso8601String(),
        ];
    }

    public static function loadDetailed(Order $order): Order
    {
        return $order->load(['photos', 'history.admin'])->loadCount('photos')->loadMax('history as latest_status_at', 'created_at');
    }
}
