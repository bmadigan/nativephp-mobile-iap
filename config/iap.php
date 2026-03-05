<?php

return [

    /*
    |--------------------------------------------------------------------------
    | In-App Purchase Products
    |--------------------------------------------------------------------------
    |
    | Define your in-app purchase products here. These will be registered with
    | the native layer when the app boots. Each product should have a unique
    | identifier that matches what's configured in App Store Connect or
    | Google Play Console.
    |
    | Supported types: 'consumable', 'non_consumable', 'subscription'
    |
    */

    'products' => [
        // Example non-consumable (lifetime purchase)
        // 'app_lifetime' => [
        //     'type' => 'non_consumable',
        //     'name' => 'Lifetime Access',
        //     'description' => 'Unlock all features forever',
        // ],

        // Example subscription
        // 'app_monthly' => [
        //     'type' => 'subscription',
        //     'name' => 'Monthly Subscription',
        //     'description' => 'Access all premium features',
        // ],

        // Example consumable
        // 'credits_100' => [
        //     'type' => 'consumable',
        //     'name' => '100 Credits',
        //     'description' => 'Purchase 100 credits',
        // ],
    ],

];
