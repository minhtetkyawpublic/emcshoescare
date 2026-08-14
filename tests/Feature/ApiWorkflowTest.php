<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Providers\AppServiceProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $requestId = '123e4567-e89b-42d3-a456-426614174000';
        $created = $this->post('/api/orders', [
            'clientRequestId' => $requestId, 'packageId' => $package['id'], 'fullName' => 'EMC Customer',
            'address' => 'Yangon', 'notes' => 'Repair the sole', 'handover' => 'pickup', 'photos' => [$this->photo()],
        ], ['X-CSRF-TOKEN' => session()->token(), 'Accept' => 'application/json']);
        $created->assertCreated()->assertJsonPath('data.order.status', 'submitted')->assertJsonPath('data.order.photoCount', 1);
        $orderId = $created->json('data.order.id');

        $this->post('/api/orders', [
            'clientRequestId' => $requestId, 'packageId' => $package['id'], 'fullName' => 'EMC Customer',
            'address' => 'Yangon', 'notes' => 'Repair the sole', 'handover' => 'pickup', 'photos' => [$this->photo('retry.png')],
        ], ['X-CSRF-TOKEN' => session()->token(), 'Accept' => 'application/json'])->assertOk()->assertJsonPath('data.replayed', true);

        Admin::create(['username' => 'emcadmin', 'password_hash' => Hash::make('AdminPass123!'), 'display_name' => 'EMC Admin', 'is_active' => true]);
        $adminSession = $this->getJson('/api/admin/auth/session');
        $this->withHeader('X-CSRF-TOKEN', $adminSession->json('data.csrfToken'))->postJson('/api/admin/auth/login', ['username' => 'emcadmin', 'password' => 'AdminPass123!'])->assertOk();
        $this->actingAs(Admin::firstOrFail(), 'admin');
        $this->getJson("/api/admin/orders/{$orderId}")->assertOk()->assertJsonPath('data.order.orderNumber', $created->json('data.order.orderNumber'));

        foreach (['confirmed', 'pickup_scheduled', 'rider_on_way', 'shoes_received', 'repairing', 'ready', 'done'] as $status) {
            $this->withHeader('X-CSRF-TOKEN', session()->token())->putJson("/api/admin/orders/{$orderId}/status", ['status' => $status, 'noteEn' => "Changed to {$status}"])->assertOk()->assertJsonPath('data.order.status', $status);
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
        $this->getJson('/api/not-a-route')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }
}
