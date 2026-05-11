<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_TRANSFERRED = 'transferred';

    public const STATUS_COMPLETED = 'completed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'primary_dentist_id',
        'manual_status_changed_by_user_id',
        'first_name',
        'last_name',
        'address',
        'phone',
        'email',
        'manual_status',
        'manual_status_reason',
        'manual_status_changed_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manual_status_changed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function primaryDentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_dentist_id');
    }

    public function manualStatusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_status_changed_by_user_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PatientTask::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PatientStatusLog::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}

