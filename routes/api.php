<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlaneController;

Route::get('/planes', [PlaneController::class, 'index']);
