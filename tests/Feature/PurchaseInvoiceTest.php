<?php

namespace Tests\Feature;

use App\Models\ConstructionMaterialPurchase;
use App\Models\ExpenseHead;
use App\Models\GasPurchase;
use App\Models\User;
use App\Services\GasPurchaseService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesInvoiceImages;
use Tests\TestCase;

/**
 * Invoice photos on gas and material purchases: attach at entry time or later,
 * compressed and kept on the PRIVATE disk (only reachable via permission-gated
 * routes), and printable as black-and-white A4 - singly or as an audit pack.
 */
class PurchaseInvoiceTest extends TestCase
{
    use MakesInvoiceImages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ExpenseHeadSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(MoneyCorePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function photo(int $w = 3000, int $h = 2000): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('invoice.jpg', $this->makeJpeg($w, $h));
    }

    private function gasForm(array $extra = []): array
    {
        return array_merge([
            'date' => '2026-09-05', 'supplier_name' => 'Bashundhara LP Gas',
            'quantity_kg' => 400, 'unit_price' => 95, 'treasury_medium' => 'CASH',
        ], $extra);
    }

    private function materialForm(array $extra = []): array
    {
        return array_merge([
            'date' => '2026-09-06', 'item_name' => 'Cement', 'quantity' => 50, 'unit' => 'bag',
            'unit_rate' => 560, 'supplier_name' => 'Local Dealer', 'treasury_medium' => 'CASH',
        ], $extra);
    }

    private function postForm(User $user, string $url, array $data)
    {
        return $this->actingAs($user, 'sanctum')->post($url, $data, ['Accept' => 'application/json']);
    }


    /** How many images dompdf really embedded - a broken <img> still yields a valid-looking PDF. */
    private function embeddedImages(string $pdf): int
    {
        return preg_match_all('#/Subtype\s*/Image#', $pdf);
    }

    // --- attach at entry time -----------------------------------------------

    public function test_gas_purchase_with_an_invoice_photo_stores_a_compressed_private_copy(): void
    {
        $upload = $this->photo();

        $response = $this->postForm($this->user('manager'), '/api/gas/purchases', $this->gasForm(['invoice_image' => $upload]));

        $response->assertStatus(201)->assertJson(['has_invoice' => true]);
        $purchase = GasPurchase::first();
        Storage::disk('local')->assertExists($purchase->invoice_file);
        $this->assertStringStartsWith('invoices/gas/', $purchase->invoice_file);
        $this->assertLessThan($upload->getSize() * 0.3, Storage::disk('local')->size($purchase->invoice_file));
        $this->assertEqualsWithDelta(Storage::disk('local')->size($purchase->invoice_file) / 1024, $response->json('invoice_kb'), 0.2);
    }

    public function test_the_invoice_is_optional(): void
    {
        $this->postForm($this->user('manager'), '/api/gas/purchases', $this->gasForm())
            ->assertStatus(201)->assertJson(['has_invoice' => false, 'invoice_kb' => null]);

        $this->assertNull(GasPurchase::first()->invoice_file);
    }

    public function test_material_purchase_accepts_an_invoice_photo_too(): void
    {
        $this->postForm($this->user('manager'), '/api/construction-materials', $this->materialForm(['invoice_image' => $this->photo()]))
            ->assertStatus(201)->assertJson(['has_invoice' => true]);

        $this->assertStringStartsWith('invoices/materials/', ConstructionMaterialPurchase::first()->invoice_file);
    }

