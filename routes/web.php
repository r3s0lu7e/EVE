<?php

use App\Http\Controllers\EveAuthController;
use App\Livewire\Assets;
use App\Livewire\Calculator;
use App\Livewire\Dashboard;
use App\Livewire\ProductDetail;
use App\Livewire\ProductList;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/products', ProductList::class)->name('products.index');
Route::get('/product/{typeId}', ProductDetail::class)->name('product.show');
Route::get('/assets', Assets::class)->name('assets.index');
Route::get('/calculator', Calculator::class)->name('calculator');

Route::get('/auth/eve/redirect', [EveAuthController::class, 'redirect'])->name('eve.redirect');
Route::get('/auth/eve/callback', [EveAuthController::class, 'callback'])->name('eve.callback');
Route::post('/auth/eve/logout', [EveAuthController::class, 'logout'])->name('eve.logout');
