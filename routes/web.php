<?php

use App\Filament\Pages\Auth\TenantRegister;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard/{tenant}/register', TenantRegister::class)
    ->name('filament.tenant.register');
