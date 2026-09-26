// Includes 'processing' and 'completed' — both retired (see backend
// OrderStatus::Processing/Completed's own docblocks), but kept here so the
// admin can still filter to any order a history row shows once passed
// through either. Never offered as a status to move an order *into* — see
// SETTABLE_ORDER_STATUSES below.
export const ORDER_STATUSES = [
  'pending', 'awaiting_payment', 'paid', 'confirmed', 'processing', 'packed', 'shipped',
  'delivered', 'completed', 'cancelled', 'failed', 'refunded',
];

const RETIRED_ORDER_STATUSES = new Set(['processing', 'completed']);

/** What the "change status to" dropdown offers — everything except the retired statuses above. */
export const SETTABLE_ORDER_STATUSES = ORDER_STATUSES.filter((status) => !RETIRED_ORDER_STATUSES.has(status));
