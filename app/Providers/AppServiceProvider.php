<?php

namespace App\Providers;

use Cloudinary\Cloudinary;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Sanctum::ignoreMigrations();

        $this->app->singleton(Cloudinary::class, function (): Cloudinary {
            $cloudName = config('services.cloudinary.cloud_name');
            $apiKey = config('services.cloudinary.api_key');
            $apiSecret = config('services.cloudinary.api_secret');

            if (
                ! is_string($cloudName) || $cloudName === ''
                || ! is_string($apiKey) || $apiKey === ''
                || ! is_string($apiSecret) || $apiSecret === ''
            ) {
                throw new RuntimeException(
                    'Cloudinary no esta configurado. Define CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY y CLOUDINARY_API_SECRET.',
                );
            }

            return new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => [
                    'secure' => true,
                ],
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
