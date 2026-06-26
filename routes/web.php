<?php

use App\Http\Controllers\EveAuthController;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');

Route::get('/auth/eve/redirect', [EveAuthController::class, 'redirect'])->name('eve.redirect');
Route::get('/auth/eve/callback', [EveAuthController::class, 'callback'])->name('eve.callback');
Route::post('/auth/eve/logout', [EveAuthController::class, 'logout'])->name('eve.logout');
