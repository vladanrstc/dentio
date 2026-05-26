<?php

namespace App\Providers;

use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\InterventionRepositoryInterface;
use App\Repositories\Contracts\InviteRepositoryInterface;
use App\Repositories\Contracts\PatientPortalInviteRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentAppointmentRepository;
use App\Repositories\Eloquent\EloquentCompanyRepository;
use App\Repositories\Eloquent\EloquentInterventionRepository;
use App\Repositories\Eloquent\EloquentInviteRepository;
use App\Repositories\Eloquent\EloquentPatientPortalInviteRepository;
use App\Repositories\Eloquent\EloquentPatientRepository;
use App\Repositories\Eloquent\EloquentPatientTaskRepository;
use App\Repositories\Eloquent\EloquentReminderRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Services\AppointmentService;
use App\Services\AuthService;
use App\Services\Calendar\CalendarSyncServiceInterface;
use App\Services\Calendar\GoogleCalendarSyncService;
use App\Services\ClientPortalService;
use App\Services\CompanyTeamService;
use App\Services\Contracts\AppointmentServiceInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\ClientPortalServiceInterface;
use App\Services\Contracts\CompanyTeamServiceInterface;
use App\Services\Contracts\InterventionServiceInterface;
use App\Services\Contracts\PatientPortalInviteServiceInterface;
use App\Services\Contracts\PatientServiceInterface;
use App\Services\Contracts\ReportServiceInterface;
use App\Services\Contracts\StripeGatewayInterface;
use App\Services\Contracts\StripePaymentServiceInterface;
use App\Services\Contracts\StripeSubscriptionServiceInterface;
use App\Services\InterventionService;
use App\Services\PatientPortalInviteService;
use App\Services\PatientService;
use App\Services\Reports\ReportsService;
use App\Services\StripeGateway;
use App\Services\StripePaymentService;
use App\Services\StripeSubscriptionService;
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
        $this->app->bind(PatientPortalInviteRepositoryInterface::class, EloquentPatientPortalInviteRepository::class);
        $this->app->bind(AppointmentRepositoryInterface::class, EloquentAppointmentRepository::class);
        $this->app->bind(InterventionRepositoryInterface::class, EloquentInterventionRepository::class);
        $this->app->bind(PatientTaskRepositoryInterface::class, EloquentPatientTaskRepository::class);
        $this->app->bind(ReminderRepositoryInterface::class, EloquentReminderRepository::class);
        $this->app->bind(CalendarSyncServiceInterface::class, GoogleCalendarSyncService::class);
        $this->app->bind(AppointmentServiceInterface::class, AppointmentService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(ClientPortalServiceInterface::class, ClientPortalService::class);
        $this->app->bind(CompanyTeamServiceInterface::class, CompanyTeamService::class);
        $this->app->bind(InterventionServiceInterface::class, InterventionService::class);
        $this->app->bind(PatientServiceInterface::class, PatientService::class);
        $this->app->bind(PatientPortalInviteServiceInterface::class, PatientPortalInviteService::class);
        $this->app->bind(ReportServiceInterface::class, ReportsService::class);
        $this->app->bind(StripeGatewayInterface::class, StripeGateway::class);
        $this->app->bind(StripePaymentServiceInterface::class, StripePaymentService::class);
        $this->app->bind(StripeSubscriptionServiceInterface::class, StripeSubscriptionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
