<?php

use App\Http\Controllers\Api\PatientApiController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyInviteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InterventionController;
use App\Http\Controllers\InviteAcceptanceController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientStatusController;
use App\Http\Controllers\PatientTaskController;
use App\Http\Controllers\PlatformAdminController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login.show');
    }

    return auth()->user()->role === User::ROLE_PLATFORM_ADMIN
        ? redirect()->route('admin.dashboard')
        : redirect()->route('dashboard.index');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login.show');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');

    Route::get('/invites/accept/{token}', [InviteAcceptanceController::class, 'show'])->name('invites.accept.show');
    Route::post('/invites/accept/{token}', [InviteAcceptanceController::class, 'store'])->name('invites.accept.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:company_admin,dentist,nurse')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
        Route::get('/patients/{patientId}', [PatientController::class, 'show'])->name('patients.show');
        Route::put('/patients/{patientId}', [PatientController::class, 'update'])->name('patients.update');

        Route::post('/patients/{patientId}/appointments', [AppointmentController::class, 'store'])->name('patients.appointments.store');
        Route::post('/patients/{patientId}/interventions', [InterventionController::class, 'store'])->name('patients.interventions.store');
        Route::patch('/patients/{patientId}/status', [PatientStatusController::class, 'update'])->name('patients.status.update');
        Route::post('/patients/{patientId}/tasks', [PatientTaskController::class, 'store'])->name('patients.tasks.store');
        Route::patch('/patients/{patientId}/tasks/{taskId}/complete', [PatientTaskController::class, 'complete'])->name('patients.tasks.complete');

        Route::middleware('role:company_admin')->group(function (): void {
            Route::get('/team/invites', [CompanyInviteController::class, 'index'])->name('team.invites.index');
            Route::post('/team/invites', [CompanyInviteController::class, 'store'])->name('team.invites.store');
        });

        Route::prefix('/api/company')->name('api.company.')->group(function (): void {
            Route::get('/patients', [PatientApiController::class, 'index'])->name('patients.index');
            Route::get('/patients/{patientId}', [PatientApiController::class, 'show'])->name('patients.show');
        });
    });

    Route::middleware('role:platform_admin')
        ->prefix('/admin')
        ->name('admin.')
        ->group(function (): void {
            Route::get('/dashboard', [PlatformAdminController::class, 'dashboard'])->name('dashboard');
            Route::post('/invites/company-owner', [PlatformAdminController::class, 'inviteOwner'])->name('invites.company-owner.store');
            Route::get('/companies/{companyId}', [PlatformAdminController::class, 'company'])->name('companies.show');
        });
});
