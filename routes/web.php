<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Experimental\RandomController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\ProfileInformationController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

/**
 * Admin Posts Routes
 */
Route::middleware(['auth', 'web'])->prefix('admin')->group(function () {
    Route::prefix('posts')->group(function () {
        Route::get('/', [PostController::class, 'index'])->name('admin.posts.index');
        Route::get('/create', [PostController::class, 'create'])->name('admin.posts.create');
        Route::get('/{post}/edit', [PostController::class, 'edit'])->name('admin.posts.edit');
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('admin.categories.index');
        Route::get('/create', [CategoryController::class, 'create'])->name('admin.categories.create');
        Route::get('/{post}/edit', [CategoryController::class, 'edit'])->name('admin.categories.edit');
    });

    Route::prefix('tags')->group(function () {
        Route::get('/', [TagController::class, 'index'])->name('admin.tags.index');
        Route::get('/create', [TagController::class, 'create'])->name('admin.tags.create');
        Route::get('/{post}/edit', [TagController::class, 'edit'])->name('admin.tags.edit');
    });
});

/**
 * Fortify Routes
 */
Route::group(['middleware' => config('fortify.middleware', ['web'])], function () {
    $enableViews = config('fortify.views', true);

    // Authentication...
    if ($enableViews) {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])
            ->middleware(['guest:'.config('fortify.guard')])
            ->name('login');
    }

    $limiter = config('fortify.limiters.login');
    $twoFactorLimiter = config('fortify.limiters.two-factor');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(array_filter([
            'guest:'.config('fortify.guard'),
            $limiter ? 'throttle:'.$limiter : null,
        ]));

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Password Reset...
    if (Features::enabled(Features::resetPasswords())) {
        if ($enableViews) {
            Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('password.request');

            Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('password.reset');
        }

        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware(['guest:'.config('fortify.guard')])
            ->name('password.email');

        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->middleware(['guest:'.config('fortify.guard')])
            ->name('password.update');
    }

    // Registration...
    if (Features::enabled(Features::registration())) {
        if ($enableViews) {
            Route::get('/register', [RegisteredUserController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('register');
        }

        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->middleware(['guest:'.config('fortify.guard')]);
    }

    // Email Verification...
    if (Features::enabled(Features::emailVerification())) {
        if ($enableViews) {
            Route::get('/email/verify', [EmailVerificationPromptController::class, '__invoke'])
                ->middleware(['auth'])
                ->name('verification.notice');
        }

        Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
            ->middleware(['auth', 'signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware(['auth', 'throttle:6,1'])
            ->name('verification.send');
    }

    // Profile Information...
    if (Features::enabled(Features::updateProfileInformation())) {
        Route::put('/user/profile-information', [ProfileInformationController::class, 'update'])
            ->middleware(['auth'])
            ->name('user-profile-information.update');
    }

    // Passwords...
    if (Features::enabled(Features::updatePasswords())) {
        Route::put('/user/password', [PasswordController::class, 'update'])
            ->middleware(['auth'])
            ->name('user-password.update');
    }

    // Password Confirmation...
    if ($enableViews) {
        Route::get('/user/confirm-password', [ConfirmablePasswordController::class, 'show'])
            ->middleware(['auth'])
            ->name('password.confirm');
    }

    Route::get('/user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])
        ->middleware(['auth'])
        ->name('password.confirmation');

    Route::post('/user/confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware(['auth']);

    // Two Factor Authentication...
    if (Features::enabled(Features::twoFactorAuthentication())) {
        if ($enableViews) {
            Route::get('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('two-factor.login');
        }

        Route::post('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
            ->middleware(array_filter([
                'guest:'.config('fortify.guard'),
                $twoFactorLimiter ? 'throttle:'.$twoFactorLimiter : null,
            ]));

        $twoFactorMiddleware = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
            ? ['auth', 'password.confirm']
            : ['auth'];

        Route::post('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.enable');

        Route::delete('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.disable');

        Route::get('/user/two-factor-qr-code', [TwoFactorQrCodeController::class, 'show'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.qr-code');

        Route::get('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'index'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.recovery-codes');

        Route::post('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'store'])
            ->middleware($twoFactorMiddleware);
    }
});

/**
 * Experimental Routes
 */
Route::prefix('experimental')->group(function () {
    Route::get('/random', [RandomController::class, 'index'])->name('experimental.random');
    Route::get('/user', [RandomController::class, 'user'])->name('experimental.user');
});