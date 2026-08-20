<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\OrderStatusHistory;
use App\Models\ServicePackage;
use App\Services\OrderPresenter;
use App\Services\WebPushService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends ApiController
{
    private function findForCustomer(int $id): Order
    {
        $order = Order::whereKey($id)->where('customer_id', $this->customer()->id)->first();
        if (! $order) {
            throw new ApiException('order_not_found', 'Order not found.', 404);
        }

        return $order;
    }

    private function number(): string
    {
        do {
            $number = 'EMC-'.now()->utc()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }

    public function index()
    {
        $orders = Order::where('customer_id', $this->customer()->id)
            ->withCount('photos')->withMax('history as latest_status_at', 'created_at')
            ->latest()->orderByDesc('id')->get()->map(fn ($order) => OrderPresenter::make($order))->all();

        return $this->success(['orders' => $orders]);
    }

    public function show(int $order)
    {
        return $this->success(['order' => OrderPresenter::make(OrderPresenter::loadDetailed($this->findForCustomer($order)), true)]);
    }

    public function seen(int $order)
    {
        $this->findForCustomer($order)->update(['customer_seen_at' => now()]);

        return $this->success(['seen' => true, 'csrfToken' => $this->csrf()]);
    }

    public function store(Request $request, WebPushService $push)
    {
        $customer = $this->customer();
        $requestId = strtolower(trim((string) $request->input('clientRequestId', '')));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $requestId)) {
            $existing = Order::where('customer_id', $customer->id)->where('client_request_id', $requestId)->first();
            if ($existing) {
                return $this->replay($existing, $customer);
            }
        }

        $name = trim((string) $request->input('fullName', ''));
        $address = trim((string) $request->input('address', ''));
        $notes = trim((string) $request->input('notes', ''));
        $handover = (string) $request->input('handover', 'dropoff');
        $packageId = filter_var($request->input('packageId'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $fields = [];
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $requestId)) {
            $fields['clientRequestId'] = 'A valid request identifier is required.';
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $fields['fullName'] = 'Enter a valid name.';
        }
        if (mb_strlen($address) < 3 || mb_strlen($address) > 500) {
            $fields['address'] = 'Enter a valid address.';
        }
        if (mb_strlen($notes) > 2000) {
            $fields['notes'] = 'Notes cannot exceed 2,000 characters.';
        }
        if (! in_array($handover, ['dropoff', 'pickup'], true)) {
            $fields['handover'] = 'Choose a valid handover method.';
        }
        if ($packageId === false) {
            $fields['packageId'] = 'Choose a valid package.';
        }
        if ($fields) {
            throw new ApiException('validation_failed', 'Please check the order details.', 422, $fields);
        }

        $package = ServicePackage::whereKey($packageId)->where('is_active', true)->first();
        if (! $package) {
            throw new ApiException('package_unavailable', 'The selected package is no longer available.', 409);
        }
        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = [$photos];
        }
        if (count($photos) < 1 || count($photos) > 10) {
            throw new ApiException('photo_count_invalid', 'Add between 1 and 10 shoe photos.', 422);
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $validated = [];
        foreach ($photos as $index => $photo) {
            if (! $photo || ! $photo->isValid()) {
                throw new ApiException('photo_upload_failed', 'One of the photos could not be uploaded.', 422);
            }
            if ($photo->getSize() < 1 || $photo->getSize() > config('emc.upload_max_bytes')) {
                throw new ApiException('photo_size_invalid', 'Each photo must be 5 MB or smaller.', 422);
            }
            $mime = (string) $photo->getMimeType();
            if (! isset($allowed[$mime])) {
                throw new ApiException('photo_type_invalid', 'Only JPG, PNG, and WebP photos are accepted.', 422);
            }
            $dimensions = @getimagesize($photo->getRealPath());
            if (! $dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] * $dimensions[1] > 30000000) {
                throw new ApiException('photo_invalid', 'One of the image files is invalid or too large.', 422);
            }
            $validated[] = compact('photo', 'index', 'mime', 'dimensions') + ['extension' => $allowed[$mime]];
        }

        $storageKey = bin2hex(random_bytes(16));
        $directory = "order-photos/{$storageKey}";

        try {
            $order = DB::transaction(function () use ($customer, $package, $requestId, $name, $address, $notes, $handover, $storageKey, $directory, $validated) {
                $order = Order::create([
                    'order_number' => $this->number(), 'client_request_id' => $requestId, 'storage_key' => $storageKey,
                    'customer_id' => $customer->id, 'package_id' => $package->id,
                    'package_name' => $package->name,
                    'package_price_ks' => $package->price_ks,
                    'total_price_ks' => $package->price_ks, 'fulfillment_method' => $handover,
                    'customer_name' => $name, 'customer_phone' => $customer->phone, 'customer_address' => $address,
                    'customer_notes' => $notes, 'status' => 'submitted', 'customer_seen_at' => now(),
                ]);
                OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => null, 'to_status' => 'submitted', 'note_en' => 'Order submitted to EMC.', 'note_mm' => 'အော်ဒါကို EMC သို့ တင်ပြီးပါပြီ။', 'created_at' => now()]);
                foreach ($validated as $item) {
                    $storageName = bin2hex(random_bytes(16)).'.'.$item['extension'];
                    if (! Storage::disk('local')->putFileAs($directory, $item['photo'], $storageName)) {
                        throw new \RuntimeException('A validated order photo could not be stored.');
                    }
                    $original = preg_replace('/[\x00-\x1F\x7F]/u', '', basename($item['photo']->getClientOriginalName())) ?: 'shoe-photo';
                    OrderPhoto::create(['order_id' => $order->id, 'storage_name' => $storageName, 'original_name' => mb_substr($original, 0, 255), 'mime_type' => $item['mime'], 'size_bytes' => $item['photo']->getSize(), 'width_px' => $item['dimensions'][0], 'height_px' => $item['dimensions'][1], 'sort_order' => $item['index'], 'created_at' => now()]);
                }
                $customer->update(['full_name' => $name, 'address' => $address]);

                return $order;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->deleteDirectory($directory);
            if ($exception instanceof QueryException) {
                $existing = Order::where('customer_id', $customer->id)->where('client_request_id', $requestId)->first();
                if ($existing) {
                    return $this->replay($existing, $customer);
                }
            }
            throw $exception;
        }

        $push->notifyAdmins([
            'title' => 'New EMC order',
            'body' => "{$order->order_number} from {$order->customer_name}",
            'url' => './admin/orders',
            'tag' => "admin-order-{$order->id}",
        ]);

        return $this->success(['order' => OrderPresenter::make(OrderPresenter::loadDetailed($order), true), 'customer' => $this->customerPayload($customer), 'csrfToken' => $this->csrf()], 201);
    }

    private function replay(Order $order, $customer)
    {
        return $this->success(['order' => OrderPresenter::make(OrderPresenter::loadDetailed($order), true), 'customer' => $this->customerPayload($customer), 'csrfToken' => $this->csrf(), 'replayed' => true]);
    }

    public function photo(int $order, int $photo)
    {
        $record = Order::find($order);
        if (! $record) {
            throw new ApiException('order_not_found', 'Order not found.', 404);
        }
        $customer = $this->customer(false);
        if ((! $customer || $customer->id !== $record->customer_id) && ! $this->admin(false)) {
            throw new ApiException('order_not_found', 'Order not found.', 404);
        }
        $image = OrderPhoto::whereKey($photo)->where('order_id', $order)->first();
        if (! $image || ! preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $image->storage_name)) {
            throw new ApiException('photo_not_found', 'Photo not found.', 404);
        }
        $path = "order-photos/{$record->storage_key}/{$image->storage_name}";
        if (! Storage::disk('local')->exists($path)) {
            throw new ApiException('photo_not_found', 'Photo not found.', 404);
        }

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => $image->mime_type, 'Content-Disposition' => 'inline; filename="emc-order-photo.'.pathinfo($image->storage_name, PATHINFO_EXTENSION).'"', 'Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache']);
    }
}
