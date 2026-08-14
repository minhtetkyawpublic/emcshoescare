<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\OrderPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends ApiController
{
    public function index()
    {
        $this->admin();
        $orders = Order::withCount('photos')->withMax('history as latest_status_at', 'created_at')->latest()->orderByDesc('id')->limit(250)->get()->map(fn ($order) => OrderPresenter::make($order))->all();

        return $this->success(['orders' => $orders]);
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
        if (($noteEn === '' && $noteMm === '') || mb_strlen($noteEn) > 1000 || mb_strlen($noteMm) > 1500) {
            throw new ApiException('status_note_required', 'Add an English or Myanmar note for the customer.', 422);
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
            $record->update(['status' => $status]);
            OrderStatusHistory::create(['order_id' => $record->id, 'from_status' => $previous, 'to_status' => $status, 'note_en' => $noteEn, 'note_mm' => $noteMm, 'changed_by_admin_id' => $admin->id, 'created_at' => now()]);
        });
        $updated = Order::findOrFail($order);

        return $this->success(['order' => OrderPresenter::make(OrderPresenter::loadDetailed($updated), true), 'csrfToken' => $this->csrf()]);
    }
}
