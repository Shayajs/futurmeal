<?php

namespace App\Providers;

use App\Socialite\BrightShieldProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $socialite = $this->app->make(SocialiteFactory::class);

        $socialite->extend('brightshield', function ($app) use ($socialite) {
            $config = $app['config']['services.brightshield'];

            return $socialite->buildProvider(BrightShieldProvider::class, $config);
        });
    }
}
