<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class B2BPortalService
{
    public function isConfigured(): bool
    {
        return (string) config('services.b2b_portal.base_url') !== '' && (string) config('services.b2b_portal.token') !== '';
    }

    public function demoModeAllowed(): bool
    {
        return (bool) config('services.b2b_portal.demo_mode', false);
    }
    public function accounts(string $email): array
    {
        $payload = $this->post('accounts_endpoint', ['email' => $email]);
        if (! is_array($payload)) throw new RuntimeException('The account service returned an unexpected response.');
        $accounts = $payload['data'] ?? $payload['accounts'] ?? $payload;
        if (! is_array($accounts)) throw new RuntimeException('The account service returned an unexpected response.');
        if (isset($accounts['bp-code']) || isset($accounts['bp_code']) || isset($accounts['code'])) {
            $accounts = [$accounts];
        }

        return collect($accounts)->map(function ($account) {
            if (is_string($account) && trim($account) !== '') {
                return ['code' => trim($account), 'name' => trim($account)];
            }
            if (! is_array($account)) return null;
            $code = $account['bp-code'] ?? $account['bp_code'] ?? $account['code'] ?? null;
            if (! is_scalar($code) || trim((string) $code) === '') return null;
            $name = $account['bp-name'] ?? $account['bp_name'] ?? $account['name'] ?? $code;
            return ['code' => trim((string) $code), 'name' => is_scalar($name) ? trim((string) $name) : (string) $code];
        })->filter()
            ->unique('code')->values()->all();
    }

    public function accountDetails(string $email, string $accountCode): array
    {
        $payload = $this->post('account_details_endpoint', ['email' => $email, 'bp_code' => $accountCode]);
        $details = is_array($payload) ? ($payload['data'] ?? $payload['account'] ?? $payload) : null;
        if (is_array($details) && array_is_list($details)) $details = $details[0] ?? null;
        if (! is_array($details)) throw new RuntimeException('The customer account service returned an unexpected response.');

        return [
            'customer_code' => (string) ($details['customer_code'] ?? $details['bp_code'] ?? $details['bp-code'] ?? $accountCode),
            'customer_name' => (string) ($details['customer_name'] ?? $details['bp_name'] ?? $details['bp-name'] ?? ''),
            'first_name' => (string) ($details['first_name'] ?? $details['firstname'] ?? ''),
            'surname' => (string) ($details['surname'] ?? $details['last_name'] ?? ''),
            'position' => (string) ($details['position'] ?? ''),
            'email' => (string) ($details['email'] ?? $email),
            'telephone' => (string) ($details['telephone'] ?? $details['phone'] ?? ''),
            'mobile' => (string) ($details['mobile'] ?? ''),
        ];
    }

    public function addresses(string $email, string $accountCode): array
    {
        $payload = $this->post('addresses_endpoint', ['email' => $email, 'bp_code' => $accountCode]);
        $result = is_array($payload) ? ($payload['data'] ?? $payload) : null;
        if (! is_array($result)) throw new RuntimeException('The customer address service returned an unexpected response.');
        return [
            'bill_addresses' => $this->normalizeAddresses($result['bill_addresses'] ?? $result['billing_addresses'] ?? []),
            'ship_addresses' => $this->normalizeAddresses($result['ship_addresses'] ?? $result['shipping_addresses'] ?? []),
        ];
    }

    private function normalizeAddresses(mixed $rows): array
    {
        if (! is_array($rows)) return [];
        return collect($rows)->filter(fn ($row) => is_array($row))->map(fn ($row) => [
            'address_id' => (string) ($row['addressid'] ?? $row['address_id'] ?? ''),
            'company_name' => (string) ($row['streetpo_box'] ?? $row['company_name'] ?? ''),
            'address_line_1' => (string) ($row['strretno'] ?? $row['streetno'] ?? $row['address_line_1'] ?? ''),
            'address_line_2' => (string) ($row['building'] ?? $row['address_line_2'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'postcode' => (string) ($row['postcode'] ?? ''),
            'county' => (string) ($row['County'] ?? $row['county'] ?? ''),
            'country' => (string) ($row['country_record']['cname'] ?? $row['country'] ?? ''),
        ])->values()->all();
    }

    public function products(array $filters, string $email, string $accountCode): array
    {
        $stockCacheScope = implode('|', [
            $this->baseUrl(),
            hash('sha256', (string) config('services.b2b_portal.token')),
            strtolower($email),
            $accountCode,
        ]);
        $stockData = Cache::remember('b2b_stock_analysis_'.sha1($stockCacheScope), now()->addMinutes(60), fn () => $this->post('stock_analysis_endpoint', ['bp_code' => $accountCode]));
        $payload = $this->post('products_endpoint', [
            'email' => $email, 'bp_code' => $accountCode,
            'max_id' => $filters['max_id'] ?? 0,
            'search' => $filters['search'] ?? '',
            'favorites' => $filters['favorites'] ?? 0,
            'promotions' => $filters['promotions'] ?? 0,
            'category_list' => $filters['category_list'] ?? [],
            'is_super_admin' => false,
            'stock_data' => $stockData,
        ]);
        if (! is_array($payload)) throw new RuntimeException('The product service returned an unexpected page response.');
        $data = $payload['data'] ?? [];
        if (! is_array($data) && ! is_string($data)) throw new RuntimeException('The product service returned an unexpected product list.');

        return array_merge($payload, [
            'data' => $data,
            'number_current_products' => max(0, (int) ($payload['number_current_products'] ?? (is_array($data) ? count($data) : 0))),
            'number_all_products' => max(0, (int) ($payload['number_all_products'] ?? 0)),
            'max_id' => is_numeric($payload['max_id'] ?? null) ? max(0, (int) $payload['max_id']) : 0,
        ]);
    }

    public function product(string $sku, string $email, string $accountCode): array
    {
        $payload = $this->post('single_product_endpoint', ['itemno' => $sku, 'email' => $email, 'bp_code' => $accountCode]);
        $product = $payload['data'] ?? $payload['product'] ?? $payload;
        if (is_array($product) && array_is_list($product)) $product = $product[0] ?? null;
        if (! is_array($product) || ! isset($product['itemno'])) throw new RuntimeException('The product service returned an unexpected response.');
        if ((string) $product['itemno'] !== $sku) throw new RuntimeException('The product service returned a different item.');
        if (! isset($product['price']) || ! is_numeric($product['price'])) throw new RuntimeException('The product price is unavailable.');
        $caseQtyEnabled = (string) ($product['b2b_case_qty_customer'] ?? '0') === '1';
        $caseQuantity = $caseQtyEnabled ? max(1, (int) ($product['qty_shipment_pack'] ?? 1)) : 1;
        $stockQuantity = isset($product['stock_quantity']) && is_numeric($product['stock_quantity']) ? max(0, (int) $product['stock_quantity']) : null;
        $allowBackorder = (string) ($product['b2b_back_order'] ?? '0') === '1';
        return [
            'sku' => (string) $product['itemno'],
            'name' => (string) ($product['b2b_product_name'] ?? $product['description'] ?? $product['name'] ?? $sku),
            'image' => (string) ($product['default_image'] ?? $product['image'] ?? ''),
            'category' => (string) ($product['category_name'] ?? $product['category'] ?? ''),
            'price_minor' => (int) round((float) $product['price'] * 100),
            'case_quantity' => $caseQuantity,
            'stock_quantity' => $stockQuantity,
            'allow_backorder' => $allowBackorder,
            'stock_status' => $allowBackorder ? 'Backorder available' : (($stockQuantity ?? 0) > 0 ? 'In stock' : 'Out of stock'),
        ];
    }

    public function toggleFavorite(string $sku, string $email, string $accountCode): array
    {
        $payload = $this->post('favorite_endpoint', ['bp_code' => $accountCode, 'email' => $email, 'itemno' => $sku]);
        if (! is_array($payload)) throw new RuntimeException('The favorites service returned an unexpected response.');
        return $payload;
    }

    public function orders(array $filters, string $email, string $accountCode, bool $isAdmin): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
        $payload = $this->post('orders_endpoint', [
            'draw' => $page,
            'start' => ($page - 1) * $perPage,
            'length' => $perPage,
            'search' => ['value' => trim((string) ($filters['search'] ?? '')), 'regex' => false],
            'is_admin' => $isAdmin ? 1 : 0,
            'is_super_admin' => false,
            'email' => $email,
            'bp_code' => $accountCode,
        ]);
        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            throw new RuntimeException('The order history service returned an unexpected response.');
        }

        $rows = collect($payload['data'])->filter(fn ($row) => is_array($row))->map(fn ($row) => [
            'id' => (string) ($row['id'] ?? $row['so_no'] ?? $row['doc_num'] ?? ''),
            'order_number' => (string) ($row['so_no'] ?? $row['doc_num'] ?? $row['number'] ?? $row['id'] ?? '—'),
            'po_reference' => (string) ($row['supplier_ref_no'] ?? $row['po_reference'] ?? ''),
            'order_date' => (string) ($row['created_at'] ?? $row['doc_date'] ?? ''),
            'due_date' => (string) ($row['delivery_date'] ?? $row['due_date'] ?? ''),
            'status' => $this->plainText($row['status'] ?? 'Unknown'),
            'payment_status' => $this->plainText($row['payment_status'] ?? 'Unknown'),
            'type' => $this->plainText($row['b2b_created_by'] ?? $row['type'] ?? ''),
            'total_amount' => is_numeric($row['total_amount'] ?? null) ? (float) $row['total_amount'] : null,
            'currency' => (string) ($row['currency'] ?? 'EGP'),
        ])->values()->all();

        return [
            'data' => $rows,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => max(0, (int) ($payload['recordsFiltered'] ?? $payload['recordsTotal'] ?? count($rows))),
        ];
    }

    public function filters(string $email, string $accountCode): array
    {
        $payload = $this->post('filters_endpoint', ['email' => $email, 'bp_code' => $accountCode, 'favorites' => 0, 'promotions' => 0, 'is_super_admin' => false]);
        return is_array($payload) ? $payload : ['data' => []];
    }

    private function plainText(mixed $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function post(string $endpointKey, array $payload): mixed
    {
        $base = $this->baseUrl();
        $endpoint = trim((string) config('services.b2b_portal.'.$endpointKey), '/');
        $token = (string) config('services.b2b_portal.token');
        if ($endpoint === '' || str_contains($endpoint, '?') || str_contains($endpoint, '#') || preg_match('~(^|/)\.\.?(/|$)~', $endpoint)) {
            throw new RuntimeException('The configured B2B portal endpoint is invalid.');
        }
        if ($token === '') throw new RuntimeException('The B2B portal service is not configured.');
        try {
            $response = Http::acceptJson()->asJson()
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(max(1, (int) config('services.b2b_portal.connect_timeout', 5)))
                ->timeout(max(1, (int) config('services.b2b_portal.timeout', 20)))
                ->post($base.'/'.$endpoint, ['token' => $token] + $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The B2B portal service could not be reached.', previous: $exception);
        }
        if (! $response->successful()) throw new RuntimeException('The B2B portal service returned HTTP '.$response->status().'.');
        try {
            $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The B2B portal service returned invalid JSON.', previous: $exception);
        }
        if (! is_array($decoded)) throw new RuntimeException('The B2B portal service returned an unexpected response.');

        return $decoded;
    }

    private function baseUrl(): string
    {
        $configured = trim((string) config('services.b2b_portal.base_url'));
        $parts = parse_url($configured);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new RuntimeException('The B2B portal base URL is invalid.');
        }
        if (app()->environment('production') && strtolower((string) $parts['scheme']) !== 'https') {
            throw new RuntimeException('The B2B portal base URL must use HTTPS in production.');
        }

        return rtrim($configured, '/');
    }
}
