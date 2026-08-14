<?php

namespace App\Http\Controllers;

use App\Models\ServicePackage;
use Illuminate\Support\Facades\DB;

class PublicController extends ApiController
{
    public function health()
    {
        DB::select('SELECT 1');

        return $this->success(['service' => 'EMC Laravel API', 'version' => '6.0.0']);
    }

    public function packages()
    {
        $packages = ServicePackage::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->map(fn ($package) => [
            'id' => $package->id, 'slug' => $package->slug, 'nameEn' => $package->name_en, 'nameMm' => $package->name_mm,
            'descriptionEn' => $package->description_en, 'descriptionMm' => $package->description_mm,
            'priceKs' => $package->price_ks, 'sortOrder' => $package->sort_order,
        ])->all();

        return $this->success(['packages' => $packages]);
    }

    public function settings()
    {
        $fee = max(0, (int) (DB::table('shop_settings')->where('setting_key', 'pickup_fee_ks')->value('setting_value') ?? 0));

        return $this->success(['pickupFeeKs' => $fee]);
    }
}
