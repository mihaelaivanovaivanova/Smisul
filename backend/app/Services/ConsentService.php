<?php

namespace App\Services;

use App\Enums\ConsentType;
use App\Models\Consent;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Records and reads the append-only consent audit log (see the consents
 * migration). Every write is an insert, never an update — "current state"
 * for a given type is always just its most recent row.
 */
class ConsentService
{
    public function __construct(private readonly LegalDocumentService $legalDocuments) {}

    public function record(
        ConsentType $type,
        bool $accepted,
        ?User $user,
        ?string $guestIdentifier,
        ?LegalDocument $legalDocument,
        ?string $ipAddress,
        ?string $userAgent,
    ): Consent {
        return Consent::create([
            'user_id' => $user?->id,
            'guest_identifier' => $guestIdentifier,
            'type' => $type,
            'version' => $legalDocument?->version,
            'legal_document_id' => $legalDocument?->id,
            'accepted' => $accepted,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Records several consent types for the same subject/request in one
     * call — the common case (registration, the cookie banner) where a
     * single form submission grants/withholds multiple consent types at
     * once with identical ip/user-agent/subject.
     *
     * @param  array<string, bool>  $decisions  ConsentType::value => accepted
     * @param  array<string, ?LegalDocument>  $legalDocuments  ConsentType::value => the LegalDocument version being agreed to, for types tied to one (Terms/Privacy) — omitted/null for the rest
     * @return list<Consent>
     */
    public function recordMany(
        array $decisions,
        ?User $user,
        ?string $guestIdentifier,
        ?string $ipAddress,
        ?string $userAgent,
        array $legalDocuments = [],
    ): array {
        $recorded = [];

        foreach ($decisions as $typeValue => $accepted) {
            $recorded[] = $this->record(
                ConsentType::from($typeValue),
                $accepted,
                $user,
                $guestIdentifier,
                $legalDocuments[$typeValue] ?? null,
                $ipAddress,
                $userAgent,
            );
        }

        return $recorded;
    }

    /**
     * The latest row per consent type for a subject — either a registered
     * user or an anonymous guest identifier, never both. Returns only the
     * types that have at least one recorded decision; a type with no rows
     * simply isn't present in the result (callers should treat "absent" as
     * "not yet decided", not as any particular accepted/rejected value).
     *
     * @return array<string, Consent>
     */
    public function currentFor(?User $user, ?string $guestIdentifier): array
    {
        if ($user === null && $guestIdentifier === null) {
            return [];
        }

        $query = Consent::query()->latest('id');

        $query = $user !== null
            ? $query->where('user_id', $user->id)
            : $query->where('guest_identifier', $guestIdentifier);

        return $query->get()
            ->unique(fn (Consent $consent) => $consent->type->value)
            ->keyBy(fn (Consent $consent) => $consent->type->value)
            ->all();
    }

    /**
     * The current LegalDocument versions (of LegalDocumentType::requiredForAccount)
     * that $user hasn't actually agreed to — either they never recorded a
     * consent for that type at all (pre-dates this tracking), or their
     * latest recorded consent points at an older LegalDocument version
     * because a new one was published since. The storefront uses this to
     * decide whether to show a "please review our updated Terms" banner
     * and demand fresh acceptance; see LegalDocumentService::publish and
     * the LegalDocumentUpdated event it fires for the notification side.
     *
     * @return Collection<int, LegalDocument>
     */
    public function outstandingForAccount(User $user): Collection
    {
        $current = $this->currentFor($user, null);

        return collect(ConsentType::cases())
            ->filter(fn (ConsentType $type) => $type->legalDocumentType() !== null)
            ->map(function (ConsentType $type) use ($current) {
                $document = $this->legalDocuments->currentByType($type->legalDocumentType());

                if ($document === null) {
                    return null;
                }

                $consent = $current[$type->value] ?? null;
                $isOutstanding = $consent === null || ! $consent->accepted || $consent->legal_document_id !== $document->id;

                return $isOutstanding ? $document : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Cookie-banner-specific convenience: necessary is always granted (it
     * isn't a real choice — see the sprint's "necessary cookies are always
     * enabled"), the other three categories reflect exactly what the
     * visitor chose.
     *
     * @param  array{analytics: bool, marketing: bool, preferences: bool}  $categories
     * @return list<Consent>
     */
    public function recordCookiePreferences(
        array $categories,
        ?User $user,
        ?string $guestIdentifier,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        return $this->recordMany([
            ConsentType::CookieNecessary->value => true,
            ConsentType::CookieAnalytics->value => $categories['analytics'],
            ConsentType::CookieMarketing->value => $categories['marketing'],
            ConsentType::CookiePreferences->value => $categories['preferences'],
        ], $user, $guestIdentifier, $ipAddress, $userAgent);
    }
}
