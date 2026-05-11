<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\PatientApiController;
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

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthApiController::class, 'me'])->name('me');
        Route::post('/logout', [AuthApiController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardApiController::class, 'index'])->name('dashboard');
        Route::get('/staff', [StaffApiController::class, 'index'])->name('staff.index');

        Route::prefix('/company')->name('company.')->middleware('role:company_admin,dentist,nurse')->group(function (): void {
            Route::get('/patients', [PatientApiController::class, 'index'])->name('patients.index');
            Route::get('/patients/{patientId}', [PatientApiController::class, 'show'])->name('patients.show');
            Route::post('/patients', [PatientApiController::class, 'store'])->name('patients.store');
            Route::put('/patients/{patientId}', [PatientApiController::class, 'update'])->name('patients.update');
            Route::post('/patients/{patientId}/appointments', [PatientApiController::class, 'storeAppointment'])->name('patients.appointments.store');
            Route::post('/patients/{patientId}/interventions', [PatientApiController::class, 'storeIntervention'])->name('patients.interventions.store');
            Route::post('/patients/{patientId}/tasks', [PatientApiController::class, 'storeTask'])->name('patients.tasks.store');
            Route::patch('/patients/{patientId}/tasks/{taskId}/complete', [PatientApiController::class, 'completeTask'])->name('patients.tasks.complete');
            Route::patch('/patients/{patientId}/status', [PatientApiController::class, 'updateStatus'])->name('patients.status.update');
        });
    });
});
