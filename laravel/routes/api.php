<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeadImportController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/lead-imports/{id}',[LeadImportController::class, 'show']);
Route::post('/lead-imports',[LeadImportController::class, 'store']);
Route::get('/lead-imports/{id}/failed-records',[LeadImportController::class, 'downloadFailedRecords']);


