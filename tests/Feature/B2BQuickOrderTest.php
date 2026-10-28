<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2BQuickOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.b2b_portal.demo_mode' => true]);
    }

    public function test_quick_order_adds_multiple_validated_items_to_the_account_basket(): void
    {
        [$company, $user] = $this->workspace('quick-order');

        $this->actingAs($user)->withSession($this->portalSession())->withHeader('X-Company-ID', $company->id)
            ->postJson('/api/v1/portal/cart/quick-order', ['items' => [
                ['sku' => 'DEMO-001', 'quantity' => 2],
                ['sku' => 'DEMO-002', 'quantity' => 4],
            ]])
            ->assertOk()
            ->assertJsonPath('item_count', 6)
            ->assertJsonPath('subtotal_minor', 6000)
            ->assertJsonCount(2, 'items');
    }

    public function test_invalid_quick_order_does_not_partially_change_the_basket(): void
    {
        [$company, $user] = $this->workspace('quick-order-atomic');
        $this->actingAs($user)->withSession($this->portalSession())->withHeader('X-Company-ID', $company->id)
            ->postJson('/api/v1/portal/cart/quick-order', ['items' => [
                ['sku' => 'DEMO-001', 'quantity' => 2],
                ['sku' => 'DEMO-002', 'quantity' => 3],
            ]])
            ->assertUnprocessable();

        $this->actingAs($user)->withSession($this->portalSession())->withHeader('X-Company-ID', $company->id)
            ->getJson('/api/v1/portal/cart')
            ->assertOk()
            ->assertJsonPath('item_count', 0)
            ->assertJsonCount(0, 'items');
    }

    public function test_the_same_user_has_separate_baskets_in_different_companies(): void
    {
        [$companyA, $user] = $this->workspace('quick-order-alpha');
        $companyB = Company::create(['name' => 'Quick Order Beta', 'slug' => 'quick-order-beta']);
        $this->enableCompanyTrial($companyB);
        $companyB->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);

        $this->actingAs($user)->withSession($this->portalSession())->withHeader('X-Company-ID', $companyA->id)
            ->postJson('/api/v1/portal/cart/quick-order', ['items' => [['sku' => 'DEMO-001', 'quantity' => 2]]])
            ->assertOk()->assertJsonPath('item_count', 2);

        $this->actingAs($user)->withSession($this->portalSession())->withHeader('X-Company-ID', $companyB->id)
            ->getJson('/api/v1/portal/cart')
            ->assertOk()
            ->assertJsonPath('item_count', 0)
            ->assertJsonCount(0, 'items');
    }

    private function portalSession(): array
    {
        return ['b2b_portal_account' => 'DEMO', 'b2b_portal_demo_mode' => true];
    }

    private function workspace(string $slug): array
    {
        $company = Company::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $this->enableCompanyTrial($company);
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $company->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);

        return [$company, $user];
    }
}
