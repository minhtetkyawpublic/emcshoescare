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
        return ['id' => $package->id, 'slug' => $package->slug, 'nameEn' => $package->name_en, 'nameMm' => $package->name_mm, 'descriptionEn' => $package->description_en, 'descriptionMm' => $package->description_mm, 'priceKs' => $package->price_ks, 'active' => $package->is_active, 'sortOrder' => $package->sort_order];
    }

    private function input(Request $request): array
    {
        $nameEn = trim((string) $request->input('nameEn', ''));
        $nameMm = trim((string) $request->input('nameMm', ''));
        $descriptionEn = trim((string) $request->input('descriptionEn', ''));
        $descriptionMm = trim((string) $request->input('descriptionMm', ''));
        $price = filter_var($request->input('priceKs'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000000]]);
        $sortOrder = max(0, min(10000, (int) $request->input('sortOrder', 0)));
        if (mb_strlen($nameEn) < 2 || mb_strlen($nameEn) > 120 || mb_strlen($nameMm) < 2 || mb_strlen($nameMm) > 180 || mb_strlen($descriptionEn) > 500 || mb_strlen($descriptionMm) > 800 || $price === false) {
            throw new ApiException('validation_failed', 'Check the package names, descriptions, and price.', 422);
        }

        return ['name_en' => $nameEn, 'name_mm' => $nameMm, 'description_en' => $descriptionEn, 'description_mm' => $descriptionMm, 'price_ks' => $price, 'is_active' => $request->boolean('active'), 'sort_order' => $sortOrder];
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
        $input['slug'] = Str::limit(Str::slug($input['name_en']) ?: 'package', 64, '').'-'.bin2hex(random_bytes(3));
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
