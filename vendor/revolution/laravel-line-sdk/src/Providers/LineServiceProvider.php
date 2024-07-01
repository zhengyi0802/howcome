<?php

namespace Revolution\Line\Providers;

use Illuminate\Support\ServiceProvider;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use Revolution\Line\Contracts\BotFactory;
use Revolution\Line\Contracts\NotifyFactory;
use Revolution\Line\Contracts\WebhookHandler;
use Revolution\Line\Messaging\BotClient;
use Revolution\Line\Messaging\Http\Actions\WebhookEventDispatcher;
use Revolution\Line\Notifications\LineNotifyClient;

class LineServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/line.php',
            'line'
        );

        $this->registerBot();

        $this->registerNotify();

        $this->registerWebhookHandler();
    }

    /**
     * Bot.
     *
     * @return void
     */
    protected function registerBot(): void
    {
        $this->app->singleton(MessagingApiApi::class, function ($app) {
            $config = (new Configuration())->setAccessToken(config('line.bot.channel_token'));

            return new MessagingApiApi(config: $config);
        });

        $this->app->singleton(BotFactory::class, BotClient::class);
    }

    /**
     * Notify.
     *
     * @return void
     */
    protected function registerNotify(): void
    {
        $this->app->singleton(NotifyFactory::class, LineNotifyClient::class);
    }

    /**
     * Default WebhookHandler.
     *
     * @return void
     */
    protected function registerWebhookHandler(): void
    {
        $this->app->singleton(WebhookHandler::class, WebhookEventDispatcher::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configurePublishing();
    }

    /**
     * Configure publishing for the package.
     *
     * @return void
     */
    protected function configurePublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return; // @codeCoverageIgnore
        }

        $this->publishes([
            __DIR__.'/../../config/line.php' => $this->app->configPath('line.php'),
        ], 'line-config');

        $this->publishes([
            __DIR__.'/../../stubs/listeners' => $this->app->path('Listeners'),
        ], 'line-listeners');
    }
}
