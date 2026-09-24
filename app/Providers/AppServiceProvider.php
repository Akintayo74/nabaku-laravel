<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('donations', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
    }
}
