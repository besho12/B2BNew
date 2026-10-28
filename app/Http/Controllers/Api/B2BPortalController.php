<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\B2BPortalService;
use App\Services\B2BDemoCatalog;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class B2BPortalController extends Controller
{
    public function accounts(Request $request, B2BPortalService $portal)
    {
        if (! $portal->isConfigured()) {
            if (! $portal->demoModeAllowed()) {
                $request->session()->forget(['b2b_portal_accounts', 'b2b_portal_account', 'b2b_portal_demo_mode']);
                abort(503, 'The B2B portal service is not configured.');
            }
            $request->session()->forget(['b2b_portal_accounts']);
            $request->session()->put('b2b_portal_demo_mode', true);
            $request->session()->put('b2b_portal_account', 'DEMO');
            return response()->json(['accounts' => [], 'selected_account' => 'DEMO', 'requires_selection' => false, 'demo_mode' => true]);
        }
        $request->session()->put('b2b_portal_demo_mode', false);
        try { $accounts = $portal->accounts($request->user()->email); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 503); }
        if (count($accounts) === 0) {
            $request->session()->forget(['b2b_portal_accounts', 'b2b_portal_account']);
            return response()->json(['accounts' => [], 'selected_account' => null, 'requires_selection' => true]);
        }
        $request->session()->put('b2b_portal_accounts', $accounts);
        if (! $request->session()->get('b2b_portal_accounts_loaded')) $request->session()->forget('b2b_portal_account');
        $request->session()->put('b2b_portal_accounts_loaded', true);
        $selected = $request->session()->get('b2b_portal_account');
        if (count($accounts) === 1) $selected = $accounts[0]['code'];
        if ($selected && ! collect($accounts)->contains('code', $selected)) $selected = null;
        if ($selected) $request->session()->put('b2b_portal_account', $selected);
        else $request->session()->forget('b2b_portal_account');
        return response()->json(['accounts' => $accounts, 'selected_account' => $selected, 'requires_selection' => count($accounts) > 1 && ! $selected]);
    }

    public function selectAccount(Request $request)
    {
        $accounts = collect($request->session()->get('b2b_portal_accounts', []));
        abort_if($accounts->isEmpty(), 409, 'Reload the available customer accounts before selecting one.');
        $data = $request->validate(['account_code' => ['required', 'string', Rule::in($accounts->pluck('code')->all())]]);
        $request->session()->put('b2b_portal_account', $data['account_code']);
        return response()->json(['message' => 'Customer account selected.', 'selected_account' => $data['account_code']]);
    }

    public function accountDetails(Request $request, B2BPortalService $portal)
    {
        $this->authorizePortalAccess($request, 'view_account_details');
        $accountCode = $request->session()->get('b2b_portal_account');
        abort_unless($accountCode, 409, 'Select a customer account before viewing account details.');
        if ((bool) $request->session()->get('b2b_portal_demo_mode', false)) {
            abort_unless($portal->demoModeAllowed(), 503, 'The B2B portal service is not configured.');
            return response()->json(['demo_mode' => true, 'customer_code' => 'DEMO']);
        }
        try {
            return response()->json($portal->accountDetails($request->user()->email, $accountCode));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    public function addresses(Request $request, B2BPortalService $portal)
    {
        $this->authorizePortalAccess($request, 'view_address_book');
        $accountCode = $request->session()->get('b2b_portal_account');
        abort_unless($accountCode, 409, 'Select a customer account before viewing its addresses.');
        if ((bool) $request->session()->get('b2b_portal_demo_mode', false)) {
            abort_unless($portal->demoModeAllowed(), 503, 'The B2B portal service is not configured.');
            return response()->json(['demo_mode' => true, 'bill_addresses' => [], 'ship_addresses' => []]);
        }
        try {
            return response()->json($portal->addresses($request->user()->email, $accountCode));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    public function showProduct(Request $request, string $sku, B2BPortalService $portal, B2BDemoCatalog $demoCatalog)
    {
        $this->authorizePortalAccess($request, 'view_product_catalog');
        if ((bool) $request->session()->get('b2b_portal_demo_mode', false)) {
            abort_unless($portal->demoModeAllowed(), 503, 'The B2B portal service is not configured.');
            $product = $demoCatalog->find($sku);
            abort_unless($product, 404, 'This sample product is no longer available.');
            return response()->json($product);
        }
        $accountCode = $request->session()->get('b2b_portal_account');
        abort_unless($accountCode, 409, 'Select a customer account before viewing products.');
        try { return response()->json($portal->product($sku, $request->user()->email, $accountCode)); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 503); }
    }
    public function products(Request $request, B2BPortalService $portal)
    {
        $this->authorizePortalAccess($request, 'view_product_catalog');
        $accountCode = $request->session()->get('b2b_portal_account');
        abort_unless($accountCode, 409, 'Select a customer account before browsing products.');
        $filters = $request->validate([
            'max_id' => ['nullable', 'integer', 'min:0', 'max:2147483647'], 'search' => ['nullable', 'string', 'max:120'],
            'favorites' => ['nullable', 'boolean'], 'promotions' => ['nullable', 'boolean'],
            'category_list' => ['nullable', 'array', 'max:30'], 'category_list.*' => ['string', 'max:120'],
        ]);
        try { return response()->json($portal->products($filters, $request->user()->email, $accountCode)); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 503); }
    }

    public function orders(Request $request, B2BPortalService $portal, TenantContext $tenant)
    {
        $this->authorizePortalAccess($request, 'view_sales_orders');
        $accountCode = $request->session()->get('b2b_portal_account');
        abort_unless($accountCode && ! (bool) $request->session()->get('b2b_portal_demo_mode', false), 409, 'Order history is available after connecting a live customer account.');
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        try {
            $membership = $request->user()->membershipFor($tenant->id());
            $isAdmin = in_array($membership?->role, ['owner', 'admin'], true);
            return response()->json($portal->orders($filters, $request->user()->email, $accountCode, $isAdmin));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    public function toggleFavorite(Request $request, string $sku, B2BPortalService $portal)
    {
        $this->authorizePortalAccess($request, 'view_product_catalog');
        $accountCode = $request->session()->get('b2b_portal_account');
        abort_unless($accountCode, 409, 'Select a customer account before changing favorites.');
        try {
            return response()->json($portal->toggleFavorite($sku, $request->user()->email, $accountCode));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    public function filters(Request $request, B2BPortalService $portal)
    {
        $this->authorizePortalAccess($request, 'view_product_catalog');
        $accountCode = $request->session()->get('b2b_portal_account');
        abort_unless($accountCode, 409, 'Select a customer account before browsing products.');
        try { return response()->json($portal->filters($request->user()->email, $accountCode)); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 503); }
    }

    protected function authorizePortalAccess(Request $request, string $permission): void
    {
        abort_unless($request->attributes->get('membership')?->can($permission), 403, 'You do not have permission to use this portal feature.');
    }
}
