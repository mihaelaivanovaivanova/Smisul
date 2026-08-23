<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Assigns the invoice document's own sequential number — separate from
 * OrderNumberGenerator's order_number (a date-prefixed random reference
 * every order gets at checkout; collision-avoided but not gap-free).
 * Bulgarian accounting documents conventionally expect a gap-free
 * ascending sequence, so this locks a single counter row per document
 * type (DocumentSequence) inside a transaction rather than reusing
 * OrderNumberGenerator's collision-retry approach.
 *
 * Idempotent: if the order already has an invoice_number, that's returned
 * unchanged — a document must never be renumbered once issued, since the
 * customer may already hold a copy carrying the original number.
 */
class InvoiceNumberGenerator
{
    private const SEQUENCE_KEY = 'invoice';

    /** Matches common Bulgarian accounting-software convention for non-VAT sales documents. */
    private const PAD_LENGTH = 10;

    public function generateFor(Order $order): string
    {
        if ($order->invoice_number !== null) {
            return $order->invoice_number;
        }

        return DB::transaction(function () use ($order) {
            $sequence = DocumentSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(['key' => self::SEQUENCE_KEY], ['next_number' => 1]);

            $number = str_pad((string) $sequence->next_number, self::PAD_LENGTH, '0', STR_PAD_LEFT);

            $sequence->increment('next_number');

            $order->forceFill([
                'invoice_number' => $number,
                'invoice_issued_at' => now(),
            ])->save();

            return $number;
        });
    }
}
