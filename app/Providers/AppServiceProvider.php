<?php

namespace App\Providers;

use App\Models\Merchant;
use App\Models\MerchantPickupShipment;
use App\Models\Role;
use App\Models\Driver;
use App\Notifications\Channels\WhatsAppChannel;
use App\Observers\MerchantObserver;
use App\Observers\MerchantPickupShipmentObserver;
use App\Observers\RoleObserver;
use App\Observers\DriverObserver;
use App\Services\WhatsAppService;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppService::class, function ($app) {
            return new WhatsAppService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(ChannelManager::class)
            ->extend('whatsapp', function ($app) {
                return new WhatsAppChannel($app->make(WhatsAppService::class));
            });

        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });
        Schema::defaultStringLength(191);

        Merchant::observe(app(MerchantObserver::class));
        MerchantPickupShipment::observe(app(MerchantPickupShipmentObserver::class));
    }
}
