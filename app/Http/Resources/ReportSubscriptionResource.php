<?php

namespace App\Http\Resources;

use App\Models\ReportSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReportSubscription
 */
class ReportSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_key' => $this->report_key,
            'frequency' => $this->frequency,
            'format' => $this->format,
            'filters' => $this->filters ?? [],
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'last_sent_at' => $this->last_sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
