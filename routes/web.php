<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

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

Route::redirect('/', '/login');

Route::controller(LoginController::class)->group(function () {
    Route::get('/register', 'viewRegister')->name('register');
    Route::post('/register', 'register')->name('store.register');
    Route::get('/login', 'viewLogin')->name('login');
    Route::post('/login', 'login')->name('store.login');
    Route::get('/logout', 'logout')->name('logout');

});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [LoginController::class, 'dashboard']);

    Route::controller(UserController::class)->group(function () {
        Route::resource('/users', UserController::class);
        Route::get('/users-datatable', 'datatableUser');
        Route::get('/changepass',  'changePass');
        Route::post('/changepass/{id}',  'changePassSubmit')->name('changepass');
    });
});
