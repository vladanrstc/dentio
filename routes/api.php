<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ClientPortalApiController;
use App\Http\Controllers\Api\CompanyBillingApiController;
use App\Http\Controllers\Api\CompanyPaymentApiController;
use App\Http\Controllers\Api\CompanyTeamApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\InviteAcceptanceApiController;
use App\Http\Controllers\Api\PatientApiController;
use App\Http\Controllers\Api\PatientPortalInviteApiController;
use App\Http\Controllers\Api\PlatformAdminApiController;
use App\Http\Controllers\Api\ReportsApiController;
use App\Http\Controllers\Api\StaffApiController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Dentio API radi',
    ]);
});

Route::prefix('/v1')->name('api.v1.')->group(function (): void {
    Route::post('/login', [AuthApiController::class, 'login'])->name('login');
    Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
    Route::get('/client/invites/{token}', [PatientPortalInviteApiController::class, 'show'])->name('client.invites.show');
    Route::post('/client/invites/{token}/accept', [PatientPortalInviteApiController::class, 'accept'])->name('client.invites.accept');
    Route::get('/invites/accept/{token}', [InviteAcceptanceApiController::class, 'show'])->name('invites.accept.show');
    Route::post('/invites/accept/{token}', [InviteAcceptanceApiController::class, 'store'])->name('invites.accept.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthApiController::class, 'me'])->name('me');
        Route::post('/logout', [AuthApiController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardApiController::class, 'index'])->middleware('role:company_admin,dentist,nurse')->name('dashboard');
        Route::get('/staff', [StaffApiController::class, 'index'])->middleware('role:company_admin,dentist,nurse')->name('staff.index');

        Route::prefix('/client')->name('client.')->middleware('role:client')->group(function (): void {
            Route::get('/me', [ClientPortalApiController::class, 'me'])->name('me');
            Route::get('/dashboard', [ClientPortalApiController::class, 'dashboard'])->name('dashboard');
            Route::get('/appointments', [ClientPortalApiController::class, 'appointments'])->name('appointments.index');
            Route::get('/interventions', [ClientPortalApiController::class, 'interventions'])->name('interventions.index');
            Route::get('/tasks', [ClientPortalApiController::class, 'tasks'])->name('tasks.index');
        });

        Route::prefix('/company')->name('company.')->middleware('role:company_admin,dentist,nurse')->group(function (): void {
            Route::get('/team', [CompanyTeamApiController::class, 'team'])->name('team.index');
            Route::get('/billing', [CompanyBillingApiController::class, 'show'])->name('billing.show');
            Route::post('/billing/checkout', [CompanyBillingApiController::class, 'checkout'])->middleware('role:company_admin')->name('billing.checkout');
            Route::post('/billing/portal', [CompanyBillingApiController::class, 'portal'])->middleware('role:company_admin')->name('billing.portal');
            Route::get('/payments', [CompanyPaymentApiController::class, 'index'])->name('payments.index');
            Route::post('/payments', [CompanyPaymentApiController::class, 'store'])->name('payments.store');
            Route::get('/reports/patients', [ReportsApiController::class, 'patients'])->name('reports.patients');
            Route::get('/reports/appointments', [ReportsApiController::class, 'appointments'])->name('reports.appointments');
            Route::get('/reports/interventions-financial', [ReportsApiController::class, 'interventionsFinancial'])->name('reports.interventions-financial');
            Route::get('/reports/subscriptions', [ReportsApiController::class, 'companySubscriptions'])->name('reports.subscriptions.index');
            Route::put('/reports/subscriptions/{reportKey}', [ReportsApiController::class, 'updateCompanySubscription'])->name('reports.subscriptions.update');
            Route::delete('/team/{user}', [CompanyTeamApiController::class, 'destroy'])->middleware('role:company_admin')->name('team.destroy');
            Route::get('/invites', [CompanyTeamApiController::class, 'invites'])->middleware('role:company_admin')->name('invites.index');
            Route::post('/invites', [CompanyTeamApiController::class, 'storeInvite'])->middleware('role:company_admin')->name('invites.store');
            Route::delete('/invites/{invite}', [CompanyTeamApiController::class, 'destroyInvite'])->middleware('role:company_admin')->name('invites.destroy');
            Route::post('/invites/{invite}/resend', [CompanyTeamApiController::class, 'resendInvite'])->middleware('role:company_admin')->name('invites.resend');

            Route::get('/patients', [PatientApiController::class, 'index'])->name('patients.index');
            Route::get('/patients/{patient}', [PatientApiController::class, 'show'])->name('patients.show');
            Route::post('/patients', [PatientApiController::class, 'store'])->name('patients.store');
            Route::put('/patients/{patient}', [PatientApiController::class, 'update'])->name('patients.update');
            Route::delete('/patients/{patient}', [PatientApiController::class, 'destroy'])->name('patients.destroy');
            Route::post('/patients/portal-invites', [PatientPortalInviteApiController::class, 'store'])->name('patients.portal-invites.store');

            Route::post('/patients/{patient}/appointments', [PatientApiController::class, 'storeAppointment'])->name('patients.appointments.store');
            Route::post('/patients/{patient}/interventions', [PatientApiController::class, 'storeIntervention'])->name('patients.interventions.store');
            Route::post('/patients/{patient}/tasks', [PatientApiController::class, 'storeTask'])->name('patients.tasks.store');
            Route::patch('/patients/{patient}/tasks/{task}/complete', [PatientApiController::class, 'completeTask'])->name('patients.tasks.complete');
            Route::patch('/patients/{patient}/status', [PatientApiController::class, 'updateStatus'])->name('patients.status.update');
            Route::post('/appointments/{appointment}/payment-session', [CompanyPaymentApiController::class, 'appointmentSession'])->name('appointments.payment-session');
            Route::patch('/appointments/{appointment}/cancel', [PatientApiController::class, 'cancelAppointment'])->name('appointments.cancel');
        });

        Route::prefix('/admin')->name('admin.')->middleware('role:platform_admin')->group(function (): void {
            Route::get('/dashboard', [PlatformAdminApiController::class, 'dashboard'])->name('dashboard');
            Route::get('/reports/companies', [ReportsApiController::class, 'adminCompanies'])->name('reports.companies');
            Route::get('/reports/subscriptions', [ReportsApiController::class, 'adminSubscriptions'])->name('reports.subscriptions.index');
            Route::put('/reports/subscriptions/{reportKey}', [ReportsApiController::class, 'updateAdminSubscription'])->name('reports.subscriptions.update');
            Route::get('/companies', [PlatformAdminApiController::class, 'companies'])->name('companies.index');
            Route::get('/companies/{company}', [PlatformAdminApiController::class, 'company'])->name('companies.show');
            Route::delete('/companies/{company}', [PlatformAdminApiController::class, 'destroyCompany'])->name('companies.destroy');
            Route::patch('/companies/{company}/payment-settings', [PlatformAdminApiController::class, 'updatePaymentSettings'])->name('companies.payment-settings.update');
            Route::post('/invite-owner', [PlatformAdminApiController::class, 'inviteOwner'])->name('invite-owner.store');
            Route::delete('/invites/{invite}', [PlatformAdminApiController::class, 'destroyInvite'])->name('invites.destroy');
            Route::post('/invites/{invite}/resend', [PlatformAdminApiController::class, 'resendInvite'])->name('invites.resend');
        });
    });
});
