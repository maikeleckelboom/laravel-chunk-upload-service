<?php

namespace App\Providers;

use App\Models\Upload;
use App\Models\User;
use App\Observers\UploadObserver;
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
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        $this->tryLoginTestUser();

        // observe the Upload model
        Upload::observe(UploadObserver::class);
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

    /**
     * @return bool
     */
    private function isLocalEnv(): bool
    {
        return $this->app->environment('local');
    }

    /**
     * @param User $user
     */
    private function setAuthUser(User $user): void
    {
        $this->app['auth']->setUser($user);
    }
}
