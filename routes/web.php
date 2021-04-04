<?php

use Andyabih\LaravelToUML\Http\Controllers\LaravelToUMLController;
use Illuminate\Support\Facades\Route;

Route::get('/uml', [LaravelToUMLController::class, 'index']);