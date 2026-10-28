<?php

namespace Tests\Feature;

use App\Services\B2BPortalService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class B2BPortalServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.env' => 'testing',
            'services.b2b_portal.base_url' => 'https://b2b.example.test/api/',
            'services.b2b_portal.token' => 'test-service-token',
            'services.b2b_portal.accounts_endpoint' => 'get_available_accounts',
            'services.b2b_portal.connect_timeout' => 2,
            'services.b2b_portal.timeout' => 5,
        ]);
    }

    public function test_account_request_uses_the_configured_endpoint_and_keeps_service_token_server_side(): void
    {
        Http::fake(['https://b2b.example.test/api/get_available_accounts' => Http::response([
            'data' => [['bp-code' => 'BP-100', 'bp-name' => 'Example Customer']],
        ])]);

        $accounts = app(B2BPortalService::class)->accounts('buyer@example.test');

        $this->assertSame([['code' => 'BP-100', 'name' => 'Example Customer']], $accounts);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://b2b.example.test/api/get_available_accounts'
            && $request->data()['token'] === 'test-service-token'
            && $request->data()['email'] === 'buyer@example.test');
    }

    public function test_upstream_redirect_is_rejected_without_forwarding_the_post_to_another_host(): void
    {
        Http::fake([
            'https://b2b.example.test/api/get_available_accounts' => Http::response('', 302, ['Location' => 'https://other.example.test/collect']),
            '*' => Http::response(['data' => []]),
        ]);

        try {
            app(B2BPortalService::class)->accounts('buyer@example.test');
            $this->fail('A redirect response must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The B2B portal service returned HTTP 302.', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_invalid_upstream_json_returns_a_safe_service_error(): void
    {
        Http::fake(['https://b2b.example.test/api/get_available_accounts' => Http::response('<html>private upstream detail</html>', 200)]);

        $this->expectExceptionMessage('The B2B portal service returned invalid JSON.');
        app(B2BPortalService::class)->accounts('buyer@example.test');
    }

    public function test_product_and_stock_calls_use_the_selected_account_and_shared_upstream_configuration(): void
    {
        config([
            'services.b2b_portal.products_endpoint' => 'get_products_list',
            'services.b2b_portal.stock_analysis_endpoint' => 'get_stock_analysis',
        ]);
        Http::fake([
            'https://b2b.example.test/api/get_stock_analysis' => Http::response(['stock' => 'snapshot']),
            'https://b2b.example.test/api/get_products_list' => Http::response([
                'data' => [['itemno' => 'SKU-9']], 'number_current_products' => 1, 'number_all_products' => 1, 'max_id' => 0,
            ]),
        ]);

        $page = app(B2BPortalService::class)->products(['search' => 'cleaner'], 'buyer@example.test', 'BP-200');

        $this->assertSame('SKU-9', $page['data'][0]['itemno']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://b2b.example.test/api/get_stock_analysis'
            && $request->data()['token'] === 'test-service-token'
            && $request->data()['bp_code'] === 'BP-200');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://b2b.example.test/api/get_products_list'
            && $request->data()['bp_code'] === 'BP-200'
            && $request->data()['email'] === 'buyer@example.test'
            && $request->data()['search'] === 'cleaner'
            && $request->data()['stock_data'] === ['stock' => 'snapshot']);
    }

    public function test_production_rejects_an_insecure_http_base_url(): void
    {
        $this->app['env'] = 'production';
        config(['services.b2b_portal.base_url' => 'http://b2b.example.test/api']);

        $this->expectExceptionMessage('The B2B portal base URL must use HTTPS in production.');
        app(B2BPortalService::class)->accounts('buyer@example.test');
    }
}
