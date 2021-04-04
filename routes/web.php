<?php

use Andyabih\LaravelToUML\Http\Controllers\LaravelToUMLController;
use Illuminate\Support\Facades\Route;

Route::get(config('laravel-to-uml.route'), [LaravelToUMLController::class, 'index']);