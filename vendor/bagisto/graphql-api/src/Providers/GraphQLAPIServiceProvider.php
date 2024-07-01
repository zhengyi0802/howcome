<?php

namespace Webkul\GraphQLAPI\Providers;

use Illuminate\Routing\Router;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Webkul\GraphQLAPI\BagistoGraphql;
use Webkul\GraphQLAPI\Console\Commands\Install;
use Webkul\GraphQLAPI\Facades\BagistoGraphql as BagistoGraphqlFacade;
use Webkul\GraphQLAPI\Http\Middleware\LocaleMiddleware;
use Webkul\GraphQLAPI\Http\Middleware\CurrencyMiddleware;
class GraphQLAPIServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot(Router $router)
    {
        include __DIR__.'/../Http/helpers.php';

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'bagisto_graphql');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'bagisto_graphql');

        // Model observer for admin user of Bagisto.
        $this->overrideModels();

        $this->publishesDefault();

        /* aliases */
        $router->aliasMiddleware('locale', LocaleMiddleware::class);
        $router->aliasMiddleware('currency', CurrencyMiddleware::class);

        if (request()->hasHeader('authorization')) {
            $headerValue = explode('Bearer ', request()->header('authorization'));

            if (isset($headerValue[1]) && $headerValue[1]) {
                request()->merge(['token' => $headerValue[1]]);
            }
        }
    }

    /**
     * Override the existing models
     */
    public function overrideModels()
    {
        // Customer Models
        $this->app->concord->registerModel(\Webkul\User\Contracts\Admin::class, \Webkul\GraphQLAPI\Models\Admin\Admin::class);

        // CurrencyExchangeRate Models
        $this->app->concord->registerModel(\Webkul\Core\Contracts\CurrencyExchangeRate::class, \Webkul\GraphQLAPI\Models\Setting\CurrencyExchangeRate::class);

        // Catalog Product Models
        $this->app->concord->registerModel(\Webkul\Product\Contracts\Product::class, \Webkul\GraphQLAPI\Models\Catalog\Product::class);

        // Catalog ProductDownloadableLink Models
        $this->app->concord->registerModel(\Webkul\Product\Contracts\ProductDownloadableLink::class, \Webkul\GraphQLAPI\Models\Catalog\ProductDownloadableLink::class);

        // Catalog ProductDownloadableSample Models
        $this->app->concord->registerModel(\Webkul\Product\Contracts\ProductDownloadableSample::class, \Webkul\GraphQLAPI\Models\Catalog\ProductDownloadableSample::class);

        // Category Model
        $this->app->concord->registerModel(\Webkul\Category\Models\Category::class, \Webkul\GraphQLAPI\Models\Catalog\Category::class);

        // CategoryTranslation Model
        $this->app->concord->registerModel(\Webkul\Category\Models\CategoryTranslation::class, \Webkul\GraphQLAPI\Models\Catalog\CategoryTranslation::class);

        // CatalogRule Models
        $this->app->concord->registerModel(\Webkul\CatalogRule\Contracts\CatalogRule::class, \Webkul\GraphQLAPI\Models\CatalogRule\CatalogRule::class);

        // CatalogRuleProduct Models
        $this->app->concord->registerModel(\Webkul\CatalogRule\Contracts\CatalogRuleProduct::class, \Webkul\GraphQLAPI\Models\CatalogRule\CatalogRuleProduct::class);

        // CartRule Coupon Models
        $this->app->concord->registerModel(\Webkul\CartRule\Contracts\CartRuleCoupon::class, \Webkul\GraphQLAPI\Models\CartRule\CartRuleCoupon::class);

        // Wishlist Models
        $this->app->concord->registerModel(\Webkul\Customer\Contracts\Wishlist::class, \Webkul\GraphQLAPI\Models\Customer\Wishlist::class);

        // Customer Models
        $this->app->concord->registerModel(\Webkul\Customer\Contracts\Customer::class, \Webkul\GraphQLAPI\Models\Customer\Customer::class);

        //  // Wishlist Models
        //  $this->app->concord->registerModel(\Webkul\Shop\Contracts\ThemeCustomization::class, \Webkul\GraphQLAPI\Models\Shop\ThemeCustomization::class);

        //  // Customer Models
        //  $this->app->concord->registerModel(\Webkul\Shop\Contracts\ThemeCustomizationTranslation::class, \Webkul\GraphQLAPI\Models\Shop\ThemeCustomizationTranslation::class);
    }

    /**
     * Publish all Default theme page.
     *
     * @return void
     */
    protected function publishesDefault()
    {
        /**
         * Publish the package pages.
         */
        $this->publishes([
            __DIR__ . '/../Resources/views/shop/default/emails/customer/registration.blade.php' => resource_path('themes/default/views/emails/customer/registration.blade.php'),

            __DIR__ . '/../Resources/views/shop/default/emails/customer/registration.blade.php' => __DIR__ .'/../../../../../packages/Webkul/Shop/src/Resources/views/emails/customer/registration.blade.php',

            /**
             * Publish the theme pages.
             */
            __DIR__ . '/../Repositories/Shop/ThemeCustomizationRepository.php' => __DIR__ .'/../../../../../packages/Webkul/Shop/src/Repositories/ThemeCustomizationRepository.php',

            __DIR__ . '/../Http/Controllers/Admin/Settings/ThemeController.php' => __DIR__ .'/../../../../../packages/Webkul/Admin/src/Http/Controllers/Settings/ThemeController.php',

            __DIR__ . '/../Resources/views/admin/settings/themes/edit.blade.php' => __DIR__ .'/../../../../../packages/Webkul/Admin/src/Resources/views/settings/themes/edit.blade.php',
        ], ['graphql-api-publish']);

        /**
         * Publish the lighthouse config page.
         */
        $this->publishes([
            __DIR__ . '/../Config/lighthouse.php' => config_path('lighthouse.php'),
        ], ['graphql-api-lighthouse']);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();

        $this->registerFacades();

        $this->registerConfig();
    }

    /**
     * Register the console commands of this package.
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Install::class,
            ]);
        }
    }

    /**
     * Register Bouncer as a singleton.
     *
     * @return void
     */
    protected function registerFacades()
    {
        $loader = AliasLoader::getInstance();

        $loader->alias('bagisto_graphql', BagistoGraphqlFacade::class);

        $this->app->singleton('bagisto_graphql', function () {
            return app()->make(BagistoGraphql::class);
        });

        $this->app->bind('cart', 'Webkul\GraphQLAPI\Cart');

        $this->app->bind(\Webkul\Checkout\Cart::class, \Webkul\GraphQLAPI\Cart::class);
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/menu.php',
            'menu.admin'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/acl.php',
            'acl'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/system.php',
            'core'
        );
    }
}
