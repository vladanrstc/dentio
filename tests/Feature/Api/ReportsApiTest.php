<?php

namespace Tests\Feature\Api;

use App\Mail\ReportMail;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Invite;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\ReportSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_patients_report_returns_csv_and_respects_scope_and_filters(): void
    {
        [$company, $admin] = $this->companyUser('main');
        [$otherCompany] = $this->companyUser('other');
        $dentist = $this->user($company, User::ROLE_DENTIST, 'dentist');
        $patient = $this->patient($company, 'Marko', 'Markovic', [
            'manual_status' => Patient::STATUS_ACTIVE,
            'primary_dentist_id' => $dentist->id,
        ]);
        $this->patient($company, 'Ana', 'Anic', ['manual_status' => Patient::STATUS_INACTIVE]);
        $this->patient($otherCompany, 'Petar', 'Petrovic');
        PatientTask::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'created_by_user_id' => $admin->id,
            'description' => 'Kontrola',
            'status' => PatientTask::STATUS_OPEN,
        ]);
        Intervention::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'performed_by_user_id' => $dentist->id,
            'title' => 'Plomba',
            'intervention_date' => '2026-05-12',
            'total_cost' => 5000,
            'paid_amount' => 1000,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->get('/api/v1/company/reports/patients?format=csv&status=active&has_open_tasks=true&has_debt=true');

        $response->assertOk();
        $this->assertSame([
            'ID',
            'Ime i prezime',
            'Email',
            'Telefon',
            'Status',
            'Primarni stomatolog',
            'Broj otvorenih taskova',
            'Ukupno',
            'Plaćeno',
            'Dugovanje',
            'Kreiran',
        ], $this->csvHeader($response->getContent()));
        $response->assertSee('Marko Markovic', false);
        $response->assertSee('Aktivan', false);
        $response->assertDontSee('active', false);
        $response->assertDontSee('Ana Anic', false);
        $response->assertDontSee('Petar Petrovic', false);
    }

    public function test_report_created_at_is_formatted_in_application_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-12 13:28:00', 'UTC'));
        [$company, $admin] = $this->companyUser('main');
        $patient = $this->patient($company, 'Milan', 'Milanovic');
        $patient->forceFill([
            'created_at' => Carbon::parse('2026-05-12 13:28:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-05-12 13:28:00', 'UTC'),
        ])->save();

        Sanctum::actingAs($admin);

        $response = $this->get('/api/v1/company/reports/patients?format=csv&search=Milan');

        $response->assertOk();
        $response->assertSee('12.05.2026. 15:28', false);
        $response->assertDontSee('2026-05-12 13:28:00', false);

        Carbon::setTestNow();
    }

    public function test_company_appointments_report_returns_csv_and_filters(): void
    {
        [$company, $admin] = $this->companyUser('main');
        $patient = $this->patient($company, 'Marko', 'Markovic');
        $doctor = $this->user($company, User::ROLE_DENTIST, 'doctor');
        $otherDoctor = $this->user($company, User::ROLE_DENTIST, 'other-doctor');
        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $admin->id,
            'assigned_user_id' => $doctor->id,
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 10:30:00',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
            'notes' => 'Redovna kontrola',
        ]);
        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $admin->id,
            'assigned_user_id' => $otherDoctor->id,
            'starts_at' => '2026-05-13 10:00:00',
            'type' => Appointment::TYPE_CONTROL,
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->get("/api/v1/company/reports/appointments?format=csv&date_from=2026-05-12&date_to=2026-05-12&assigned_user_id={$doctor->id}&status=scheduled");

        $response->assertOk();
        $this->assertSame(['Datum/vreme', 'Pacijent', 'Doktor', 'Tip', 'Status', 'Napomena'], $this->csvHeader($response->getContent()));
        $response->assertSee('Redovna kontrola', false);
        $response->assertSee('Pregled', false);
        $response->assertSee('Zakazan', false);
        $response->assertDontSee(Appointment::TYPE_CHECKUP, false);
        $response->assertDontSee(Appointment::STATUS_SCHEDULED, false);
        $response->assertDontSee(Appointment::TYPE_CONTROL, false);
    }

    public function test_company_interventions_financial_report_returns_csv_and_filters(): void
    {
        [$company, $admin] = $this->companyUser('main');
        $patient = $this->patient($company, 'Marko', 'Markovic');
        $doctor = $this->user($company, User::ROLE_DENTIST, 'doctor');
        Intervention::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'performed_by_user_id' => $doctor->id,
            'title' => 'Plomba',
            'intervention_date' => '2026-05-12',
            'total_cost' => 5000,
            'paid_amount' => 1000,
            'next_step' => 'Kontrola',
        ]);
        Intervention::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'performed_by_user_id' => $doctor->id,
            'title' => 'Ciscenje',
            'intervention_date' => '2026-05-13',
            'total_cost' => 2000,
            'paid_amount' => 2000,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->get("/api/v1/company/reports/interventions-financial?format=csv&date_from=2026-05-12&date_to=2026-05-12&performed_by_user_id={$doctor->id}&has_outstanding=true");

        $response->assertOk();
        $this->assertSame(['Datum', 'Pacijent', 'Intervencija', 'Izvršio', 'Ukupna cena', 'Plaćeno', 'Dugovanje', 'Sledeći korak'], $this->csvHeader($response->getContent()));
        $response->assertSee('Plomba', false);
        $response->assertDontSee('Ciscenje', false);
    }

    public function test_dentist_can_use_company_reports_but_platform_admin_without_company_cannot(): void
    {
        [$company] = $this->companyUser('main');
        $dentist = $this->user($company, User::ROLE_DENTIST, 'dentist');
        $platformAdmin = User::factory()->create([
            'company_id' => null,
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);

        Sanctum::actingAs($dentist);
        $this->get('/api/v1/company/reports/patients?format=csv')->assertOk();

        Sanctum::actingAs($platformAdmin);
        $this->get('/api/v1/company/reports/patients?format=csv')->assertForbidden();
    }

    public function test_admin_companies_report_returns_csv_and_company_admin_is_forbidden(): void
    {
        $platformAdmin = User::factory()->create([
            'company_id' => null,
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);
        [$company, $companyAdmin] = $this->companyUser('main');
        $patient = $this->patient($company, 'Marko', 'Markovic');
        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $companyAdmin->id,
            'starts_at' => '2026-05-12 10:00:00',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        Invite::query()->create([
            'company_id' => $company->id,
            'email' => 'invite@example.com',
            'role' => User::ROLE_DENTIST,
            'token' => 'token-'.str()->random(16),
            'expires_at' => Carbon::now()->addDay(),
            'metadata' => [],
        ]);

        Sanctum::actingAs($platformAdmin);
        $response = $this->get('/api/v1/admin/reports/companies?format=csv');

        $response->assertOk();
        $this->assertSame(['Kompanija', 'Email', 'Telefon', 'Broj osoblja', 'Broj pacijenata', 'Broj aktivnih termina', 'Broj pozivnica na čekanju', 'Datum kreiranja'], $this->csvHeader($response->getContent()));
        $response->assertSee($company->name, false);

        Sanctum::actingAs($companyAdmin);
        $this->get('/api/v1/admin/reports/companies?format=csv')->assertForbidden();
    }

    public function test_xlsx_export_returns_real_xlsx_file(): void
    {
        [, $admin] = $this->companyUser('main');

        Sanctum::actingAs($admin);

        $response = $this->get('/api/v1/company/reports/patients?format=xlsx');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_pdf_export_returns_real_pdf_file(): void
    {
        [, $admin] = $this->companyUser('main');

        Sanctum::actingAs($admin);

        $response = $this->get('/api/v1/company/reports/patients?format=pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_report_subscription_create_and_update_sets_next_run_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-12 10:00:00'));
        [, $admin] = $this->companyUser('main');

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/company/reports/subscriptions/patients', [
            'frequency' => ReportSubscription::FREQUENCY_DAILY,
            'format' => 'xlsx',
            'filters' => ['status' => Patient::STATUS_ACTIVE],
        ])
            ->assertOk()
            ->assertJsonPath('data.frequency', ReportSubscription::FREQUENCY_DAILY)
            ->assertJsonPath('data.format', 'xlsx')
            ->assertJsonPath('data.next_run_at', Carbon::now()->addDay()->toIso8601String());

        $this->putJson('/api/v1/company/reports/subscriptions/patients', [
            'frequency' => ReportSubscription::FREQUENCY_OFF,
            'format' => 'csv',
        ])
            ->assertOk()
            ->assertJsonPath('data.frequency', ReportSubscription::FREQUENCY_OFF)
            ->assertJsonPath('data.next_run_at', null);

        Carbon::setTestNow();
    }

    public function test_weekly_and_monthly_subscription_next_run_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-12 10:00:00'));
        [, $admin] = $this->companyUser('main');

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/company/reports/subscriptions/appointments', [
            'frequency' => ReportSubscription::FREQUENCY_WEEKLY,
            'format' => 'csv',
        ])
            ->assertOk()
            ->assertJsonPath('data.next_run_at', Carbon::now()->addWeek()->toIso8601String());

        $this->putJson('/api/v1/company/reports/subscriptions/interventions-financial', [
            'frequency' => ReportSubscription::FREQUENCY_MONTHLY,
            'format' => 'pdf',
        ])
            ->assertOk()
            ->assertJsonPath('data.next_run_at', Carbon::now()->addMonth()->toIso8601String());

        Carbon::setTestNow();
    }

    public function test_reports_send_due_skips_off_and_sends_due_subscription(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-05-12 10:00:00'));
        [$company, $admin] = $this->companyUser('main');
        $this->patient($company, 'Marko', 'Markovic');

        ReportSubscription::query()->create([
            'user_id' => $admin->id,
            'company_id' => $company->id,
            'report_key' => 'appointments',
            'frequency' => ReportSubscription::FREQUENCY_OFF,
            'format' => 'csv',
            'filters' => [],
            'next_run_at' => Carbon::now()->subMinute(),
        ]);
        $due = ReportSubscription::query()->create([
            'user_id' => $admin->id,
            'company_id' => $company->id,
            'report_key' => 'patients',
            'frequency' => ReportSubscription::FREQUENCY_DAILY,
            'format' => 'csv',
            'filters' => [],
            'next_run_at' => Carbon::now()->subMinute(),
        ]);

        $this->artisan('reports:send-due')
            ->expectsOutput('Poslato izvestaja: 1')
            ->assertExitCode(0);

        Mail::assertSent(ReportMail::class, 1);
        $this->assertNotNull($due->fresh()->last_sent_at);
        $this->assertEquals(Carbon::now()->addDay()->toDateTimeString(), $due->fresh()->next_run_at->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * @return array{Company, User}
     */
    private function companyUser(string $suffix): array
    {
        $company = Company::query()->create([
            'name' => "Company {$suffix}",
            'address' => "Address {$suffix}",
            'email' => "{$suffix}@example.com",
            'phone' => '060123456',
        ]);

        $user = $this->user($company, User::ROLE_COMPANY_ADMIN, "admin-{$suffix}");

        return [$company, $user];
    }

    private function user(Company $company, string $role, string $suffix): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'name' => "User {$suffix}",
            'first_name' => 'User',
            'last_name' => ucfirst($suffix),
            'phone' => '060123456',
            'role' => $role,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function patient(Company $company, string $firstName, string $lastName, array $overrides = []): Patient
    {
        return Patient::query()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => '060111222',
            'email' => strtolower($firstName).'@example.com',
            'manual_status' => Patient::STATUS_ACTIVE,
        ], $overrides));
    }

    /**
     * @return list<string>
     */
    private function csvHeader(string $content): array
    {
        $line = strtok($content, "\n");

        return str_getcsv((string) $line);
    }
}
