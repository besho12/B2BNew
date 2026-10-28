<?php

namespace App\Providers;

use App\Support\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use App\Services\PortalMailTemplates;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ResetPassword::createUrlUsing(fn ($user, string $token) => url('/reset-password?'.http_build_query([
            'token' => $token, 'email' => $user->getEmailForPasswordReset(),
        ])));

        VerifyEmail::createUrlUsing(fn ($user) => URL::temporarySignedRoute(
            'auth.email.verify', now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        ));

        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            $templates = app(PortalMailTemplates::class);
            $template = $templates->get('verify_email');
            $values = ['USER_NAME' => $notifiable->name, 'URL' => $url];
            return (new MailMessage)
                ->subject($templates->renderSubject($template['subject'], $values))
                ->line($templates->render($template['body'], $values))
                ->action('Verify email address', $url)
                ->line('If you did not create an account, no further action is required.');
        });
    }
}
