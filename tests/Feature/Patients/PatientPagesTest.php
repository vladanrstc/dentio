<?php

namespace Tests\Feature\Patients;

use App\Models\Company;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_access_patients_index(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->get('/patients')
            ->assertOk()
            ->assertSee('Lista pacijenata')
            ->assertSee($context['patient']->fullName());
    }

    public function test_company_admin_can_access_patient_create_page(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->get('/patients/create')
            ->assertOk()
            ->assertSee('Novi pacijent')
            ->assertSee('Primarni stomatolog');
    }

    public function test_company_admin_can_access_patient_show_page(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->get('/patients/'.$context['patient']->id)
            ->assertOk()
            ->assertSee($context['patient']->fullName())
            ->assertSee('Podaci o pacijentu');
    }

    public function test_company_admin_can_access_patient_edit_page(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->get('/patients/'.$context['patient']->id.'/edit')
            ->assertOk()
            ->assertSee('Izmeni pacijenta')
            ->assertSee('Sacuvaj izmene');
    }

    public function test_company_admin_can_access_patient_status_edit_page(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->get('/patients/'.$context['patient']->id.'/status/edit')
            ->assertOk()
            ->assertSee('Promeni status pacijenta')
            ->assertSee('Obrazlozenje');
    }

    public function test_company_admin_can_access_patient_task_create_page(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->get('/patients/'.$context['patient']->id.'/tasks/create')
            ->assertOk()
            ->assertSee('Dodaj aktivnu stavku')
            ->assertSee('Opis stavke');
    }

    public function test_company_admin_can_access_patient_appointment_create_page(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->get('/patients/'.$context['patient']->id.'/appointments/create')
            ->assertOk()
            ->assertSee('Zakazi termin')
            ->assertSee('Tip termina');
    }

    public function test_company_admin_can_access_patient_intervention_create_page(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->get('/patients/'.$context['patient']->id.'/interventions/create')
            ->assertOk()
            ->assertSee('Unesi intervenciju')
            ->assertSee('Naziv intervencije');
    }

    /**
     * @return array{companyAdmin: User, patient: Patient}
     */
    private function createCompanyContext(): array
    {
        $company = Company::factory()->create();
        $companyAdmin = User::factory()->companyAdmin()->forCompany($company)->create();
        $dentist = User::factory()->dentist()->forCompany($company)->create();
        User::factory()->nurse()->forCompany($company)->create();

        $patient = Patient::factory()->create([
            'company_id' => $company->id,
            'primary_dentist_id' => $dentist->id,
        ]);

        return [
            'companyAdmin' => $companyAdmin,
            'patient' => $patient,
        ];
    }
}
