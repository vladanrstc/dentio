<?php

namespace App\Services\Calendar;

use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleCalendarSyncService implements CalendarSyncServiceInterface
{
    public function syncAppointment(Appointment $appointment): ?string
    {
        $accessToken = config('services.google_calendar.access_token');
        $calendarId = config('services.google_calendar.calendar_id');

        if (! is_string($accessToken) || ! is_string($calendarId) || $accessToken === '' || $calendarId === '') {
            Log::warning('Google Calendar is not configured. Appointment remains in app only.', [
                'appointment_id' => $appointment->id,
            ]);

            return null;
        }

        $payload = [
            'summary' => 'Stomatoloski termin - '.$appointment->patient->fullName(),
            'description' => $appointment->notes ?: 'Termin iz Dentio aplikacije',
            'start' => ['dateTime' => $appointment->starts_at->toIso8601String()],
            'end' => ['dateTime' => ($appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes(30))->toIso8601String()],
        ];

        try {
            $endpoint = 'https://www.googleapis.com/calendar/v3/calendars/'.urlencode($calendarId).'/events';
            $response = $appointment->google_event_id
                ? Http::withToken($accessToken)->patch($endpoint.'/'.$appointment->google_event_id, $payload)
                : Http::withToken($accessToken)->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('Google Calendar sync failed.', [
                    'appointment_id' => $appointment->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('id');
        } catch (Throwable $exception) {
            Log::error('Google Calendar sync exception.', [
                'appointment_id' => $appointment->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}

