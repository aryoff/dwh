<?php

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

Route::post('/inputInteraction', 'App\Http\Controllers\ApiController@ApiInputInteraction');
Route::post('/inputCustomer', 'App\Http\Controllers\ApiController@ApiInputCustomer');
Route::post('/inputPartnerData', 'App\Http\Controllers\ApiController@ApiInputPartnerData');