    public function test_a_non_image_is_rejected_and_nothing_is_recorded(): void
    {
        $this->postForm($this->user('manager'), '/api/gas/purchases', $this->gasForm([
            'invoice_image' => UploadedFile::fake()->createWithContent('invoice.pdf', '%PDF-1.4 fake'),
        ]))->assertStatus(422)->assertJsonValidationErrors('invoice_image');

        $this->assertSame(0, GasPurchase::count());
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_a_file_over_the_upload_cap_is_rejected(): void
    {
        $this->postForm($this->user('manager'), '/api/gas/purchases', $this->gasForm([
            'invoice_image' => UploadedFile::fake()->create('huge.jpg', 16000, 'image/jpeg'),
        ]))->assertStatus(422)->assertJsonValidationErrors('invoice_image');
    }

    public function test_a_corrupt_image_with_a_jpg_name_is_rejected_by_the_pipeline(): void
    {
        $this->postForm($this->user('manager'), '/api/gas/purchases', $this->gasForm([
            'invoice_image' => UploadedFile::fake()->createWithContent('invoice.jpg', 'not really a jpeg'),
        ]))->assertStatus(422);

        $this->assertSame(0, GasPurchase::count());
    }

    public function test_the_stored_file_is_removed_if_the_purchase_cannot_be_saved(): void
    {
        ExpenseHead::query()->delete(); // makes GasPurchaseService fail *after* the photo is stored

        try {
            app(GasPurchaseService::class)->record($this->gasForm(['invoice_image' => $this->photo(800, 600)]), $this->user('manager')->id);
            $this->fail('expected the service to throw');
        } catch (ModelNotFoundException) {
            $this->assertSame([], Storage::disk('local')->allFiles(), 'orphan invoice file left behind');
        }
    }

    // --- viewing the stored photo -------------------------------------------

    public function test_the_photo_is_served_only_through_an_authenticated_route(): void
    {
        $manager = $this->user('manager');
        $this->postForm($manager, '/api/gas/purchases', $this->gasForm(['invoice_image' => $this->photo(1200, 900)]));
        $id = GasPurchase::first()->id;

        $ok = $this->actingAs($manager, 'sanctum')->get("/api/gas/purchases/{$id}/invoice");
        $ok->assertStatus(200);
        $this->assertSame('image/jpeg', $ok->headers->get('Content-Type'));

        $tenant = $this->user('tenant');
        $this->actingAs($tenant, 'sanctum')->get("/api/gas/purchases/{$id}/invoice")->assertStatus(403);
    }

    public function test_the_photo_is_not_reachable_without_logging_in(): void
    {
        $this->postForm($this->user('manager'), '/api/gas/purchases', $this->gasForm(['invoice_image' => $this->photo(600, 400)]));

        // A fresh guard, as a real anonymous request would have (actingAs persists across calls in a test).
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/gas/purchases/'.GasPurchase::first()->id.'/invoice')->assertStatus(401);
    }

    public function test_viewing_a_purchase_with_no_invoice_is_a_404(): void
    {
        $manager = $this->user('manager');
        $this->postForm($manager, '/api/gas/purchases', $this->gasForm());

        $this->actingAs($manager, 'sanctum')->get('/api/gas/purchases/'.GasPurchase::first()->id.'/invoice')->assertStatus(404);
        $this->actingAs($manager, 'sanctum')->get('/api/gas/purchases/'.GasPurchase::first()->id.'/invoice/print')->assertStatus(404);
    }

    // --- attach / replace later ----------------------------------------------

    public function test_an_invoice_can_be_attached_later_and_replacing_deletes_the_old_file(): void
    {
        $manager = $this->user('manager');
        $this->postForm($manager, '/api/gas/purchases', $this->gasForm());
        $id = GasPurchase::first()->id;

        $this->postForm($manager, "/api/gas/purchases/{$id}/invoice", ['invoice_image' => $this->photo(1000, 800)])
            ->assertStatus(200)->assertJson(['has_invoice' => true]);
        $first = GasPurchase::first()->invoice_file;
        Storage::disk('local')->assertExists($first);

        $this->postForm($manager, "/api/gas/purchases/{$id}/invoice", ['invoice_image' => $this->photo(900, 700)])->assertStatus(200);
        $second = GasPurchase::first()->invoice_file;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_attaching_requires_the_manage_permission(): void
    {
        $manager = $this->user('manager');
        $this->postForm($manager, '/api/gas/purchases', $this->gasForm());

        $this->postForm($this->user('tenant'), '/api/gas/purchases/'.GasPurchase::first()->id.'/invoice', ['invoice_image' => $this->photo(600, 400)])
            ->assertStatus(403);
    }

    public function test_material_purchase_invoice_can_be_attached_and_viewed(): void
    {
        $manager = $this->user('manager');
        $this->postForm($manager, '/api/construction-materials', $this->materialForm());
        $id = ConstructionMaterialPurchase::first()->id;

        $this->postForm($manager, "/api/construction-materials/{$id}/invoice", ['invoice_image' => $this->photo(900, 700)])->assertStatus(200);

        $this->actingAs($manager, 'sanctum')->get("/api/construction-materials/{$id}/invoice")->assertStatus(200);
    }

    // --- black-and-white A4 print ---------------------------------------------

    public function test_a_single_invoice_prints_as_an_a4_pdf(): void
    {
        $manager = $this->user('manager');
        $this->postForm($manager, '/api/gas/purchases', $this->gasForm(['invoice_image' => $this->photo(1600, 1200)]));

        $response = $this->actingAs($manager, 'sanctum')->get('/api/gas/purchases/'.GasPurchase::first()->id.'/invoice/print');

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0(\.0+)?\s+0(\.0+)?\s+595\.\d+\s+841\.\d+\s*\]#', $pdf, 'page must be A4 (595x842pt)');
        $this->assertSame(1, preg_match_all('#/Type\s*/Page(?!s)#', $pdf), 'exactly one page');
        $this->assertSame(1, $this->embeddedImages($pdf), 'the invoice photo must actually be in the PDF, not a broken-image box');
        $this->assertSame([], glob(storage_path('app/invoice-print-tmp/*')) ?: [], 'temp greyscale files must be cleaned up');
    }

    public function test_material_invoice_prints_too(): void
    {
        $manager = $this->user('manager');
        $this->postForm($manager, '/api/construction-materials', $this->materialForm(['invoice_image' => $this->photo(1200, 900)]));

        $this->actingAs($manager, 'sanctum')
            ->get('/api/construction-materials/'.ConstructionMaterialPurchase::first()->id.'/invoice/print')
            ->assertStatus(200);
    }

    // --- audit pack -----------------------------------------------------------------

    public function test_audit_pack_has_a_cover_page_plus_one_page_per_invoice(): void
    {
        $admin = $this->user('admin');
        $this->postForm($admin, '/api/gas/purchases', $this->gasForm(['invoice_image' => $this->photo(1200, 900)]));
        $this->postForm($admin, '/api/construction-materials', $this->materialForm(['invoice_image' => $this->photo(900, 1200)]));
        $this->postForm($admin, '/api/gas/purchases', $this->gasForm(['date' => '2026-09-20'])); // no photo
        $this->postForm($admin, '/api/gas/purchases', $this->gasForm(['date' => '2026-12-01', 'invoice_image' => $this->photo(800, 600)])); // out of range

        $response = $this->actingAs($admin, 'sanctum')->get('/api/invoices/audit-pack?from=2026-09-01&to=2026-09-30');

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame(3, preg_match_all('#/Type\s*/Page(?!s)#', $response->getContent()), 'cover + 2 invoice pages (out-of-range one excluded)');
        $this->assertSame(2, $this->embeddedImages($response->getContent()), 'both photos embedded');
    }

    public function test_audit_pack_can_be_limited_to_one_purchase_type(): void
    {
        $admin = $this->user('admin');
        $this->postForm($admin, '/api/gas/purchases', $this->gasForm(['invoice_image' => $this->photo(800, 600)]));
        $this->postForm($admin, '/api/construction-materials', $this->materialForm(['invoice_image' => $this->photo(800, 600)]));

        $pdf = $this->actingAs($admin, 'sanctum')->get('/api/invoices/audit-pack?from=2026-09-01&to=2026-09-30&type=gas')->getContent();

        $this->assertSame(2, preg_match_all('#/Type\s*/Page(?!s)#', $pdf), 'cover + the one gas invoice');
    }

    public function test_audit_pack_requires_accounting_view_which_managers_do_not_have(): void
    {
        $this->actingAs($this->user('manager'), 'sanctum')->get('/api/invoices/audit-pack?from=2026-09-01&to=2026-09-30')->assertStatus(403);
    }

    public function test_audit_pack_requires_login(): void
    {
        $this->getJson('/api/invoices/audit-pack?from=2026-09-01&to=2026-09-30')->assertStatus(401);
    }

    public function test_audit_pack_validates_the_date_range(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin, 'sanctum')->getJson('/api/invoices/audit-pack')->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/invoices/audit-pack?from=2026-09-30&to=2026-09-01')->assertStatus(422)->assertJsonValidationErrors('to');
    }

