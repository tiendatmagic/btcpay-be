<?php

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group([
    'middleware' => 'api',
], function ($router) {
    Route::get('/get-product', [Controller::class, 'getProduct']);
    Route::post('/create-product', [Controller::class, 'createProduct']);
    Route::post('/create-order', [Controller::class, 'createOrder']);
    Route::post('/get-order', [Controller::class, 'getOrder']);
    Route::post('/check-transaction', [Controller::class, 'updateOrder']);
});
