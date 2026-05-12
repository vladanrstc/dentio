<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Policies\AppointmentPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\InterventionPolicy;
use App\Policies\PatientPolicy;
use App\Policies\PatientTaskPolicy;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\InterventionRepositoryInterface;
use App\Repositories\Contracts\InviteRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentAppointmentRepository;
use App\Repositories\Eloquent\EloquentCompanyRepository;
use App\Repositories\Eloquent\EloquentInterventionRepository;
use App\Repositories\Eloquent\EloquentInviteRepository;
use App\Repositories\Eloquent\EloquentPatientRepository;
use App\Repositories\Eloquent\EloquentPatientTaskRepository;
use App\Repositories\Eloquent\EloquentReminderRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Services\Calendar\CalendarSyncServiceInterface;
use App\Services\Calendar\GoogleCalendarSyncService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CompanyRepositoryInterface::class, EloquentCompanyRepository::class);
        $this->app->bind(InviteRepositoryInterface::class, EloquentInviteRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(PatientRepositoryInterface::class, EloquentPatientRepository::class);
        $this->app->bind(AppointmentRepositoryInterface::class, EloquentAppointmentRepository::class);
        $this->app->bind(InterventionRepositoryInterface::class, EloquentInterventionRepository::class);
        $this->app->bind(PatientTaskRepositoryInterface::class, EloquentPatientTaskRepository::class);
        $this->app->bind(ReminderRepositoryInterface::class, EloquentReminderRepository::class);
        $this->app->bind(CalendarSyncServiceInterface::class, GoogleCalendarSyncService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(Intervention::class, InterventionPolicy::class);
        Gate::policy(PatientTask::class, PatientTaskPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
    }
}
