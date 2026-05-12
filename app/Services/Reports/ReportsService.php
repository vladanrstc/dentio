<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\ReportSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportsService
{
    private const STATUS_LABELS = [
        Patient::STATUS_ACTIVE => 'Aktivan',
        Patient::STATUS_INACTIVE => 'Neaktivan',
        Patient::STATUS_COMPLETED => 'Završen',
        Patient::STATUS_TRANSFERRED => 'Prebačen',
        Appointment::STATUS_SCHEDULED => 'Zakazan',
        Appointment::STATUS_CANCELLED => 'Otkazan',
        'pending' => 'Na čekanju',
        'accepted' => 'Prihvaćen',
        'revoked' => 'Opozvan',
        'expired' => 'Istekao',
    ];

    private const APPOINTMENT_TYPE_LABELS = [
        Appointment::TYPE_CHECKUP => 'Pregled',
        Appointment::TYPE_INTERVENTION => 'Intervencija',
        Appointment::TYPE_CONTROL => 'Kontrola',
    ];

    public const COMPANY_REPORTS = [
        'patients',
        'appointments',
        'interventions-financial',
    ];

    public const ADMIN_REPORTS = [
        'companies',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function forSubscription(ReportSubscription $subscription, array $filters = []): array
    {
        $request = Request::create('/', 'GET', $filters);

        return match ($subscription->report_key) {
            'patients' => $this->patients((int) $subscription->company_id, $request),
            'appointments' => $this->appointments((int) $subscription->company_id, $request),
            'interventions-financial' => $this->interventionsFinancial((int) $subscription->company_id, $request),
            'companies' => $this->adminCompanies(),
            default => throw new \InvalidArgumentException('Nepoznat report.'),
        };
    }

    /**
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function patients(int $companyId, Request $request): array
    {
        $hasOpenTasks = $this->booleanFilter($request, 'has_open_tasks');
        $hasDebt = $this->booleanFilter($request, 'has_debt');

        $query = Patient::query()
            ->where('company_id', $companyId)
            ->with(['primaryDentist', 'interventions'])
            ->withCount([
                'tasks as open_tasks_count' => fn (Builder $query) => $query->where('status', PatientTask::STATUS_OPEN),
            ]);

        $query->when($request->string('search')->toString() !== '', function (Builder $query) use ($request): void {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        });
        $query->when($request->filled('status'), fn (Builder $query) => $query->where('manual_status', $request->string('status')->toString()));
        $query->when($request->filled('primary_dentist_id'), fn (Builder $query) => $query->where('primary_dentist_id', (int) $request->input('primary_dentist_id')));
        $query->when($hasOpenTasks === true, fn (Builder $query) => $query->whereHas('tasks', fn (Builder $inner) => $inner->where('status', PatientTask::STATUS_OPEN)));
        $query->when($hasOpenTasks === false, fn (Builder $query) => $query->whereDoesntHave('tasks', fn (Builder $inner) => $inner->where('status', PatientTask::STATUS_OPEN)));
        $query->when($hasDebt === true, fn (Builder $query) => $query->whereHas('interventions', fn (Builder $inner) => $inner->whereRaw($this->outstandingBalanceExpression().' > 0')));
        $query->when($hasDebt === false, fn (Builder $query) => $query->whereDoesntHave('interventions', fn (Builder $inner) => $inner->whereRaw($this->outstandingBalanceExpression().' > 0')));

        $patients = $query->orderBy('last_name')->orderBy('first_name')->get();

        return [
            'filename' => 'patients-report',
            'headers' => ['ID', 'Ime i prezime', 'Email', 'Telefon', 'Status', 'Primarni stomatolog', 'Broj otvorenih taskova', 'Ukupno', 'Plaćeno', 'Dugovanje', 'Kreiran'],
            'rows' => $patients->map(function (Patient $patient): array {
                $total = (float) $patient->interventions->sum('total_cost');
                $paid = (float) $patient->interventions->sum('paid_amount');

                return [
                    $patient->id,
                    $patient->fullName(),
                    $patient->email,
                    $patient->phone,
                    $this->status($patient->manual_status),
                    $patient->primaryDentist?->fullName(),
                    (int) $patient->open_tasks_count,
                    $this->money($total),
                    $this->money($paid),
                    $this->money(max(0, $total - $paid)),
                    $this->dateTime($patient->created_at),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function appointments(int $companyId, Request $request): array
    {
        $query = Appointment::query()
            ->where('company_id', $companyId)
            ->with(['patient', 'assignedTo']);

        $query->when($request->filled('date_from'), fn (Builder $query) => $query->where('starts_at', '>=', Carbon::parse($request->input('date_from'))->startOfDay()));
        $query->when($request->filled('date_to'), fn (Builder $query) => $query->where('starts_at', '<=', Carbon::parse($request->input('date_to'))->endOfDay()));
        $query->when($request->filled('assigned_user_id'), fn (Builder $query) => $query->where('assigned_user_id', (int) $request->input('assigned_user_id')));
        $query->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()));
        $query->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()));

        return [
            'filename' => 'appointments-report',
            'headers' => ['Datum/vreme', 'Pacijent', 'Doktor', 'Tip', 'Status', 'Napomena'],
            'rows' => $query->orderBy('starts_at')->get()->map(fn (Appointment $appointment): array => [
                $this->dateTime($appointment->starts_at),
                $appointment->patient?->fullName(),
                $appointment->assignedTo?->fullName(),
                $this->appointmentType($appointment->type),
                $this->status($appointment->status),
                $appointment->notes,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function interventionsFinancial(int $companyId, Request $request): array
    {
        $hasOutstanding = $this->booleanFilter($request, 'has_outstanding');

        $query = Intervention::query()
            ->where('company_id', $companyId)
            ->with(['patient', 'performedBy']);

        $query->when($request->filled('date_from'), fn (Builder $query) => $query->where('intervention_date', '>=', Carbon::parse($request->input('date_from'))->startOfDay()));
        $query->when($request->filled('date_to'), fn (Builder $query) => $query->where('intervention_date', '<=', Carbon::parse($request->input('date_to'))->endOfDay()));
        $query->when($request->filled('performed_by_user_id'), fn (Builder $query) => $query->where('performed_by_user_id', (int) $request->input('performed_by_user_id')));
        $query->when($hasOutstanding === true, fn (Builder $query) => $query->whereRaw($this->outstandingBalanceExpression().' > 0'));
        $query->when($hasOutstanding === false, fn (Builder $query) => $query->whereRaw($this->outstandingBalanceExpression().' <= 0'));

        return [
            'filename' => 'interventions-financial-report',
            'headers' => ['Datum', 'Pacijent', 'Intervencija', 'Izvršio', 'Ukupna cena', 'Plaćeno', 'Dugovanje', 'Sledeći korak'],
            'rows' => $query->orderBy('intervention_date')->get()->map(fn (Intervention $intervention): array => [
                $this->date($intervention->intervention_date),
                $intervention->patient?->fullName(),
                $intervention->title,
                $intervention->performedBy?->fullName(),
                $this->money((float) $intervention->total_cost),
                $this->money((float) $intervention->paid_amount),
                $this->money($intervention->outstandingAmount()),
                $intervention->next_step,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function adminCompanies(): array
    {
        $companies = Company::query()
            ->withCount([
                'users as staff_count' => fn (Builder $query) => $query->whereIn('role', [User::ROLE_COMPANY_ADMIN, User::ROLE_DENTIST, User::ROLE_NURSE]),
                'patients',
                'appointments as active_appointments_count' => fn (Builder $query) => $query->where('status', Appointment::STATUS_SCHEDULED),
                'invites as pending_invites_count' => fn (Builder $query) => $query
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', Carbon::now()),
            ])
            ->orderBy('name')
            ->get();

        return [
            'filename' => 'companies-report',
            'headers' => ['Kompanija', 'Email', 'Telefon', 'Broj osoblja', 'Broj pacijenata', 'Broj aktivnih termina', 'Broj pozivnica na čekanju', 'Datum kreiranja'],
            'rows' => $companies->map(fn (Company $company): array => [
                $company->name,
                $company->email,
                $company->phone,
                (int) $company->staff_count,
                (int) $company->patients_count,
                (int) $company->active_appointments_count,
                (int) $company->pending_invites_count,
                $this->dateTime($company->created_at),
            ])->values()->all(),
        ];
    }

    private function booleanFilter(Request $request, string $key): ?bool
    {
        if (! $request->filled($key)) {
            return null;
        }

        return filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function status(?string $status): ?string
    {
        return $status === null ? null : (self::STATUS_LABELS[$status] ?? $status);
    }

    private function appointmentType(?string $type): ?string
    {
        return $type === null ? null : (self::APPOINTMENT_TYPE_LABELS[$type] ?? $type);
    }

    private function outstandingBalanceExpression(): string
    {
        return '(CAST(total_cost AS REAL) - CAST(paid_amount AS REAL))';
    }

    private function dateTime(?Carbon $date): ?string
    {
        return $date?->timezone(config('app.timezone'))->format('d.m.Y. H:i');
    }

    private function date(?Carbon $date): ?string
    {
        return $date?->timezone(config('app.timezone'))->format('d.m.Y.');
    }
}
