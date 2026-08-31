<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\ComplaintController as AdminComplaintController;
use App\Http\Controllers\Api\V1\Admin\ContentBlockController as AdminContentBlockController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\FunnelController as AdminFunnelController;
use App\Http\Controllers\Api\V1\Admin\FunnelLeadController as AdminFunnelLeadController;
use App\Http\Controllers\Api\V1\Admin\ICardConfigurationController as AdminICardConfigurationController;
use App\Http\Controllers\Api\V1\Admin\LegalDocumentController as AdminLegalDocumentController;
use App\Http\Controllers\Api\V1\Admin\LogController as AdminLogController;
use App\Http\Controllers\Api\V1\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentOperationController as AdminPaymentOperationController;
use App\Http\Controllers\Api\V1\Admin\PriceController as AdminPriceController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ProductMediaController as AdminProductMediaController;
use App\Http\Controllers\Api\V1\Admin\ProductVariantController as AdminProductVariantController;
use App\Http\Controllers\Api\V1\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Api\V1\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\ShippingProviderConfigurationController as AdminShippingProviderConfigurationController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\Checkout\CheckoutController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\FunnelController;
use App\Http\Controllers\Api\V1\FunnelLeadController;
use App\Http\Controllers\Api\V1\LegalController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\Payment\ICardWebhookController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\ShipmentController;
use App\Http\Controllers\Api\V1\StoredPaymentMethodController;
use App\Http\Resources\UserResource;
use App\Services\ContentBlockService;
use App\Services\FunnelContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| All routes are versioned under /api/v1 so future breaking changes can
| ship as /api/v2 without disrupting existing clients. Route *names* are
| intentionally left unprefixed (e.g. "verification.verify") to match
| Laravel's own conventions, since the framework's password-reset and
| email-verification notifications resolve those exact route names.
|
*/

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->middleware('throttle:register')
            ->name('register');

        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:login')
            ->name('login');

        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:password-reset')
            ->name('password.email');

        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:password-reset')
            ->name('password.reset');

        Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:email-verification'])
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');

            Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:email-verification')
                ->name('verification.send');

            Route::get('/user', function (Request $request) {
                return new UserResource($request->user());
            })->name('auth.user');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [PasswordController::class, 'update'])->name('profile.password.update');
    });

    // Favorites: authenticated customers only (see FavoritePolicy) — a
    // guest gets 401 from auth:sanctum, an administrator gets 403 from the
    // policy. Every route is under /customer per the sprint spec, unlike
    // the flat /profile and /orders routes above.
    Route::prefix('customer')->middleware('auth:sanctum')->name('customer.')->group(function () {
        Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
        Route::post('/favorites', [FavoriteController::class, 'store'])->name('favorites.store');
        Route::get('/favorites/count', [FavoriteController::class, 'count'])->name('favorites.count');
        Route::get('/favorites/check/{productVariant}', [FavoriteController::class, 'check'])->name('favorites.check');
        Route::delete('/favorites/{favorite}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');

        // Reviews: "own reviews" CRUD — verified-purchase eligibility is
        // enforced in ReviewService::assertEligible(), not here (see
        // ReviewPolicy). Administrators get the same 403 a guest would get
        // 401 for, one layer up.
        Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
        Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    });

    // Marking a review helpful isn't customer-only (see ReviewPolicy) —
    // flat under auth:sanctum like /profile, not the /customer prefix.
    Route::middleware('auth:sanctum')->post('/reviews/{review}/helpful', [ReviewController::class, 'markHelpful'])
        ->name('reviews.helpful');

    // Public storefront: read-only, no auth required.
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{slug}/variants', [ProductController::class, 'variants'])->name('products.variants');
    Route::get('/products/{slug}/reviews', [ProductController::class, 'reviews'])->name('products.reviews.index');
    Route::get('/products/{slug}/reviews/summary', [ProductController::class, 'reviewsSummary'])->name('products.reviews.summary');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/categories/{slug}/products', [CategoryController::class, 'products'])->name('categories.products');

    Route::get('/content/homepage', [ContentController::class, 'homepage'])->name('content.homepage');

    // Funnel mode: public, unauthenticated — the storefront reads this at
    // boot to decide whether to render the normal homepage or the funnel
    // landing page (see FunnelService).
    Route::get('/funnel', [FunnelController::class, 'show'])->name('funnel.show');

    // Funnel lead capture (the landing page's email opt-in block) — no
    // auth, throttled like the contact form.
    Route::post('/funnel/leads', [FunnelLeadController::class, 'store'])
        ->middleware('throttle:funnel-leads')
        ->name('funnel.leads.store');

    // Public merchant identity/contact details for the footer — a strict
    // whitelist, never the full settings table.
    Route::get('/settings/public', [SettingController::class, 'show'])->name('settings.public');

    // Legal pages: public, no auth — every current document (superset of
    // checkout's required-only subset, see CheckoutController::legalDocuments).
    Route::get('/legal-documents', [LegalController::class, 'index'])->name('legal-documents.index');
    Route::get('/legal-documents/{slug}', [LegalController::class, 'show'])->name('legal-documents.show');

    // Cookie consent: open to guests (the banner appears before login) and
    // authenticated users alike — see ConsentController.
    Route::prefix('consent')->group(function () {
        Route::get('/cookies', [ConsentController::class, 'showCookiePreferences'])->name('consent.cookies.show');
        Route::post('/cookies', [ConsentController::class, 'storeCookiePreferences'])->name('consent.cookies.store');

        // Re-accepting Terms/Privacy after a published update — account
        // holders only (see UserResource::outstanding_legal_documents,
        // which is what tells the frontend to show the prompt this posts to).
        Route::post('/legal-documents/accept', [ConsentController::class, 'acceptOutstandingAccountDocuments'])
            ->middleware('auth:sanctum')
            ->name('consent.legal-documents.accept');
    });

    // Public contact form (frontend footer modal) — no auth, throttled.
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:contact')
        ->name('contact.store');

    // Cart: open to both guests (identified by the X-Guest-Cart-Token
    // header) and authenticated users (identified by their session) — no
    // auth:sanctum middleware, since that would 401 guests. See
    // CartController::resolve() / CartService::resolveCart().
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'show'])->name('cart.show');
        Route::post('/items', [CartController::class, 'storeItem'])->name('cart.items.store');
        Route::patch('/items/{item}', [CartController::class, 'updateItem'])->name('cart.items.update');
        Route::delete('/items/{item}', [CartController::class, 'destroyItem'])->name('cart.items.destroy');
        Route::delete('/', [CartController::class, 'clear'])->name('cart.clear');
    });

    // Checkout: same guest/authenticated resolution as cart (see above) —
    // placing an order doesn't require an account.
    Route::prefix('checkout')->group(function () {
        Route::get('/shipping-methods', [CheckoutController::class, 'shippingMethods'])->name('checkout.shipping-methods');
        Route::get('/shipping-quote', [CheckoutController::class, 'shippingQuote'])->name('checkout.shipping-quote');
        Route::get('/shipping-offices', [CheckoutController::class, 'shippingOffices'])->name('checkout.shipping-offices');
        Route::get('/settlements', [CheckoutController::class, 'settlements'])->name('checkout.settlements');
        Route::get('/legal-documents', [CheckoutController::class, 'legalDocuments'])->name('checkout.legal-documents');
        Route::get('/payment-methods', [CheckoutController::class, 'paymentMethods'])->name('checkout.payment-methods');
        Route::post('/orders', [CheckoutController::class, 'placeOrder'])
            ->middleware('throttle:checkout')
            ->name('checkout.orders.store');
    });

    // Order history: registered customers only — a guest has no account to
    // list orders against.
    Route::middleware('auth:sanctum')->get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/stored-payment-methods', [StoredPaymentMethodController::class, 'index'])->name('stored-payment-methods.index');
        Route::delete('/stored-payment-methods/{storedPaymentMethod}', [StoredPaymentMethodController::class, 'destroy'])->name('stored-payment-methods.destroy');
    });

    // Order lookup: registered customers via ownership (OrderPolicy), guests
    // via the guest_access_token minted at placement — see OrderController.
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');

    // Shipment tracking: same ownership rules as the order it belongs to
    // (see ShipmentController::authorizeAccess) — no auth:sanctum
    // middleware, since guests place and track orders too.
    Route::get('/orders/{order}/shipment', [ShipmentController::class, 'show'])->name('orders.shipment');

    // Payments: same ownership rules as the order they belong to (see
    // PaymentController::authorizeAccess) — no auth:sanctum middleware,
    // since guests place and pay for orders too.
    Route::prefix('payments')->group(function () {
        Route::post('/{order}/initiate', [PaymentController::class, 'initiate'])
            ->middleware('throttle:checkout')
            ->name('payments.initiate');
        Route::get('/{order}/status', [PaymentController::class, 'status'])->name('payments.status');
        Route::post('/{order}/return', [PaymentController::class, 'handleReturn'])->name('payments.return');
        Route::post('/{order}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');

    });

    // Webhook: public, unauthenticated — iCard's servers call this
    // directly. Trust comes from signature verification, not a session
    // (see ICardWebhookController / ICardPaymentGateway::verifySignature).
    Route::post('/payments/webhook/icard', [ICardWebhookController::class, 'handle'])
        ->middleware('throttle:webhooks')
        ->name('payments.webhook.icard');

    // Admin: full CRUD over the product domain, gated to administrators.
    Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->name('admin.')->group(function () {
        Route::apiResource('products', AdminProductController::class);

        Route::post('/products/{product}/variants', [AdminProductVariantController::class, 'store'])
            ->name('products.variants.store');
        Route::put('/products/{product}/variants/{variant}', [AdminProductVariantController::class, 'update'])
            ->name('products.variants.update');
        Route::delete('/products/{product}/variants/{variant}', [AdminProductVariantController::class, 'destroy'])
            ->name('products.variants.destroy');

        Route::put('/products/{product}/variants/{variant}/price', [AdminPriceController::class, 'update'])
            ->name('products.variants.price.update');
        Route::put('/products/{product}/variants/{variant}/inventory', [AdminProductVariantController::class, 'updateInventory'])
            ->name('products.variants.inventory.update');

        Route::post('/products/{product}/media', [AdminProductMediaController::class, 'store'])
            ->name('products.media.store');
        Route::patch('/products/{product}/media/{media}/primary', [AdminProductMediaController::class, 'makePrimary'])
            ->name('products.media.primary');
        Route::delete('/products/{product}/media/{media}', [AdminProductMediaController::class, 'destroy'])
            ->name('products.media.destroy');

        Route::apiResource('categories', AdminCategoryController::class);

        Route::apiResource('promotions', AdminPromotionController::class);

        Route::get('/orders/statistics', [AdminOrderController::class, 'statistics'])->name('orders.statistics');
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status.update');
        Route::post('/orders/{order}/shipment', [AdminOrderController::class, 'createShipment'])->name('orders.shipment.create');
        Route::get('/orders/{order}/shipment/label', [AdminOrderController::class, 'shipmentLabel'])->name('orders.shipment.label');

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard.show');

        Route::get('/customers', [AdminUserController::class, 'index'])->name('customers.index');
        Route::get('/customers/{user}', [AdminUserController::class, 'show'])->name('customers.show');

        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.show');
        Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::get('/payment-settings/icard', [AdminICardConfigurationController::class, 'index'])->name('payment-settings.icard.index');
        Route::put('/payment-settings/icard/{environment}', [AdminICardConfigurationController::class, 'update'])->name('payment-settings.icard.update');
        Route::post('/payment-settings/icard/{environment}/activate', [AdminICardConfigurationController::class, 'activate'])->name('payment-settings.icard.activate');
        Route::get('/shipping-settings', [AdminShippingProviderConfigurationController::class, 'index'])->name('shipping-settings.index');
        Route::put('/shipping-settings/{provider}', [AdminShippingProviderConfigurationController::class, 'update'])->name('shipping-settings.update');
        Route::post('/payments/{payment}/reverse', [AdminPaymentOperationController::class, 'reverse'])->name('payments.reverse');
        Route::post('/payments/{payment}/refund', [AdminPaymentOperationController::class, 'refund'])->name('payments.refund');

        Route::get('/legal-documents', [AdminLegalDocumentController::class, 'index'])->name('legal-documents.index');
        Route::post('/legal-documents', [AdminLegalDocumentController::class, 'store'])->name('legal-documents.store');

        // The ЗЗП чл. 128, ал. 4 complaints register - distinct from the
        // public contact form. No destroy route by design (see
        // ComplaintController's docblock).
        Route::get('/complaints', [AdminComplaintController::class, 'index'])->name('complaints.index');
        Route::post('/complaints', [AdminComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/complaints/{complaint}', [AdminComplaintController::class, 'show'])->name('complaints.show');
        Route::patch('/complaints/{complaint}', [AdminComplaintController::class, 'update'])->name('complaints.update');

        Route::get('/media', [AdminMediaController::class, 'index'])->name('media.index');
        Route::post('/media/{media}', [AdminMediaController::class, 'replace'])->name('media.replace');
        Route::delete('/media/{media}', [AdminMediaController::class, 'destroy'])->name('media.destroy');

        Route::get('/logs', [AdminLogController::class, 'index'])->name('logs.index');

        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/statistics', [AdminReviewController::class, 'statistics'])->name('reviews.statistics');
        Route::post('/reviews/bulk-moderate', [AdminReviewController::class, 'bulkModerate'])->name('reviews.bulk-moderate');
        Route::post('/reviews/{review}/approve', [AdminReviewController::class, 'approve'])->name('reviews.approve');
        Route::post('/reviews/{review}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
        Route::post('/reviews/{review}/hide', [AdminReviewController::class, 'hide'])->name('reviews.hide');
        Route::post('/reviews/{review}/reply', [AdminReviewController::class, 'reply'])->name('reviews.reply');
        Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::get('/content/homepage', [AdminContentBlockController::class, 'index'])->name('content.homepage.show');
        Route::put('/content/homepage/{section}', [AdminContentBlockController::class, 'update'])
            ->where('section', implode('|', ContentBlockService::HOMEPAGE_SECTIONS))
            ->name('content.homepage.update');

        Route::get('/funnel', [AdminFunnelController::class, 'show'])->name('funnel.show');
        Route::put('/funnel/toggle', [AdminFunnelController::class, 'toggle'])->name('funnel.toggle');
        Route::put('/funnel/packages', [AdminFunnelController::class, 'updatePackages'])->name('funnel.packages.update');
        Route::put('/funnel/content/{section}', [AdminFunnelController::class, 'updateContent'])
            ->where('section', implode('|', FunnelContentService::FUNNEL_SECTIONS))
            ->name('funnel.content.update');
        Route::post('/funnel/faq-attachment', [AdminFunnelController::class, 'uploadFaqAttachment'])
            ->name('funnel.faq-attachment.upload');

        // Leads captured by the funnel landing page's email opt-in.
        // /export is declared before /{lead} so it isn't swallowed by the
        // model-binding wildcard.
        Route::get('/funnel/leads', [AdminFunnelLeadController::class, 'index'])->name('funnel.leads.index');
        Route::get('/funnel/leads/export', [AdminFunnelLeadController::class, 'export'])->name('funnel.leads.export');
        Route::delete('/funnel/leads/{lead}', [AdminFunnelLeadController::class, 'destroy'])->name('funnel.leads.destroy');
    });
});
