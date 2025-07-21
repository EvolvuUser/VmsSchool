<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\FormController;

Route::post('/submit-form', [FormController::class, 'submit']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\Api\VisitorController;


Route::post('/visitors', [VisitorController::class, 'store']);
Route::get('/visitors/{id}', [VisitorController::class, 'show']);
Route::get('/visitors/mobile/{mobileno}', [VisitorController::class, 'findByMobile']);
Route::get('/role_get', [VisitorController::class, 'role']);
Route::post('/get_emailotp', [VisitorController::class, 'getEmailOtp']);
Route::post('/verifyemailotp', [VisitorController::class, 'Verifyemailotp']);
Route::get('/check_visitor_status', [VisitorController::class, 'checkVisitorStatus']);
Route::get('/get_all_visitors', [VisitorController::class, 'getAllVisitors']);
Route::post('/get_all_visitor', [VisitorController::class, 'getAllVisitor']);
Route::post('/save_intime/{id}', [VisitorController::class, 'saveInTime']);
Route::post('/save_outtime/{id}', [VisitorController::class, 'saveOutTime']);

Route::post('/generate_token_url', [VisitorController::class, 'generateTokenAndUrl']);
Route::get('/verify_token', [VisitorController::class, 'verifyToken']);
Route::post('/invalidate_token', [VisitorController::class, 'invalidateToken']);
