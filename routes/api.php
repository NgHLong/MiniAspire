<?php

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post("register", "Api\UserController@register");
Route::post('login', 'Api\UserController@login');

Route::group([
    'middleware' => ['jwt.verify'],
], function ($router) {
    Route::post('logout', 'Api\UserController@logout');
    Route::post('user', 'Api\UserController@user');
    Route::post('updateUser', 'Api\UserController@updateUser');
    Route::resource('loans', 'Api\LoansController')->only([
        'index','store', 'show', 'update', 'destroy'
    ]);
    Route::resource('repayments', 'Api\RepaymentsController')->only([
        'index','store', 'show', 'update'
    ]);
});

