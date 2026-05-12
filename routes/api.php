<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CompanyTeamApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\InviteAcceptanceApiController;
use App\Http\Controllers\Api\PatientApiController;
use App\Http\Controllers\Api\PlatformAdminApiController;
use App\Http\Controllers\Api\StaffApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Dentio API radi',
    ]);
});

Route::prefix('/v1')->name('api.v1.')->group(function (): void {
    Route::post('/login', [AuthApiController::class, 'login'])->name('login');
    Route::get('/invites/accept/{token}', [InviteAcceptanceApiController::class, 'show'])->name('invites.accept.show');
    Route::post('/invites/accept/{token}', [InviteAcceptanceApiController::class, 'store'])->name('invites.accept.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthApiController::class, 'me'])->name('me');
        Route::post('/logout', [AuthApiController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardApiController::class, 'index'])->name('dashboard');
        Route::get('/staff', [StaffApiController::class, 'index'])->name('staff.index');

        Route::prefix('/company')->name('company.')->middleware('role:company_admin,dentist,nurse')->group(function (): void {
            Route::get('/team', [CompanyTeamApiController::class, 'team'])->name('team.index');
            Route::delete('/team/{userId}', [CompanyTeamApiController::class, 'destroy'])->middleware('role:company_admin')->name('team.destroy');
            Route::get('/invites', [CompanyTeamApiController::class, 'invites'])->middleware('role:company_admin')->name('invites.index');
            Route::post('/invites', [CompanyTeamApiController::class, 'storeInvite'])->middleware('role:company_admin')->name('invites.store');
            Route::delete('/invites/{inviteId}', [CompanyTeamApiController::class, 'destroyInvite'])->middleware('role:company_admin')->name('invites.destroy');
            Route::post('/invites/{inviteId}/resend', [CompanyTeamApiController::class, 'resendInvite'])->middleware('role:company_admin')->name('invites.resend');

            Route::get('/patients', [PatientApiController::class, 'index'])->name('patients.index');
            Route::get('/patients/{patientId}', [PatientApiController::class, 'show'])->name('patients.show');
            Route::post('/patients', [PatientApiController::class, 'store'])->name('patients.store');
            Route::put('/patients/{patientId}', [PatientApiController::class, 'update'])->name('patients.update');
            Route::delete('/patients/{patientId}', [PatientApiController::class, 'destroy'])->name('patients.destroy');

            Route::post('/patients/{patientId}/appointments', [PatientApiController::class, 'storeAppointment'])->name('patients.appointments.store');
            Route::post('/patients/{patientId}/interventions', [PatientApiController::class, 'storeIntervention'])->name('patients.interventions.store');
            Route::post('/patients/{patientId}/tasks', [PatientApiController::class, 'storeTask'])->name('patients.tasks.store');
            Route::patch('/patients/{patientId}/tasks/{taskId}/complete', [PatientApiController::class, 'completeTask'])->name('patients.tasks.complete');
            Route::patch('/patients/{patientId}/status', [PatientApiController::class, 'updateStatus'])->name('patients.status.update');
            Route::patch('/appointments/{appointmentId}/cancel', [PatientApiController::class, 'cancelAppointment'])->name('appointments.cancel');
        });

        Route::prefix('/admin')->name('admin.')->middleware('role:platform_admin')->group(function (): void {
            Route::get('/dashboard', [PlatformAdminApiController::class, 'dashboard'])->name('dashboard');
            Route::get('/companies', [PlatformAdminApiController::class, 'companies'])->name('companies.index');
            Route::get('/companies/{companyId}', [PlatformAdminApiController::class, 'company'])->name('companies.show');
            Route::delete('/companies/{companyId}', [PlatformAdminApiController::class, 'destroyCompany'])->name('companies.destroy');
            Route::post('/invite-owner', [PlatformAdminApiController::class, 'inviteOwner'])->name('invite-owner.store');
            Route::delete('/invites/{inviteId}', [PlatformAdminApiController::class, 'destroyInvite'])->name('invites.destroy');
            Route::post('/invites/{inviteId}/resend', [PlatformAdminApiController::class, 'resendInvite'])->name('invites.resend');
        });
    });
});
