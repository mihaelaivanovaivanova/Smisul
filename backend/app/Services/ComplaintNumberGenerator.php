<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Assigns each complaint its own gap-free sequential number - same
 * locked-counter pattern as InvoiceNumberGenerator, sharing the
 * DocumentSequence table under a separate 'complaint' key so the two
 * sequences never collide or interfere with each other. Unlike invoice
 * numbers (assigned lazily, on first actual issuance), a complaint's
 * number is assigned immediately at creation - the register entry exists
 * from the moment it's logged, there's no "unissued" state to defer.
 */
class ComplaintNumberGenerator
{
    private const SEQUENCE_KEY = 'complaint';

    /** Matches InvoiceNumberGenerator's convention for consistency across the two registers. */
    private const PAD_LENGTH = 10;

    public function next(): string
    {
        return DB::transaction(function () {
            $sequence = DocumentSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(['key' => self::SEQUENCE_KEY], ['next_number' => 1]);

            $number = str_pad((string) $sequence->next_number, self::PAD_LENGTH, '0', STR_PAD_LEFT);

            $sequence->increment('next_number');

            return $number;
        });
    }
}
