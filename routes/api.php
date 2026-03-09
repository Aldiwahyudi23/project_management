<?php

use App\Http\Controllers\Api\AppInspection\Auth\AuthController;
use App\Http\Controllers\Api\AppInspection\Job\FormInspectionController;
use App\Http\Controllers\Api\AppInspection\Job\JobController;
use App\Http\Controllers\API\VehicleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware(['auth:sanctum', 'inspector'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});



Route::prefix('app-inspection')->middleware(['auth:sanctum'])->group(function () {
    
    // Jobs endpoints
    Route::prefix('jobs')->group(function () {

        // Jumlah Tugas yang aktif untuk Mneu 
        Route::get('jobcount', [JobController::class, 'jobCount']);
        
        // Draft jobs
        Route::get('draft', [JobController::class, 'getDraftJobs']);
        
        // Process jobs
        Route::get('process', [JobController::class, 'getProcessJobs']);
        
        // Completed jobs (with year/month filters)
        Route::get('completed', [JobController::class, 'getCompletedJobs']);
        
        // Single job detail
        Route::get('{id}', [JobController::class, 'show']);
        
        // Update job status
        Route::patch('{id}/status', [JobController::class, 'updateStatus']);
                
    });
    Route::prefix('form-inspection')->group(function(){
        // Get form inspection
        Route::get('/start/{id}', [FormInspectionController::class, 'getFormInspection']);

        //Upload Gambar 
        Route::post('/upload-image', [FormInspectionController::class, 'uploadImage']);
    
        // Hapus gambar
        Route::delete('/image/{id}', [FormInspectionController::class, 'deleteImage']);

        // Hapus data Item
        Route::delete('/{inspectionId}/items/{itemId}', [FormInspectionController::class, 'deleteItem']);

        //get data item untuk null 
        Route::get('/{inspectionId}/images/unassigned', [FormInspectionController::class, 'getUnassignedImages']);

        //SaveFormInspection
        Route::post('/{inspectionId}/save', [FormInspectionController::class, 'saveFormInspection']);

        
    });
});


Route::get('/test', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API jalan'
    ]);
});

// 4|X7UtX7mRImr0FDmFekFRKxjMZQvXaeihxcroOM1a20ef4c00