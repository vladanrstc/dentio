<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_PLATFORM_ADMIN = 'platform_admin';

    public const ROLE_COMPANY_ADMIN = 'company_admin';

    public const ROLE_DENTIST = 'dentist';

    public const ROLE_NURSE = 'nurse';

    public const ROLE_PATIENT = 'patient';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'first_name',
        'last_name',
        'phone',
        'role',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function patientsAsPrimaryDentist(): HasMany
    {
        return $this->hasMany(Patient::class, 'primary_dentist_id');
    }

    public function scheduledAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'scheduled_by_user_id');
    }

    public function assignedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'assigned_user_id');
    }

    public function performedInterventions(): HasMany
    {
        return $this->hasMany(Intervention::class, 'performed_by_user_id');
    }

    public function createdInvites(): HasMany
    {
        return $this->hasMany(Invite::class, 'invited_by_user_id');
    }

    public function acceptedInvites(): HasMany
    {
        return $this->hasMany(Invite::class, 'accepted_by_user_id');
    }

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function fullName(): string
    {
        $firstName = trim((string) $this->first_name);
        $lastName = trim((string) $this->last_name);
        $fullName = trim($firstName.' '.$lastName);

        return $fullName !== '' ? $fullName : $this->name;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === self::ROLE_COMPANY_ADMIN;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === self::ROLE_PLATFORM_ADMIN;
    }

    public function isPatient(): bool
    {
        return $this->role === self::ROLE_PATIENT;
    }
}
