<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\ServicePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminPackageController extends ApiController
{
    private function payload(ServicePackage $package): array
    {
        return ['id' => $package->id, 'slug' => $package->slug, 'name' => $package->name, 'description' => $package->description, 'priceKs' => $package->price_ks, 'active' => $package->is_active, 'sortOrder' => $package->sort_order];
    }

    private function input(Request $request): array
    {
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $price = filter_var($request->input('priceKs'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000000]]);
        $sortOrder = max(0, min(10000, (int) $request->input('sortOrder', 0)));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 180 || mb_strlen($description) > 1000 || $price === false) {
            throw new ApiException('validation_failed', 'Check the package name, description, and price.', 422);
        }

        return ['name' => $name, 'description' => $description, 'price_ks' => $price, 'is_active' => $request->boolean('active'), 'sort_order' => $sortOrder];
    }

    public function index()
    {
        $this->admin();

        return $this->success(['packages' => ServicePackage::orderBy('sort_order')->orderBy('id')->get()->map(fn ($package) => $this->payload($package))->all()]);
    }

    public function store(Request $request)
    {
        $this->admin();
        $input = $this->input($request);
        $input['slug'] = Str::limit(Str::slug($input['name']) ?: 'package', 64, '').'-'.bin2hex(random_bytes(3));
        $package = ServicePackage::create($input);

        return $this->success(['id' => $package->id, 'csrfToken' => $this->csrf()], 201);
    }

    public function update(Request $request, int $package)
    {
        $this->admin();
        $record = ServicePackage::find($package);
        if (! $record) {
            throw new ApiException('package_not_found', 'Package not found.', 404);
        }
        $record->update($this->input($request));

        return $this->success(['updated' => true, 'csrfToken' => $this->csrf()]);
    }

    public function destroy(int $package)
    {
        $this->admin();
        ServicePackage::whereKey($package)->update(['is_active' => false]);

        return $this->success(['archived' => true, 'csrfToken' => $this->csrf()]);
    }
}
