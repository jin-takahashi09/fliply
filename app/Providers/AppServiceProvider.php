<?php

namespace App\Providers;

use App\Models\Word;
use Illuminate\Support\Facades\Route;
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
        Route::bind('word', function (string $value) {
            $user = auth()->user();

            if ($user === null) {
                abort(404);
            }

            return $user->words()->whereKey($value)->firstOrFail();
        });
    }
}
