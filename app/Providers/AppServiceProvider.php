<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // FIFO is the default cost-basis method; swap here to change it globally.
        $this->app->bind(
            \App\Services\CostBasis\CostBasisStrategy::class,
            \App\Services\CostBasis\FifoStrategy::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('eveonline', \SocialiteProviders\Eveonline\Provider::class);
        });
    }
}
