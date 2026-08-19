<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminReportController extends ApiController
{
    public function show(Request $request)
    {
        $this->admin();
        [$from, $to] = $this->period($request);
        $packageId = filter_var($request->query('packageId'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $base = Order::query()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        if ($packageId !== false) {
            $base->where('package_id', $packageId);
        }

        $totals = (clone $base)->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_price_ks), 0) as revenue, COALESCE(AVG(total_price_ks), 0) as average_value')
            ->selectRaw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->first();
        $totalOrders = (int) $totals->order_count;
        $completed = (int) $totals->completed_count;
        $cancelled = (int) $totals->cancelled_count;

        $byStatus = (clone $base)->select('status')->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_price_ks), 0) as revenue')
            ->groupBy('status')->orderByDesc('order_count')->get()->map(fn ($row) => [
                'status' => $row->status, 'orderCount' => (int) $row->order_count, 'revenueKs' => (int) $row->revenue,
            ])->values()->all();

        $byPackage = (clone $base)->select('package_id', 'package_name')->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_price_ks), 0) as revenue')
            ->groupBy('package_id', 'package_name')->orderByDesc('order_count')->limit(20)->get()->map(fn ($row) => [
                'packageId' => (int) $row->package_id, 'packageName' => $row->package_name,
                'orderCount' => (int) $row->order_count, 'revenueKs' => (int) $row->revenue,
            ])->values()->all();

        $dateExpression = DB::connection()->getDriverName() === 'sqlite' ? 'date(created_at)' : 'DATE(created_at)';
        $byDay = (clone $base)->selectRaw("{$dateExpression} as report_date, COUNT(*) as order_count, COALESCE(SUM(total_price_ks), 0) as revenue")
            ->groupBy(DB::raw($dateExpression))->orderBy('report_date')->get()->map(fn ($row) => [
                'date' => $row->report_date, 'orderCount' => (int) $row->order_count, 'revenueKs' => (int) $row->revenue,
            ])->values()->all();

        return $this->success([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'totalOrders' => $totalOrders, 'revenueKs' => (int) $totals->revenue,
                'averageOrderKs' => (int) round((float) $totals->average_value), 'completedOrders' => $completed,
                'activeOrders' => max(0, $totalOrders - $completed - $cancelled), 'cancelledOrders' => $cancelled,
            ],
            'byStatus' => $byStatus, 'byPackage' => $byPackage, 'byDay' => $byDay,
        ]);
    }

    private function period(Request $request): array
    {
        $today = now()->startOfDay();
        $fromInput = $request->query('from');
        $toInput = $request->query('to');
        $to = $this->date($toInput) ?? $today->copy();
        $from = $this->date($fromInput) ?? $to->copy()->subDays(29);
        if ((is_string($fromInput) && $fromInput !== '' && $this->date($fromInput) === null) || (is_string($toInput) && $toInput !== '' && $this->date($toInput) === null)) {
            throw new ApiException('invalid_report_period', 'Enter valid report dates.', 422);
        }
        if ($from->gt($to)) {
            throw new ApiException('invalid_report_period', 'The report start date must be before the end date.', 422);
        }
        if ($from->diffInDays($to) > 365) {
            throw new ApiException('report_period_too_large', 'Choose a report period of 366 days or less.', 422);
        }

        return [$from, $to];
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
}
