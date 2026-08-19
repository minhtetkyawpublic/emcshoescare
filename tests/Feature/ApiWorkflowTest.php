<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Providers\AppServiceProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    private function photo(string $name = 'shoe.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    public function test_complete_customer_and_admin_workflow(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('data.service', 'EMC Laravel API');
        $session = $this->getJson('/api/auth/session')->assertOk()->assertJsonPath('data.authenticated', false);
        $csrf = $session->json('data.csrfToken');

        $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/auth/register', [
            'phone' => '09123456789', 'password' => 'CustomerPass123!', 'fullName' => 'EMC Customer', 'address' => 'Yangon', 'remember' => true,
        ])->assertCreated()->assertJsonPath('data.customer.phone', '09123456789');
        $this->actingAs(Customer::firstOrFail(), 'customer');

        $package = $this->getJson('/api/packages')->assertOk()->json('data.packages.0');
        $this->assertArrayHasKey('name', $package);
        $this->assertArrayHasKey('description', $package);
        $this->assertArrayNotHasKey('nameEn', $package);
        $this->assertArrayNotHasKey('nameMm', $package);
        $requestId = '123e4567-e89b-42d3-a456-426614174000';
        $created = $this->post('/api/orders', [
            'clientRequestId' => $requestId, 'packageId' => $package['id'], 'fullName' => 'EMC Customer',
            'address' => 'Yangon', 'notes' => 'Repair the sole', 'handover' => 'pickup', 'photos' => [$this->photo()],
        ], ['X-CSRF-TOKEN' => session()->token(), 'Accept' => 'application/json']);
        $created->assertCreated()
            ->assertJsonPath('data.order.status', 'submitted')
            ->assertJsonPath('data.order.photoCount', 1)
            ->assertJsonPath('data.order.totalPriceKs', $package['priceKs']);
        $this->assertArrayNotHasKey('pickupFeeKs', $created->json('data.order'));
        $orderId = $created->json('data.order.id');

        $this->post('/api/orders', [
            'clientRequestId' => $requestId, 'packageId' => $package['id'], 'fullName' => 'EMC Customer',
            'address' => 'Yangon', 'notes' => 'Repair the sole', 'handover' => 'pickup', 'photos' => [$this->photo('retry.png')],
        ], ['X-CSRF-TOKEN' => session()->token(), 'Accept' => 'application/json'])->assertOk()->assertJsonPath('data.replayed', true);

        Admin::create(['username' => 'emcadmin', 'password_hash' => Hash::make('AdminPass123!'), 'display_name' => 'EMC Admin', 'is_active' => true]);
        $adminSession = $this->getJson('/api/admin/auth/session');
        $adminLogin = $this->withHeader('X-CSRF-TOKEN', $adminSession->json('data.csrfToken'))->postJson('/api/admin/auth/login', ['username' => 'emcadmin', 'password' => 'AdminPass123!', 'remember' => true]);
        $adminLogin->assertOk()->assertCookie(Auth::guard('admin')->getRecallerName());
        $this->assertNotNull(Admin::firstOrFail()->remember_token);
        $this->actingAs(Admin::firstOrFail(), 'admin');
        $packageCreated = $this->withHeader('X-CSRF-TOKEN', session()->token())->postJson('/api/admin/packages', [
            'name' => 'Single Content Package', 'description' => 'One admin-managed description.',
            'priceKs' => 18000, 'sortOrder' => 40, 'active' => true,
        ])->assertCreated();
        $managedPackageId = $packageCreated->json('data.id');
        $this->getJson('/api/admin/packages')->assertOk()->assertJsonFragment([
            'id' => $managedPackageId, 'name' => 'Single Content Package', 'description' => 'One admin-managed description.',
        ]);
        $this->withHeader('X-CSRF-TOKEN', session()->token())->putJson("/api/admin/packages/{$managedPackageId}", [
            'name' => 'Admin Wording', 'description' => 'မြန်မာ သို့မဟုတ် English စာသားရေးနိုင်သည်။',
            'priceKs' => 20000, 'sortOrder' => 40, 'active' => true,
        ])->assertOk();
        $this->getJson("/api/admin/orders/{$orderId}")->assertOk()->assertJsonPath('data.order.orderNumber', $created->json('data.order.orderNumber'));
        $this->getJson('/api/admin/orders?status=submitted&perPage=10&page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.pagination.perPage', 10)
            ->assertJsonPath('data.orders.0.id', $orderId);
        $this->getJson('/api/admin/orders?search=0912345')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
        $today = now()->toDateString();
        $this->getJson("/api/admin/reports?from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('data.summary.totalOrders', 1)
            ->assertJsonPath('data.summary.revenueKs', $package['priceKs'])
            ->assertJsonPath('data.byStatus.0.status', 'submitted');

        foreach (['confirmed', 'pickup_scheduled', 'rider_on_way', 'shoes_received', 'repairing', 'ready', 'done'] as $status) {
            $payload = ['status' => $status];
            if ($status !== 'confirmed') {
                $payload['noteEn'] = "Changed to {$status}";
            }
            $this->withHeader('X-CSRF-TOKEN', session()->token())->putJson("/api/admin/orders/{$orderId}/status", $payload)->assertOk()->assertJsonPath('data.order.status', $status);
        }
        $this->getJson('/api/orders')->assertOk()->assertJsonPath('data.orders.0.unreadStatus', true);
        $this->postJson("/api/orders/{$orderId}/seen")->assertOk();
        $this->getJson('/api/orders')->assertOk()->assertJsonPath('data.orders.0.unreadStatus', false);
        $this->get("/api/orders/{$orderId}/photos/1")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_session_cookie_path_is_detected_for_any_public_folder(): void
    {
        $this->assertSame('/clients/emc/', AppServiceProvider::cookiePathFromScriptName('/clients/emc/index.php'));
        $this->assertSame('/', AppServiceProvider::cookiePathFromScriptName('/index.php'));
        $this->assertNull(AppServiceProvider::cookiePathFromScriptName('/artisan'));
    }

    public function test_laravel_serves_the_react_spa_and_keeps_unknown_api_routes_json(): void
    {
        $this->get('/')->assertOk()->assertSee('<div id="root"></div>', false)->assertSee('/build/assets/', false);
        $this->get('/admin')->assertOk()->assertSee('<div id="root"></div>', false);
        $this->get('/admin/orders')->assertOk()->assertSee('<div id="root"></div>', false);
        $this->get('/admin/packages')->assertOk()->assertSee('<div id="root"></div>', false);
        $this->get('/admin/reports')->assertOk()->assertSee('<div id="root"></div>', false);
        $this->getJson('/api/settings')->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->getJson('/api/admin/settings')->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->getJson('/api/not-a-route')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }
}
