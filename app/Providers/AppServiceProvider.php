<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // WHY: ApiResponse already supplies the outer "data" key. Without this
        // call Laravel would add a second "data" layer around every resource.
        JsonResource::withoutWrapping();
    }
}
