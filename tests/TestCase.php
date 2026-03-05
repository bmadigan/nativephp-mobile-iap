<?php

namespace Native\Mobile\Iap\Tests;

use Native\Mobile\Iap\IapServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            IapServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Iap' => \Native\Mobile\Iap\Facades\Iap::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('iap.products', [
            'test_lifetime' => [
                'type' => 'non_consumable',
                'name' => 'Test Lifetime',
                'description' => 'Test lifetime product',
            ],
            'test_monthly' => [
                'type' => 'subscription',
                'name' => 'Test Monthly',
                'description' => 'Test monthly subscription',
            ],
        ]);
    }
}
