<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completed is being retired (see the next migration/OrderStatus::Completed's
 * own docblock): it had no automated behavior of its own, and its mere
 * existence let an order silently lose review eligibility once pushed past
 * Delivered - ReviewService::assertEligible() only accepts a status of
 * exactly Delivered, not "Delivered or later". Any order that already
 * reached Completed is moved back to Delivered here, one time, so it (and
 * its customer's ability to review) ends up exactly where every future
 * order will now permanently stay. A real transition record is added too,
 * not just a silent column update, so this shows up in that order's own
 * timeline like any other status change - changed_by_user_id is left null
 * (system-initiated, not an admin action) to match how every other
 * automated transition in this app records itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orderIds = DB::table('orders')->where('status', 'completed')->pluck('id');

        if ($orderIds->isEmpty()) {
            return;
        }

        DB::table('orders')->whereIn('id', $orderIds)->update(['status' => 'delivered']);

        $now = now();

        DB::table('order_status_histories')->insert(
            $orderIds->map(fn (int $orderId) => [
                'order_id' => $orderId,
                'status' => 'delivered',
                'previous_status' => 'completed',
                'changed_by_user_id' => null,
                'note' => 'Reverted from Completed - that status was retired; Delivered is now the terminal status.',
                'created_at' => $now,
            ])->all()
        );
    }

    /**
     * Reversible via the marker note up() writes on each history row it
     * inserts - without that marker there'd be no reliable way to tell
     * which orders were actually reverted here versus already Delivered on
     * their own.
     */
    public function down(): void
    {
        $note = 'Reverted from Completed - that status was retired; Delivered is now the terminal status.';

        $orderIds = DB::table('order_status_histories')
            ->where('note', $note)
            ->where('previous_status', 'completed')
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            return;
        }

        DB::table('orders')->whereIn('id', $orderIds)->update(['status' => 'completed']);
        DB::table('order_status_histories')->where('note', $note)->where('previous_status', 'completed')->delete();
    }
};
