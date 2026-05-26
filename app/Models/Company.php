<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'address',
        'email',
        'phone',
        'created_by_user_id',
        'payments_enabled',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'subscription_plan',
        'subscription_current_period_end',
        'subscription_cancel_at_period_end',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payments_enabled' => 'boolean',
            'subscription_current_period_end' => 'datetime',
            'subscription_cancel_at_period_end' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PatientPayment::class);
    }
}
