<?php

namespace App\Services;

use App\Enums\ConsentType;
use App\Enums\LegalDocumentType;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly ConsentService $consents,
        private readonly LegalDocumentService $legalDocuments,
    ) {}

    /**
     * Register a new customer account.
     *
     * The "role" column is never set from client input — it defaults to
     * "customer" at the database level. Administrators can only be created
     * by the seeder.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?string $ipAddress = null, ?string $userAgent = null): User
    {
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'newsletter_subscription' => $data['newsletter_subscription'] ?? false,
            'marketing_consent' => $data['marketing_consent'] ?? false,
            'gdpr_consent_at' => now(),
        ]);

        // The "role" column is set by the database's own default, which
        // Eloquent has no way to know about without reloading — without
        // this, $user->role would be null in memory until the model is
        // fetched fresh from the database.
        $user->refresh();

        // "gdpr_consent" on the request is a single required checkbox that
        // covers accepting the Terms and Privacy policies at sign-up time;
        // newsletter/marketing are their own independently-optional boxes.
        // Linking legal_document_id to the version current right now is
        // what lets ConsentService::outstandingForAccount later detect a
        // Terms/Privacy update this user never agreed to.
        $this->consents->recordMany([
            ConsentType::Terms->value => true,
            ConsentType::Privacy->value => true,
            ConsentType::Marketing->value => (bool) ($data['marketing_consent'] ?? false),
            ConsentType::Newsletter->value => (bool) ($data['newsletter_subscription'] ?? false),
        ], $user, null, $ipAddress, $userAgent, [
            ConsentType::Terms->value => $this->legalDocuments->currentByType(LegalDocumentType::TermsOfService),
            ConsentType::Privacy->value => $this->legalDocuments->currentByType(LegalDocumentType::PrivacyPolicy),
        ]);

        event(new Registered($user));

        return $user;
    }

    /**
     * Attempt to authenticate and start a stateful session.
     *
     * Requires the request to have been recognized by Sanctum as coming
     * from a trusted frontend (see config/sanctum.php "stateful" domains);
     * otherwise no session middleware ever ran and $request->session()
     * would throw. A real browser SPA request always satisfies this.
     */
    public function login(Request $request, string $email, string $password, bool $remember): User
    {
        if (! $request->hasSession()) {
            abort(400, 'This request was not recognized as coming from a trusted frontend.');
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Log out of the current session.
     */
    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
