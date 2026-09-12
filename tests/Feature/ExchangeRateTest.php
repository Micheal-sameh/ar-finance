<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'base_currency' => 'EGP']);
        Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Co', 'currency' => 'USD']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_index_syncs_and_displays_rates_against_the_tenant_base_currency(): void
    {
        Http::fake([
            '*' => Http::response(['date' => '2026-09-11', 'egp' => ['usd' => 0.02, 'eur' => 0.018]], 200),
        ]);

        $this->actingAsRole('Accountant');

        $response = $this->get(route('exchange-rates.index', ['date' => '2026-09-11']));

        $response->assertInertia(fn ($page) => $page
            ->component('Tools/ExchangeRates/Index')
            ->where('report.base_currency', 'EGP')
            ->where('report.is_stale', false)
        );

        $rows = $response->viewData('page')['props']['report']['rows'];
        $usdRow = collect($rows)->firstWhere('currency', 'USD');

        $this->assertNotNull($usdRow);
        $this->assertEqualsWithDelta(50.0, $usdRow['rate'], 0.001);
    }

    public function test_index_prefers_the_official_cbe_rate_when_it_can_be_scraped(): void
    {
        $cbeHtml = <<<'HTML'
            <p>Rates for Date: 11/09/2026</p>
            <table><tbody>
                <tr><td>US Dollar</td><td>51.2000</td><td>51.4000</td></tr>
                <tr><td>Euro</td><td>59.6000</td><td>59.8000</td></tr>
            </tbody></table>
            HTML;

        Http::fake([
            'cbe.org.eg/*' => Http::response($cbeHtml, 200),
            '*' => Http::response('', 404),
        ]);

        $this->actingAsRole('Accountant');

        $response = $this->get(route('exchange-rates.index', ['date' => '2026-09-11']));

        $response->assertInertia(fn ($page) => $page
            ->where('report.source', 'cbe.org.eg')
            ->where('report.is_stale', false)
        );

        $rows = $response->viewData('page')['props']['report']['rows'];
        $usdRow = collect($rows)->firstWhere('currency', 'USD');

        $this->assertNotNull($usdRow);
        $this->assertEqualsWithDelta(51.3, $usdRow['rate'], 0.001);
        $this->assertEqualsWithDelta(51.2, $usdRow['buy'], 0.001);
        $this->assertEqualsWithDelta(51.4, $usdRow['sell'], 0.001);
    }

    public function test_a_date_with_no_provider_data_falls_back_to_the_most_recent_cached_date(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '2026-09-11')) {
                return Http::response('', 404);
            }

            if (str_contains($request->url(), '2026-09-10')) {
                return Http::response(['date' => '2026-09-10', 'egp' => ['usd' => 0.025]], 200);
            }

            return Http::response('', 404);
        });

        $this->actingAsRole('Accountant');

        $response = $this->get(route('exchange-rates.index', ['date' => '2026-09-11']));

        $response->assertInertia(fn ($page) => $page
            ->where('report.is_stale', true)
            ->where('report.actual_date', '2026-09-10')
        );
    }

    public function test_viewer_cannot_sync_rates(): void
    {
        Http::fake(['*' => Http::response(['date' => '2026-09-11', 'egp' => ['usd' => 0.02]], 200)]);

        $this->actingAsRole('Viewer');

        $this->post(route('exchange-rates.sync'), ['date' => '2026-09-11'])->assertForbidden();
    }

    public function test_accountant_can_force_a_resync(): void
    {
        Http::fake(['*' => Http::response(['date' => '2026-09-11', 'egp' => ['usd' => 0.02]], 200)]);

        $this->actingAsRole('Accountant');

        $this->post(route('exchange-rates.sync'), ['date' => '2026-09-11'])
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}
