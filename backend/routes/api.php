<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\PriceController as AdminPriceController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ProductMediaController as AdminProductMediaController;
use App\Http\Controllers\Api\V1\Admin\ProductVariantController as AdminProductVariantController;
use App\Http\Controllers\Api\V1\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Resources\UserResource;
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

    // Public storefront: read-only, no auth required.
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{slug}/variants', [ProductController::class, 'variants'])->name('products.variants');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/categories/{slug}/products', [CategoryController::class, 'products'])->name('categories.products');

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

        Route::post('/products/{product}/media', [AdminProductMediaController::class, 'store'])
            ->name('products.media.store');
        Route::delete('/products/{product}/media/{media}', [AdminProductMediaController::class, 'destroy'])
            ->name('products.media.destroy');

        Route::apiResource('categories', AdminCategoryController::class);

        Route::apiResource('promotions', AdminPromotionController::class);
    });
});
