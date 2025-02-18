<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')
                . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Load custom media migrations
        $this->addMediaMigrations();

        // Login test user in local environment
        $this->tryLoginTestUser();
    }

    private function addMediaMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/Media');
    }

    private function tryLoginTestUser(): void
    {
        if ($this->isLocalEnv() && !$this->app->runningInConsole()) {
            $this->loginTestUser();
        }
    }

    private function loginTestUser(): void
    {
        $this->setAuthUser(User::find(1));
    }

    private function setAuthUser(User $user): void
    {
        $this->app['auth']->setUser($user);
    }

    private function isLocalEnv(): bool
    {
        return $this->app->environment('local');
    }
}
