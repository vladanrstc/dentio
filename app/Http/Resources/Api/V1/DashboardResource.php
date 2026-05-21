<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        if (isset($summary['upcoming_appointments'])) {
            $summary['upcoming_appointments'] = AppointmentResource::collection($summary['upcoming_appointments']);
        }

        return $summary;
    }
}
