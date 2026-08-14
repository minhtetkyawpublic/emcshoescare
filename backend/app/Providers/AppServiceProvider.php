<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $cookiePath = self::cookiePathFromScriptName((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($cookiePath !== null) {
            config(['session.path' => $cookiePath]);
        }
    }

    public static function cookiePathFromScriptName(string $scriptName): ?string
    {
        $apiDirectory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if (basename($apiDirectory) !== 'api') {
            return null;
        }

        $applicationDirectory = rtrim(str_replace('\\', '/', dirname($apiDirectory)), '/');

        return $applicationDirectory === '' ? '/' : $applicationDirectory.'/';
    }
}
