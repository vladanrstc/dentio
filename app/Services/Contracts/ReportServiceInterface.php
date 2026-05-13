<?php

namespace App\Services\Contracts;

use App\Models\ReportSubscription;
use Illuminate\Http\Request;

interface ReportServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function forSubscription(ReportSubscription $subscription, array $filters = []): array;

    /**
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function patients(int $companyId, Request $request): array;

    /**
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function appointments(int $companyId, Request $request): array;

    /**
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function interventionsFinancial(int $companyId, Request $request): array;

    /**
     * @return array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}
     */
    public function adminCompanies(): array;
}