    public function test_audit_pack_refuses_an_oversized_batch_instead_of_exhausting_memory(): void
    {
        config(['invoice_images.audit_pack_max' => 1]);
        $admin = $this->user('admin');
        $this->postForm($admin, '/api/gas/purchases', $this->gasForm(['invoice_image' => $this->photo(600, 400)]));
        $this->postForm($admin, '/api/gas/purchases', $this->gasForm(['date' => '2026-09-07', 'invoice_image' => $this->photo(600, 400)]));

        $this->actingAs($admin, 'sanctum')->getJson('/api/invoices/audit-pack?from=2026-09-01&to=2026-09-30')
            ->assertStatus(422)->assertJsonValidationErrors('to');
    }

    public function test_listing_purchases_reports_which_ones_have_invoices(): void
    {
        $manager = $this->user('manager');
        $this->postForm($manager, '/api/gas/purchases', $this->gasForm(['invoice_image' => $this->photo(600, 400)]));
        $this->postForm($manager, '/api/gas/purchases', $this->gasForm(['date' => '2026-09-08']));

        $rows = collect($this->actingAs($manager, 'sanctum')->getJson('/api/gas/purchases')->json());

        $this->assertSame([false, true], $rows->pluck('has_invoice')->sort()->values()->all());
    }
}
