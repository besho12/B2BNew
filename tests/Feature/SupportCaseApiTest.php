<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportCaseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_members_can_create_and_view_their_support_cases(): void
    {
        [$company, $user] = $this->workspace('alpha');

        $response = $this->actingAs($user)->withHeader('X-Company-ID', $company->id)
            ->postJson('/api/v1/support-cases', [
                'subject' => 'Delivery question',
                'description' => 'Please confirm the expected delivery date.',
                'system_reference' => 'ORDER-1001',
            ])
            ->assertCreated()
            ->assertJsonPath('subject', 'Delivery question')
            ->assertJsonPath('system_reference', 'ORDER-1001');

        $caseId = $response->json('id');

        $this->actingAs($user)->withHeader('X-Company-ID', $company->id)
            ->getJson('/api/v1/support-cases/'.$caseId)
            ->assertOk()
            ->assertJsonPath('company_id', $company->id)
            ->assertJsonPath('subject', 'Delivery question');
    }

    public function test_support_cases_cannot_be_read_across_companies(): void
    {
        [$companyA, $userA] = $this->workspace('alpha');
        [$companyB, $userB] = $this->workspace('beta');
        $foreignCase = SupportCase::create([
            'company_id' => $companyB->id,
            'created_by' => $userB->id,
            'subject' => 'Private support case',
            'description' => 'Belongs to another workspace.',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($userA)->withHeader('X-Company-ID', $companyA->id)
            ->getJson('/api/v1/support-cases/'.$foreignCase->id)
            ->assertNotFound();
    }

    public function test_members_without_support_case_permissions_are_denied(): void
    {
        [$company] = $this->workspace('alpha');
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $company->memberships()->create(['user_id' => $user->id, 'role' => 'viewer', 'status' => 'active']);

        $this->actingAs($user)->withHeader('X-Company-ID', $company->id)
            ->postJson('/api/v1/support-cases', [
                'subject' => 'Unauthorized case',
                'description' => 'This should not be created.',
            ])
            ->assertForbidden();
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
