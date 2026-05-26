<?php

namespace App\Models;

use App\Enums\PatientTaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientTask extends Model
{
    use HasFactory;

    public const STATUS_OPEN = PatientTaskStatus::OPEN->value;

    public const STATUS_DONE = PatientTaskStatus::DONE->value;

    public const STATUS_CANCELLED = PatientTaskStatus::CANCELLED->value;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'patient_id',
        'created_by_user_id',
        'assigned_to_user_id',
        'closed_by_user_id',
        'description',
        'due_date',
        'status',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'closed_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
