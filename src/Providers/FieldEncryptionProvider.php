<?php


namespace FieldEncryption\Providers;


use FieldEncryption\Command\SysCommand;
use FieldEncryption\Utils\ConfigUtils;
use FieldEncryption\Utils\DecryptUtils;
use FieldEncryption\Utils\EncryptionUtils;
use Illuminate\Support\ServiceProvider;

class FieldEncryptionProvider extends ServiceProvider
{
    /**
     * 在注册后启动服务
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SysCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . "/../../config/field_encryption.php", 'field_encryption'
        );

        $this->registerAliases();
        $this->app->singleton('field_encryption.decrypt', function () {
            return new DecryptUtils($this->config('aes_key'), $this->config('aes_pre'), $this->config('aes_tail'));
        });

        $this->app->singleton('field_encryption.encryption', function () {
            return new EncryptionUtils($this->config('aes_key'), $this->config('aes_pre'), $this->config('aes_tail'));
        });
        $this->app->singleton('field_encryption.config_util', function () {
            return new ConfigUtils(config("field_encryption"));
        });

    }

    /**
     * Bind some aliases.
     *
     * @return void
     */
    protected function registerAliases(): void
    {
        $this->app->alias('field_encryption.decrypt', DecryptUtils::class);
        $this->app->alias('field_encryption.encryption', EncryptionUtils::class);
        $this->app->alias('field_encryption.config_util', ConfigUtils::class);
    }

    protected function config($key, $default = null)
    {
        return config("field_encryption.$key", $default);
    }
}
