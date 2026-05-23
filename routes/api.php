<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\InviteController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PatientPortalController;
use App\Http\Controllers\Api\V1\PlatformAdminController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/invites/{token}', [InviteController::class, 'show']);
    Route::post('/invites/{token}/accept', [InviteController::class, 'accept']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware('role:company_admin,dentist,nurse')->group(function (): void {
            Route::get('/dashboard', [CompanyController::class, 'dashboard']);
            Route::get('/company/staff', [CompanyController::class, 'staff']);
            Route::get('/company/team', [CompanyController::class, 'team']);
            Route::get('/company/invites', [CompanyController::class, 'invites'])
                ->middleware('role:company_admin');
            Route::post('/company/invites', [CompanyController::class, 'invite'])
                ->middleware('role:company_admin');

            Route::get('/patients', [PatientController::class, 'index']);
            Route::post('/patients', [PatientController::class, 'store']);
            Route::get('/patients/{patient}', [PatientController::class, 'show']);
            Route::put('/patients/{patient}', [PatientController::class, 'update']);
            Route::delete('/patients/{patient}', [PatientController::class, 'destroy']);
            Route::patch('/patients/{patient}/status', [PatientController::class, 'updateStatus']);
            Route::post('/patients/{patient}/tasks', [PatientController::class, 'storeTask']);
            Route::patch('/patients/{patient}/tasks/{task}/complete', [PatientController::class, 'completeTask']);
            Route::post('/patients/{patient}/appointments', [PatientController::class, 'storeAppointment']);
            Route::post('/patients/{patient}/interventions', [PatientController::class, 'storeIntervention']);
            Route::post('/patients/{patient}/portal-invite', [PatientController::class, 'invite']);

            Route::get('/reports/patients', [ReportController::class, 'patients']);
            Route::get('/reports/appointments', [ReportController::class, 'appointments']);
            Route::get('/reports/interventions-financial', [ReportController::class, 'interventionsFinancial']);
        });

        Route::middleware('role:platform_admin')->prefix('admin')->group(function (): void {
            Route::get('/dashboard', [PlatformAdminController::class, 'dashboard']);
            Route::get('/companies/{company}', [PlatformAdminController::class, 'company']);
            Route::post('/invites/company-owner', [PlatformAdminController::class, 'inviteCompanyOwner']);
        });

        Route::middleware('role:patient')->prefix('patient-portal')->group(function (): void {
            Route::get('/me', [PatientPortalController::class, 'show']);
        });
    });
});
