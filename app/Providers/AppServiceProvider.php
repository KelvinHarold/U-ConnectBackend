<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
 use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
class AppServiceProvider extends ServiceProvider
{


public function boot(): void
{
    RateLimiter::for('login', fn (Request $r) =>
    Limit::perMinute(5)->by($r->ip())
);

RateLimiter::for('register', fn (Request $r) =>
    Limit::perHour(30)->by($r->ip())
);
}
}
