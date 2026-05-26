<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use HasFactory;

    public const TYPE_CHECKUP = AppointmentType::CHECKUP->value;

    public const TYPE_INTERVENTION = AppointmentType::INTERVENTION->value;

    public const TYPE_CONTROL = AppointmentType::CONTROL->value;

    public const STATUS_SCHEDULED = AppointmentStatus::SCHEDULED->value;

    public const STATUS_COMPLETED = AppointmentStatus::COMPLETED->value;

    public const STATUS_CANCELLED = AppointmentStatus::CANCELLED->value;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'patient_id',
        'scheduled_by_user_id',
        'assigned_user_id',
        'starts_at',
        'ends_at',
        'type',
        'status',
        'cancel_reason',
        'notes',
        'google_event_id',
        'calendar_synced_at',
        'reminder_staff_at',
        'reminder_patient_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'calendar_synced_at' => 'datetime',
            'reminder_staff_at' => 'datetime',
            'reminder_patient_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PatientPayment::class);
    }
}
