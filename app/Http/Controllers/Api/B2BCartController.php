<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\B2BDemoCatalog;
use App\Services\B2BPortalService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class B2BCartController extends Controller
{
    public function index(Request $request, TenantContext $tenant, B2BPortalService $portal)
    {
        $this->authorizeCart($request, $tenant, $portal, 'manage_basket');
        return response()->json($this->cartPayload($this->items($request)));
    }

    public function store(Request $request, TenantContext $tenant, B2BPortalService $portal, B2BDemoCatalog $demoCatalog)
    {
        $this->authorizeCart($request, $tenant, $portal, 'manage_basket');
        $data = $request->validate(['sku' => ['required', 'string', 'max:120'], 'quantity' => ['required', 'integer', 'min:1', 'max:50000']]);
        $product = $this->product($request, $data['sku'], $portal, $demoCatalog);
        $quantity = $data['quantity'];
        $this->validateQuantity($product, $quantity);
        $items = $this->items($request);
        $existing = $items[$product['sku']] ?? null;
        $requestedTotal = ($existing['quantity'] ?? 0) + $quantity;
        $this->validateStock($product, $requestedTotal);
        $items[$product['sku']] = $this->line($product, $requestedTotal);
        $this->saveItems($request, $items);
        return response()->json($this->cartPayload($items), $existing ? 200 : 201);
    }

    public function quickOrder(Request $request, TenantContext $tenant, B2BPortalService $portal, B2BDemoCatalog $demoCatalog)
    {
        $this->authorizeCart($request, $tenant, $portal, 'create_quick_orders');
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.sku' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50000'],
        ]);

        $requestedItems = collect($data['items'])->map(fn (array $item) => [
            'sku' => trim($item['sku']), 'quantity' => (int) $item['quantity'],
        ]);
        $normalizedSkus = $requestedItems->map(fn (array $item) => mb_strtolower($item['sku']));
        if ($normalizedSkus->contains('')) {
            throw ValidationException::withMessages(['items' => ['Enter a product code for every quick order line.']]);
        }
        if ($normalizedSkus->unique()->count() !== $normalizedSkus->count()) {
            throw ValidationException::withMessages(['items' => ['Each product can only appear once in a quick order.']]);
        }

        $items = $this->items($request);
        foreach ($requestedItems as $requested) {
            $product = $this->product($request, $requested['sku'], $portal, $demoCatalog);
            $this->validateQuantity($product, $requested['quantity']);
            $existingQuantity = (int) ($items[$product['sku']]['quantity'] ?? 0);
            $totalQuantity = $existingQuantity + $requested['quantity'];
            $this->validateStock($product, $totalQuantity);
            $items[$product['sku']] = $this->line($product, $totalQuantity);
        }

        $this->saveItems($request, $items);

        return response()->json($this->cartPayload($items));
    }

    public function update(Request $request, string $sku, TenantContext $tenant, B2BPortalService $portal, B2BDemoCatalog $demoCatalog)
    {
        $this->authorizeCart($request, $tenant, $portal, 'manage_basket');
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:50000']]);
        $items = $this->items($request);
        abort_unless(isset($items[$sku]), 404, 'This product is not in your basket.');
        $product = $this->product($request, $sku, $portal, $demoCatalog);
        $this->validateQuantity($product, $data['quantity']);
        $this->validateStock($product, $data['quantity']);
        $items[$sku] = $this->line($product, $data['quantity']);
        $this->saveItems($request, $items);
        return response()->json($this->cartPayload($items));
    }

    public function destroy(Request $request, string $sku, TenantContext $tenant, B2BPortalService $portal)
    {
        $this->authorizeCart($request, $tenant, $portal, 'manage_basket');
        $items = $this->items($request);
        unset($items[$sku]);
        $this->saveItems($request, $items);
        return response()->json($this->cartPayload($items));
    }

    public function clear(Request $request, TenantContext $tenant, B2BPortalService $portal)
    {
        $this->authorizeCart($request, $tenant, $portal, 'manage_basket');
        $this->saveItems($request, []);
        return response()->json($this->cartPayload([]));
    }

    private function product(Request $request, string $sku, B2BPortalService $portal, B2BDemoCatalog $demoCatalog): array
    {
        if ((bool) $request->session()->get('b2b_portal_demo_mode', false)) {
            abort_unless($portal->demoModeAllowed(), 503, 'The B2B portal service is not configured.');
            $product = $demoCatalog->find($sku);
            abort_unless($product, 404, 'This sample product is no longer available.');
            return $product;
        }
        $accountCode = $request->session()->get('b2b_portal_account');
        abort_unless($accountCode, 409, 'Select a customer account before adding products.');
        try { return $portal->product($sku, $request->user()->email, $accountCode); }
        catch (RuntimeException $exception) { abort(503, $exception->getMessage()); }
    }

    private function validateQuantity(array $product, int $quantity): void
    {
        $caseQuantity = max(1, (int) ($product['case_quantity'] ?? 1));
        if ($quantity % $caseQuantity !== 0) {
            throw ValidationException::withMessages(['quantity' => ["Quantity must be ordered in multiples of {$caseQuantity}."]]);
        }
    }

    private function validateStock(array $product, int $quantity): void
    {
        if (! ($product['allow_backorder'] ?? false) && isset($product['stock_quantity']) && $quantity > (int) $product['stock_quantity']) {
            throw ValidationException::withMessages(['quantity' => ['Requested quantity is above current stock.']]);
        }
    }

    private function line(array $product, int $quantity): array
    {
        return [
            'sku' => (string) $product['sku'], 'name' => (string) $product['name'],
            'image' => (string) ($product['image'] ?? ''), 'category' => (string) ($product['category'] ?? ''),
            'price_minor' => (int) $product['price_minor'], 'case_quantity' => max(1, (int) ($product['case_quantity'] ?? 1)),
            'stock_status' => (string) ($product['stock_status'] ?? 'Availability checked at checkout'), 'quantity' => $quantity,
        ];
    }

    private function authorizeCart(Request $request, TenantContext $tenant, B2BPortalService $portal, string $permission): void
    {
        abort_unless($request->attributes->get('membership')?->can($permission), 403, 'You do not have permission to use this basket feature.');
        abort_if($tenant->company()->is_suspended, 403, 'This company workspace has been suspended.');
        abort_unless($request->session()->get('b2b_portal_account'), 409, 'Select a customer account before using the basket.');
        if ((bool) $request->session()->get('b2b_portal_demo_mode', false)) {
            abort_unless($portal->demoModeAllowed(), 503, 'The B2B portal service is not configured.');
        }
    }

    private function key(Request $request): string
    {
        $membership = $request->attributes->get('membership');

        return hash('sha256', $request->user()->id.'|'.$membership->company_id.'|'.$request->session()->get('b2b_portal_account'));
    }

    private function items(Request $request): array
    {
        return $request->session()->get('b2b_cart_'.$this->key($request), []);
    }

    private function saveItems(Request $request, array $items): void
    {
        $request->session()->put('b2b_cart_'.$this->key($request), $items);
    }

    private function cartPayload(array $items): array
    {
        $lines = array_values($items);
        return [
            'items' => $lines,
            'item_count' => array_sum(array_column($lines, 'quantity')),
            'subtotal_minor' => array_sum(array_map(fn ($line) => $line['price_minor'] * $line['quantity'], $lines)),
        ];
    }
}
