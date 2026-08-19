<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\OrderPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends ApiController
{
    public function index(Request $request)
    {
        $this->admin();
        $perPage = min(100, max(10, (int) $request->query('perPage', 25)));
        $page = max(1, (int) $request->query('page', 1));
        $query = Order::query()->withCount('photos')->withMax('history as latest_status_at', 'created_at');
        $this->applyFilters($query, $request);
        $paginator = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
        $orders = $paginator->getCollection()->map(fn ($order) => OrderPresenter::make($order))->values()->all();

        return $this->success([
            'orders' => $orders,
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $statuses = ['submitted', 'confirmed', 'pickup_scheduled', 'rider_on_way', 'shoes_received', 'repairing', 'ready', 'done', 'cancelled'];
        $status = trim((string) $request->query('status', ''));
        if (in_array($status, $statuses, true)) {
            $query->where('status', $status);
        }

        $packageId = filter_var($request->query('packageId'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($packageId !== false) {
            $query->where('package_id', $packageId);
        }

        $handover = trim((string) $request->query('handover', ''));
        if (in_array($handover, ['pickup', 'dropoff'], true)) {
            $query->where('fulfillment_method', $handover);
        }

        if ($from = $this->date($request->query('from'))) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to = $this->date($request->query('to'))) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);
        if ($search !== '') {
            $prefix = addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $nested) use ($prefix) {
                $nested->where('order_number', 'like', $prefix)
                    ->orWhere('customer_phone', 'like', $prefix)
                    ->orWhere('customer_name', 'like', $prefix);
            });
        }
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);

            return $date->toDateString() === $value ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function show(int $order)
    {
        $this->admin();
        $record = Order::find($order);
        if (! $record) {
            throw new ApiException('order_not_found', 'Order not found.', 404);
        }

        return $this->success(['order' => OrderPresenter::make(OrderPresenter::loadDetailed($record), true)]);
    }

    public function updateStatus(Request $request, int $order)
    {
        $admin = $this->admin();
        $status = trim((string) $request->input('status', ''));
        $noteEn = trim((string) $request->input('noteEn', ''));
        $noteMm = trim((string) $request->input('noteMm', ''));
        if (mb_strlen($noteEn) > 1000 || mb_strlen($noteMm) > 1500) {
            throw new ApiException('validation_failed', 'The customer note is too long.', 422);
        }
        DB::transaction(function () use ($order, $status, $noteEn, $noteMm, $admin) {
            $record = Order::whereKey($order)->lockForUpdate()->first();
            if (! $record) {
                throw new ApiException('order_not_found', 'Order not found.', 404);
            }
            if (! in_array($status, OrderPresenter::transitions($record), true)) {
                throw new ApiException('invalid_status_transition', 'That status change is not allowed for this order.', 409);
            }
            $previous = $record->status;
            $record->update(['status' => $status, 'customer_seen_at' => null]);
            OrderStatusHistory::create(['order_id' => $record->id, 'from_status' => $previous, 'to_status' => $status, 'note_en' => $noteEn, 'note_mm' => $noteMm, 'changed_by_admin_id' => $admin->id, 'created_at' => now()]);
        });
        $updated = Order::findOrFail($order);

        return $this->success(['order' => OrderPresenter::make(OrderPresenter::loadDetailed($updated), true), 'csrfToken' => $this->csrf()]);
    }
}
