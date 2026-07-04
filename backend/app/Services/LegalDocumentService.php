<?php

namespace App\Services;

use App\Enums\LegalDocumentType;
use App\Exceptions\Checkout\LegalDocumentsNotAcceptedException;
use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * Legal documents are versioned rows (see the legal_documents migration):
 * publishing a new version inserts a new row and flips is_current, rather
 * than mutating an accepted one — so an order's OrderLegalAcceptance
 * always points at the exact version the customer actually saw.
 */
class LegalDocumentService
{
    /**
     * @return Collection<int, LegalDocument>
     */
    public function current(): Collection
    {
        return LegalDocument::query()->where('is_current', true)->get();
    }

    /**
     * Every LegalDocumentType is required at checkout today (see the
     * sprint brief's Cookie Policy "if applicable" note — this storefront
     * doesn't yet distinguish an "if applicable" case, so all five are
     * always required). Returns the current LegalDocument rows matching
     * the given IDs, or throws listing whichever required types weren't
     * covered — by a missing ID, a wrong/stale ID, or an ID for the wrong
     * type.
     *
     * @param  list<int>  $legalDocumentIds
     * @return Collection<int, LegalDocument>
     */
    public function validateAcceptance(array $legalDocumentIds): Collection
    {
        $current = $this->current();
        $accepted = $current->whereIn('id', $legalDocumentIds);

        $missingTypes = collect(LegalDocumentType::cases())
            ->reject(fn (LegalDocumentType $type) => $accepted->contains(fn (LegalDocument $doc) => $doc->type === $type))
            ->values()
            ->all();

        if ($missingTypes !== []) {
            throw new LegalDocumentsNotAcceptedException($missingTypes);
        }

        return $accepted;
    }

    /**
     * All versions of every document type, newest first — the admin
     * history view. Unlike current(), this is not filtered to is_current.
     *
     * @return Collection<int, LegalDocument>
     */
    public function all(): Collection
    {
        return LegalDocument::query()->orderByDesc('published_at')->get();
    }

    /**
     * Publishes a new version of a document type: inserts a new row and
     * flips is_current on it, while unsetting is_current on every other
     * row of that type. Never mutates an existing version in place, since
     * past orders' OrderLegalAcceptance rows point at specific versions.
     */
    public function publish(LegalDocumentType $type, string $version, string $title, ?string $content): LegalDocument
    {
        return LegalDocument::query()->getConnection()->transaction(function () use ($type, $version, $title, $content) {
            LegalDocument::query()->where('type', $type)->update(['is_current' => false]);

            return LegalDocument::query()->create([
                'type' => $type,
                'version' => $version,
                'title' => $title,
                'content' => $content,
                'is_current' => true,
                'published_at' => now(),
            ]);
        });
    }
}
