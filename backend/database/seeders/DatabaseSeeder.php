<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('packages')->insertOrIgnore([
            ['slug' => 'essential-clean', 'name_en' => 'Essential Clean', 'name_mm' => 'အခြေခံသန့်ရှင်းရေး', 'description_en' => 'A careful refresh for everyday shoes.', 'description_mm' => 'နေ့စဉ်စီးဖိနပ်များအတွက် ဂရုတစိုက် သန့်ရှင်းပေးခြင်း။', 'price_ks' => 15000, 'is_active' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'premium-care', 'name_en' => 'Premium Care', 'name_mm' => 'အထူးဂရုစိုက်မှု', 'description_en' => 'Deeper care for pairs that need extra attention.', 'description_mm' => 'ပိုမိုဂရုစိုက်ရန်လိုသော ဖိနပ်များအတွက် အထူးဝန်ဆောင်မှု။', 'price_ks' => 25000, 'is_active' => true, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'full-restore', 'name_en' => 'Full Restore', 'name_mm' => 'အပြည့်အစုံ ပြန်လည်ပြုပြင်ခြင်း', 'description_en' => 'Complete care for worn and well-loved shoes.', 'description_mm' => 'စီးထားပြီး ပျက်စီးနေသောဖိနပ်များအတွက် အပြည့်အစုံဂရုစိုက်မှု။', 'price_ks' => 45000, 'is_active' => true, 'sort_order' => 30, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('shop_settings')->insertOrIgnore([
            'setting_key' => 'pickup_fee_ks',
            'setting_value' => '0',
            'updated_at' => now(),
        ]);
    }
}
