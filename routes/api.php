<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TemplateController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::post('login', [AuthController::class, 'login']);
Route::get('/countries', [CountryController::class, 'index']);

Route::middleware(['auth:api'])->group(function () {
    Route::resource('/items', ItemController::class);
    Route::resource('/families', FamilyController::class);
    Route::resource('/customers', CustomerController::class);
    Route::resource('/quotes', QuoteController::class);
    Route::resource('/invoices', InvoiceController::class);
    Route::post('/invoices/print', [InvoiceController::class, 'print_invoice']);

    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/templates/save', [TemplateController::class, 'save_template']);
    Route::post('/logout', [AuthController::class, 'logout']);
});