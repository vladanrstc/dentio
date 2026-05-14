<?php

namespace Tests\Feature;

use App\Mail\DailySuperadminReportMail;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DailySuperadminReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_generates_yesterday_report_and_queues_mail_to_superadmin(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-05-13 10:00:00'));
        config(['mail.super_admin.address' => 'superadmin@example.com']);

        $data = $this->reportData();

        $this->artisan('reports:send-daily-superadmin')
            ->expectsOutput('Dnevni izvestaj je dodat u red za slanje na: superadmin@example.com')
            ->expectsOutput('Datum izvestaja: 2026-05-12')
            ->assertExitCode(0);

        Mail::assertQueued(DailySuperadminReportMail::class, function (DailySuperadminReportMail $mail) use ($data): bool {
            $report = $mail->report;

            $this->assertTrue($mail->hasTo('superadmin@example.com'));
            $this->assertSame('2026-05-12', $report['date']);
            $this->assertSame(2, $report['appointments_count']);
            $this->assertSame(1, $report['checkups_count']);
            $this->assertSame(60.0, $report['total_outstanding_debt']);
            $this->assertCount(2, $report['interventions']);

            $csv = $report['csv'];
            $this->assertStringContainsString('Daily Superadmin Report', $csv);
            $this->assertStringContainsString('Date,2026-05-12', $csv);
            $this->assertStringContainsString('Appointments,2', $csv);
            $this->assertStringContainsString('Checkups,1', $csv);
            $this->assertStringContainsString('"Total outstanding debt",60.00', $csv);
            $this->assertStringContainsString('Yesterday procedure', $csv);
            $this->assertStringContainsString('Yesterday paid procedure', $csv);
            $this->assertStringNotContainsString('Today procedure', $csv);
            $this->assertStringNotContainsString('Older procedure', $csv);

            $mail->assertHasAttachedData(
                $csv,
                'daily-superadmin-report-2026-05-12.csv',
                ['mime' => 'text/csv'],
            );

            return $data['patient']->fullName() === 'Alice Patient';
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(): array
    {
        $company = Company::query()->create([
            'name' => 'Dentio Demo',
            'address' => 'Demo Street',
            'email' => 'demo@example.com',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_DENTIST,
            'first_name' => 'Dana',
            'last_name' => 'Dentist',
        ]);

        $patient = Patient::query()->create([
            'company_id' => $company->id,
            'primary_dentist_id' => $user->id,
            'first_name' => 'Alice',
            'last_name' => 'Patient',
            'address' => 'Patient Street',
            'phone' => '123',
            'email' => 'alice@example.com',
            'manual_status' => Patient::STATUS_ACTIVE,
        ]);

        Appointment::query()->create($this->appointmentPayload($company, $patient, $user, [
            'starts_at' => Carbon::parse('2026-05-12 09:00:00'),
            'ends_at' => Carbon::parse('2026-05-12 09:30:00'),
            'type' => Appointment::TYPE_CHECKUP,
        ]));
        Appointment::query()->create($this->appointmentPayload($company, $patient, $user, [
            'starts_at' => Carbon::parse('2026-05-12 11:00:00'),
            'ends_at' => Carbon::parse('2026-05-12 11:30:00'),
            'type' => Appointment::TYPE_CONTROL,
        ]));
        Appointment::query()->create($this->appointmentPayload($company, $patient, $user, [
            'starts_at' => Carbon::parse('2026-05-13 09:00:00'),
            'ends_at' => Carbon::parse('2026-05-13 09:30:00'),
            'type' => Appointment::TYPE_CHECKUP,
        ]));
        Appointment::query()->create($this->appointmentPayload($company, $patient, $user, [
            'starts_at' => Carbon::parse('2026-05-11 09:00:00'),
            'ends_at' => Carbon::parse('2026-05-11 09:30:00'),
            'type' => Appointment::TYPE_CHECKUP,
        ]));

        Intervention::query()->create($this->interventionPayload($company, $patient, $user, [
            'title' => 'Yesterday procedure',
            'description' => 'Done yesterday',
            'intervention_date' => '2026-05-12',
            'total_cost' => 100,
            'paid_amount' => 40,
        ]));
        Intervention::query()->create($this->interventionPayload($company, $patient, $user, [
            'title' => 'Yesterday paid procedure',
            'intervention_date' => '2026-05-12',
            'total_cost' => 50,
            'paid_amount' => 100,
        ]));
        Intervention::query()->create($this->interventionPayload($company, $patient, $user, [
            'title' => 'Today procedure',
            'intervention_date' => '2026-05-13',
            'total_cost' => 500,
            'paid_amount' => 0,
        ]));
        Intervention::query()->create($this->interventionPayload($company, $patient, $user, [
            'title' => 'Older procedure',
            'intervention_date' => '2026-05-11',
            'total_cost' => 300,
            'paid_amount' => 0,
        ]));

        return compact('company', 'user', 'patient');
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function appointmentPayload(Company $company, Patient $patient, User $user, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $user->id,
            'assigned_user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-05-12 09:00:00'),
            'ends_at' => Carbon::parse('2026-05-12 09:30:00'),
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_COMPLETED,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function interventionPayload(Company $company, Patient $patient, User $user, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'performed_by_user_id' => $user->id,
            'title' => 'Procedure',
            'description' => null,
            'next_step' => null,
            'intervention_date' => '2026-05-12',
            'total_cost' => 100,
            'paid_amount' => 0,
        ], $overrides);
    }
}
