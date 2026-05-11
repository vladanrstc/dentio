<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\PatientApiController;
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

        Route::prefix('/company')->name('company.')->group(function (): void {
            Route::get('/patients', [PatientApiController::class, 'index'])->name('patients.index');
            Route::get('/patients/{patientId}', [PatientApiController::class, 'show'])->name('patients.show');
            Route::post('/patients', [PatientApiController::class, 'store'])->name('patients.store');
            Route::put('/patients/{patientId}', [PatientApiController::class, 'update'])->name('patients.update');
        });
    });
});
