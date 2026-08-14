<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeOrderPhotos extends Command
{
    protected $signature = 'emc:purge-order-photos {--days=} {--dry-run}';

    protected $description = 'Remove private photos for completed or cancelled orders older than the approved retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('emc.order_photo_retention_days'));
        if ($days < 1) {
            $this->error('Photo retention is disabled. Set --days to an approved positive value.');

            return self::FAILURE;
        }
        $orders = Order::whereIn('status', ['done', 'cancelled'])->where('updated_at', '<', now()->subDays($days))->withCount('photos')->get();
        $count = 0;
        foreach ($orders as $order) {
            $count += $order->photos_count;
            if (! $this->option('dry-run')) {
                Storage::disk('local')->deleteDirectory("order-photos/{$order->storage_key}");
                $order->photos()->delete();
            }
        }
        $this->info(($this->option('dry-run') ? 'Would remove ' : 'Removed ')."{$count} photo(s) from {$orders->count()} order(s).");

        return self::SUCCESS;
    }
}
