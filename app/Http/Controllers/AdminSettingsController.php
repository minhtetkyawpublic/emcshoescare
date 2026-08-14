<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSettingsController extends ApiController
{
    public function show()
    {
        $this->admin();
        $fee = max(0, (int) (DB::table('shop_settings')->where('setting_key', 'pickup_fee_ks')->value('setting_value') ?? 0));

        return $this->success(['pickupFeeKs' => $fee]);
    }

    public function update(Request $request)
    {
        $this->admin();
        $fee = filter_var($request->input('pickupFeeKs'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000000]]);
        if ($fee === false) {
            throw new ApiException('validation_failed', 'Enter a valid pickup fee.', 422);
        }
        DB::table('shop_settings')->updateOrInsert(['setting_key' => 'pickup_fee_ks'], ['setting_value' => (string) $fee, 'updated_at' => now()]);

        return $this->success(['pickupFeeKs' => $fee, 'csrfToken' => $this->csrf()]);
    }
}
