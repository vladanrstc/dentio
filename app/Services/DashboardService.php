<?php

namespace App\Services;

use App\Exceptions\MissingCompanyContextException;
use App\Models\User;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\InterventionRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ReminderRepositoryInterface;

class DashboardService
{
    public function __construct(
        private readonly PatientRepositoryInterface $patientRepository,
        private readonly AppointmentRepositoryInterface $appointmentRepository,
        private readonly ReminderRepositoryInterface $reminderRepository,
        private readonly InterventionRepositoryInterface $interventionRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(User $user): array
    {
        $companyId = $this->companyIdOrFail($user);

        return [
            'patients_total' => $this->patientRepository->countForCompany($companyId),
            'patients_with_open_tasks' => $this->patientRepository->countWithOpenTasksForCompany($companyId),
            'appointments_today' => $this->appointmentRepository->countTodayForCompany($companyId),
            'reminders_due' => $this->reminderRepository->countDueForCompany($companyId),
            'outstanding_amount' => $this->interventionRepository->outstandingTotalForCompany($companyId),
            'upcoming_appointments' => $this->appointmentRepository->upcomingForCompany($companyId, 12),
        ];
    }

    private function companyIdOrFail(User $user): int
    {
        if (! $user->company_id) {
            throw new MissingCompanyContextException(__('errors.missing_company'));
        }

        return (int) $user->company_id;
    }
}
