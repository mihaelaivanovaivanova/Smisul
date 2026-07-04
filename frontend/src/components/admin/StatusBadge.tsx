const POSITIVE = new Set(['published', 'active', 'paid', 'delivered', 'completed', 'delivered_to_office']);
const NEGATIVE = new Set(['cancelled', 'failed', 'expired', 'archived', 'inactive', 'refunded', 'out_of_stock']);
const WARNING = new Set(['pending', 'awaiting_payment', 'processing', 'packed', 'shipped', 'draft', 'low_stock']);

function variantFor(status: string): string {
  if (POSITIVE.has(status)) return 'success';
  if (NEGATIVE.has(status)) return 'danger';
  if (WARNING.has(status)) return 'warning';
  return 'secondary';
}

export default function StatusBadge({ status }: { status: string }) {
  return <span className={`badge text-bg-${variantFor(status)}`}>{status.replace(/_/g, ' ')}</span>;
}
