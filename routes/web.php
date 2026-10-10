<?php

use Illuminate\Support\Facades\Route;

// SPA catch-all: Vue Router owns every page URL, so a browser refresh on any
// route returns the same shell. API, Sanctum and health-check paths are excluded.
Route::view('/{any?}', 'app')->where('any', '^(?!(api|sanctum|up)(/|$)).*$');
