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
}
