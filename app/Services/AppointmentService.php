<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Reminder;
use App\Models\User;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use App\Services\Calendar\CalendarSyncServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AppointmentService
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository,
        private readonly ReminderRepositoryInterface $reminderRepository,
        private readonly CalendarSyncServiceInterface $calendarSyncService,
    ) {}

    public function schedule(User $actor, Patient $patient, array $data): Appointment
    {
        if ($patient->company_id !== $this->companyIdOrFail($actor)) {
            throw new RuntimeException('Pacijent ne pripada kompaniji korisnika.');
        }

        $this->ensureDoctorIsAvailable(
            (int) $patient->company_id,
            $data['assigned_user_id'] ?? null,
            $data['starts_at'],
            $data['ends_at'] ?? null,
        );

        $appointment = $this->appointmentRepository->create([
            'company_id' => $patient->company_id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $actor->id,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'type' => $data['type'],
            'status' => Appointment::STATUS_SCHEDULED,
            'notes' => $data['notes'] ?? null,
            'reminder_staff_at' => $data['reminder_staff_at'] ?? null,
            'reminder_patient_at' => $data['reminder_patient_at'] ?? null,
        ]);

        $appointment->loadMissing(['patient', 'assignedTo']);
        $googleEventId = $this->calendarSyncService->syncAppointment($appointment);
        if ($googleEventId !== null) {
            $appointment->google_event_id = $googleEventId;
            $appointment->calendar_synced_at = Carbon::now();
            $appointment->save();
        }

        $this->createReminderRowsForAppointment($appointment, $patient, $actor);

        return $appointment;
    }

    public function cancel(User $actor, Appointment $appointment, ?string $reason = null): Appointment
    {
        if ($appointment->company_id !== $this->companyIdOrFail($actor)) {
            throw new RuntimeException('Termin ne pripada kompaniji korisnika.');
        }

        $appointment->status = Appointment::STATUS_CANCELLED;
        $appointment->cancel_reason = $reason;
        $appointment->save();

        return $appointment;
    }

    private function ensureDoctorIsAvailable(int $companyId, mixed $assignedUserId, mixed $startsAt, mixed $endsAt): void
    {
        if ($assignedUserId === null || $endsAt === null) {
            return;
        }

        $startsAt = Carbon::parse($startsAt);
        $endsAt = Carbon::parse($endsAt);

        $overlaps = Appointment::query()
            ->where('company_id', $companyId)
            ->where('assigned_user_id', $assignedUserId)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('starts_at', '<', $endsAt)
            ->where(function ($query) use ($startsAt): void {
                $query->where('ends_at', '>', $startsAt)
                    ->orWhere(function ($inner) use ($startsAt): void {
                        $inner->whereNull('ends_at')
                            ->where('starts_at', '>', $startsAt);
                    });
            })
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'assigned_user_id' => ['Izabrani doktor već ima zakazan termin u tom periodu.'],
            ]);
        }
    }

    private function createReminderRowsForAppointment(Appointment $appointment, Patient $patient, User $actor): void
    {
        $rows = [];
        $timestamp = Carbon::now();

        if ($appointment->reminder_staff_at instanceof Carbon) {
            $recipient = $appointment->assignedTo?->email ?? $actor->email;
            if (is_string($recipient) && $recipient !== '') {
                $rows[] = [
                    'company_id' => $appointment->company_id,
                    'patient_id' => $patient->id,
                    'appointment_id' => $appointment->id,
                    'intervention_id' => null,
                    'recipient_email' => $recipient,
                    'recipient_type' => Reminder::TYPE_STAFF,
                    'remind_at' => $appointment->reminder_staff_at,
                    'subject' => 'Podsetnik za termin - '.$patient->fullName(),
                    'body' => 'Zakazan termin je '.$appointment->starts_at->format('d.m.Y H:i').'.',
                    'status' => Reminder::STATUS_PENDING,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        if ($appointment->reminder_patient_at instanceof Carbon && is_string($patient->email) && $patient->email !== '') {
            $rows[] = [
                'company_id' => $appointment->company_id,
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'intervention_id' => null,
                'recipient_email' => $patient->email,
                'recipient_type' => Reminder::TYPE_PATIENT,
                'remind_at' => $appointment->reminder_patient_at,
                'subject' => 'Podsetnik za vas stomatoloski termin',
                'body' => 'Termin je '.$appointment->starts_at->format('d.m.Y H:i').'.',
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
