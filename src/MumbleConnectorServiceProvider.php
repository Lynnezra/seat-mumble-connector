<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble;

use Illuminate\Support\Facades\Event;
use Seat\Services\AbstractSeatPlugin;

/**
 * Class MumbleConnectorServiceProvider
 */
class MumbleConnectorServiceProvider extends AbstractSeatPlugin
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->addRoutes();
        $this->addViews();
        $this->addTranslations();
        $this->addMigrations();
        $this->addCommands();
        
        // 注册事件监听器
        $this->registerEventListeners();
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/mumble-connector.config.php', 'mumble-connector.config'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/Config/seat-connector.config.php', 'seat-connector.drivers.mumble'
        );
    }

    /**
     * 注册路由
     */
    private function addRoutes(): void
    {
        if (!$this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }
    }

    /**
     * 注册视图
     */
    public function addViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'seat-mumble-connector');
    }

    /**
     * 注册翻译文件
     */
    private function addTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'seat-mumble-connector');
    }

    /**
     * 注册数据库迁移
     */
    private function addMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * 注册命令
     */
    private function addCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Lynnezra\Seat\Connector\Drivers\Mumble\Console\TestIceConnection::class,
                \Lynnezra\Seat\Connector\Drivers\Mumble\Console\ManageIceInterface::class,
                \Lynnezra\Seat\Connector\Drivers\Mumble\Console\InstallIceExtension::class,
            ]);
        }
    }

    /**
     * 注册事件监听器
     */
    private function registerEventListeners(): void
    {
        // 监听用户角色变化事件，自动更新Mumble权限
        Event::listen(
            \Seat\Web\Events\UserRoleUpdated::class,
            \Lynnezra\Seat\Connector\Drivers\Mumble\Listeners\UserRoleUpdatedListener::class
        );
        
        // 监听军团变化事件，自动更新频道权限
        Event::listen(
            \Seat\Eveapi\Events\CharacterCorporationChanged::class,
            \Lynnezra\Seat\Connector\Drivers\Mumble\Listeners\CorporationChangedListener::class
        );
    }

    /**
     * 返回插件名称
     */
    public function getName(): string
    {
        return 'Mumble Connector';
    }

    /**
     * 返回插件仓库地址
     */
    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/Lynnezra/seat-mumble-connector';
    }

    /**
     * 返回Packagist包名
     */
    public function getPackagistPackageName(): string
    {
        return 'seat-mumble-connector';
    }

    /**
     * 返回Packagist供应商名
     */
    public function getPackagistVendorName(): string
    {
        return 'lynnezra';
    }


}