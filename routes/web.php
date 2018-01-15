<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

#Route::get('/coin', 'CoinController@index');

Route::group(array('prefix' => 'xcoin', 'middleware' => []), function () {

    Route::get('/', 'CoinController@index');
    Route::get('/balance', 'CoinController@balance');
    Route::get('/daemon', 'CoinController@daemon');
    Route::get('/transaction', 'CoinController@transactions');

});