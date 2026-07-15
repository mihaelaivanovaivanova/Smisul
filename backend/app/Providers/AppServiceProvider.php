<?php

namespace App\Providers;

use App\Events\Favorite\ProductBackInStock;
use App\Events\Favorite\ProductPriceDropped;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusChanged;
use App\Events\Review\ReviewApproved;
use App\Events\Review\ReviewRejected;
use App\Events\Review\ReviewReplied;
use App\Listeners\NotifyFavoritesOfBackInStock;
use App\Listeners\NotifyFavoritesOfPriceDrop;
use App\Listeners\SendOrderPlacedNotifications;
use App\Listeners\SendOrderStatusEmails;
use App\Listeners\SendReviewApprovedNotification;
use App\Listeners\SendReviewRejectedNotification;
use App\Listeners\SendReviewReplyNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Keep utf8mb4 while remaining compatible with shared-hosting MySQL /
        // MariaDB installations whose maximum index key is 1000 bytes.
        Schema::defaultStringLength(191);

        $this->configureRateLimiting();
        $this->configureAuthNotificationUrls();

        // Laravel 11+ no longer ships a default EventServiceProvider, so the
        // framework's own "send verification email on registration" listener
        // has to be wired up explicitly.
        Event::listen(Registered::class, SendEmailVerificationNotification::class);
        Event::listen(OrderPlaced::class, SendOrderPlacedNotifications::class);
        Event::listen(OrderStatusChanged::class, SendOrderStatusEmails::class);
        Event::listen(ProductPriceDropped::class, NotifyFavoritesOfPriceDrop::class);
        Event::listen(ProductBackInStock::class, NotifyFavoritesOfBackInStock::class);
        Event::listen(ReviewApproved::class, SendReviewApprovedNotification::class);
        Event::listen(ReviewRejected::class, SendReviewRejectedNotification::class);
        Event::listen(ReviewReplied::class, SendReviewReplyNotification::class);
    }

    /**
     * Named rate limiters used by the authentication routes.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function ($request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('register', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password-reset', function ($request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(3)->by($key);
        });

        RateLimiter::for('email-verification', function ($request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('checkout', function ($request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Generous and IP-scoped, not user-scoped — the caller here is
        // iCard's own servers, not a customer.
        RateLimiter::for('webhooks', function ($request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('contact', function ($request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('funnel-leads', function ($request) {
            return Limit::perMinute(3)->by($request->ip());
        });
    }

    /**
     * Point password reset and email verification links at the frontend SPA
     * instead of Laravel's default backend web routes.
     */
    private function configureAuthNotificationUrls(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return sprintf(
                '%s/reset-password?token=%s&email=%s',
                rtrim(config('app.frontend_url'), '/'),
                $token,
                urlencode($notifiable->getEmailForPasswordReset()),
            );
        });

        VerifyEmail::createUrlUsing(function ($notifiable) {
            $id = $notifiable->getKey();
            $hash = sha1($notifiable->getEmailForVerification());

            // Generate the real signed backend URL purely to obtain a valid
            // "expires" + "signature" pair, then re-host the link on the
            // frontend so the user stays inside the SPA. The frontend reads
            // these same values and calls the backend verification endpoint
            // directly, so the signature still validates.
            $signedBackendUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                ['id' => $id, 'hash' => $hash],
            );

            $query = parse_url($signedBackendUrl, PHP_URL_QUERY);

            return sprintf(
                '%s/verify-email/%s/%s?%s',
                rtrim(config('app.frontend_url'), '/'),
                $id,
                $hash,
                $query,
            );
        });
    }
}
