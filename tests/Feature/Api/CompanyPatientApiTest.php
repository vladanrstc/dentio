<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPatientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_get_company_patients(): void
    {
        $context = $this->createCompanyContext(User::ROLE_COMPANY_ADMIN);

        $this->actingAs($context['user'])
            ->getJson(route('api.company.patients.index'))
            ->assertOk();
    }

    public function test_dentist_can_get_company_patients(): void
    {
        $context = $this->createCompanyContext(User::ROLE_DENTIST);

        $this->actingAs($context['user'])
            ->getJson(route('api.company.patients.index'))
            ->assertOk();
    }

    public function test_nurse_can_get_company_patients(): void
    {
        $context = $this->createCompanyContext(User::ROLE_NURSE);

        $this->actingAs($context['user'])
            ->getJson(route('api.company.patients.index'))
            ->assertOk();
    }

    public function test_response_contains_patients_from_users_company(): void
    {
        $context = $this->createCompanyContext(User::ROLE_COMPANY_ADMIN);

        $this->actingAs($context['user'])
            ->getJson(route('api.company.patients.index'))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $context['patient']->id,
                'full_name' => $context['patient']->fullName(),
            ]);
    }

    public function test_response_does_not_contain_patients_from_another_company(): void
    {
        $context = $this->createCompanyContext(User::ROLE_COMPANY_ADMIN);
        $otherCompany = Company::factory()->create();
        $otherPatient = Patient::factory()->create([
            'company_id' => $otherCompany->id,
            'first_name' => 'Other',
            'last_name' => 'Patient',
        ]);

        $this->actingAs($context['user'])
            ->getJson(route('api.company.patients.index'))
            ->assertOk()
            ->assertJsonMissing([
                'id' => $otherPatient->id,
                'full_name' => $otherPatient->fullName(),
            ]);
    }

    public function test_user_can_get_own_company_patient(): void
    {
        $context = $this->createCompanyContext(User::ROLE_DENTIST);

        $this->actingAs($context['user'])
            ->getJson(route('api.company.patients.show', $context['patient']->id))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $context['patient']->id,
                'full_name' => $context['patient']->fullName(),
            ]);
    }

    public function test_user_cannot_get_another_company_patient(): void
    {
        $context = $this->createCompanyContext(User::ROLE_DENTIST);
        $otherCompany = Company::factory()->create();
        $otherPatient = Patient::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($context['user'])
            ->getJson(route('api.company.patients.show', $otherPatient->id))
            ->assertNotFound();
    }

    /**
     * @return array{company: Company, user: User, patient: Patient}
     */
    private function createCompanyContext(string $role): array
    {
        $company = Company::factory()->create();
        $user = match ($role) {
            User::ROLE_COMPANY_ADMIN => User::factory()->companyAdmin()->forCompany($company)->create(),
            User::ROLE_NURSE => User::factory()->nurse()->forCompany($company)->create(),
            default => User::factory()->dentist()->forCompany($company)->create(),
        };
        $patient = Patient::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Api',
            'last_name' => 'Patient',
        ]);

        return [
            'company' => $company,
            'user' => $user,
            'patient' => $patient,
        ];
    }
}
