<?php

use App\Http\Controllers\Api\PatientApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Dentio API radi',
    ]);
});

Route::middleware('auth')->prefix('/v1/company')->name('api.v1.company.')->group(function (): void {
    Route::get('/patients', [PatientApiController::class, 'index'])->name('patients.index');
    Route::get('/patients/{patientId}', [PatientApiController::class, 'show'])->name('patients.show');
});