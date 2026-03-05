<?php

namespace Native\Mobile\Iap;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IapServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('nativephp-iap')
            ->hasConfigFile('iap');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Iap::class, function () {
            return new Iap;
        });
    }

    public function packageBooted(): void
    {
        $this->registerProductsFromConfig();
    }

    protected function registerProductsFromConfig(): void
    {
        $products = config('iap.products', []);

        if (! empty($products)) {
            app(Iap::class)->register($products);
        }
    }
}
