<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\Reminder;
use App\Models\User;
use App\Repositories\Contracts\InterventionRepositoryInterface;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class InterventionService
{
    public function __construct(
        private readonly InterventionRepositoryInterface $interventionRepository,
        private readonly PatientTaskRepositoryInterface $patientTaskRepository,
        private readonly ReminderRepositoryInterface $reminderRepository,
    ) {
    }

    public function record(User $actor, Patient $patient, array $data): Intervention
    {
        if ($patient->company_id !== $this->companyIdOrFail($actor)) {
            throw new RuntimeException('Pacijent ne pripada kompaniji korisnika.');
        }

        $intervention = $this->interventionRepository->create([
            'company_id' => $patient->company_id,
            'patient_id' => $patient->id,
            'appointment_id' => $data['appointment_id'] ?? null,
            'performed_by_user_id' => $data['performed_by_user_id'] ?? $actor->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'next_step' => $data['next_step'] ?? null,
            'intervention_date' => $data['intervention_date'],
            'total_cost' => $data['total_cost'] ?? 0,
            'paid_amount' => $data['paid_amount'] ?? 0,
        ]);

        if (! empty($data['next_step'])) {
            $this->patientTaskRepository->create([
                'company_id' => $patient->company_id,
                'patient_id' => $patient->id,
                'created_by_user_id' => $actor->id,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? $patient->primary_dentist_id,
                'description' => $data['next_step'],
                'due_date' => $data['task_due_date'] ?? null,
                'status' => PatientTask::STATUS_OPEN,
            ]);
        }

        $this->createReminderRowsForIntervention($intervention, $patient, $actor, $data);

        return $intervention;
    }

    private function createReminderRowsForIntervention(Intervention $intervention, Patient $patient, User $actor, array $data): void
    {
        $rows = [];
        $timestamp = Carbon::now();

        if (! empty($data['reminder_staff_at'])) {
            $rows[] = [
                'company_id' => $intervention->company_id,
                'patient_id' => $patient->id,
                'appointment_id' => null,
                'intervention_id' => $intervention->id,
                'recipient_email' => $actor->email,
                'recipient_type' => Reminder::TYPE_STAFF,
                'remind_at' => $data['reminder_staff_at'],
                'subject' => 'Podsetnik: '.$intervention->title.' - '.$patient->fullName(),
                'body' => $intervention->next_step ?: 'Nastavak terapije.',
                'status' => Reminder::STATUS_PENDING,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if (! empty($data['reminder_patient_at']) && is_string($patient->email) && $patient->email !== '') {
            $rows[] = [
                'company_id' => $intervention->company_id,
                'patient_id' => $patient->id,
                'appointment_id' => null,
                'intervention_id' => $intervention->id,
                'recipient_email' => $patient->email,
                'recipient_type' => Reminder::TYPE_PATIENT,
                'remind_at' => $data['reminder_patient_at'],
                'subject' => 'Podsetnik za dalji tretman',
                'body' => $intervention->next_step ?: 'Molimo da dodjete na sledecu planiranu kontrolu.',
                'status' => Reminder::STATUS_PENDING,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        $this->reminderRepository->createMany($rows);
    }

    private function companyIdOrFail(User $user): int
    {
        if (! $user->company_id) {
            throw new RuntimeException('Korisnik nema dodeljenu kompaniju.');
        }

        return (int) $user->company_id;
    }
}

