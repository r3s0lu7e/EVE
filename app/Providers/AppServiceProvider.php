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

        // Broker fee / provider tax rates for per-trade fee estimation (sales tax
        // is linked exactly, not rated). See config/eve.php.
        $this->app->bind(
            \App\Services\CostBasis\FeeAllocator::class,
            fn () => new \App\Services\CostBasis\FeeAllocator(
                (float) config('eve.broker_fee_rate'),
                (float) config('eve.market_provider_tax_rate'),
            ),
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
