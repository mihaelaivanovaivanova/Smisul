import { useState } from 'react';
import { syncShipmentTracking } from '../../api/admin/orders';
import { getErrorMessage } from '../../api/errors';

interface SyncShipmentTrackingButtonProps {
  /** Called after a successful sync so the caller can refetch whatever order list/stats are on screen - this component doesn't know about either. */
  onSynced: () => void;
}

/**
 * Shared by the Orders page and the dashboard (both show a live order
 * list that a sync can change). On-demand only, by request - see backend's
 * ShipmentTrackingSyncService for why this isn't a scheduled background job.
 */
export default function SyncShipmentTrackingButton({ onSynced }: SyncShipmentTrackingButtonProps) {
  const [isPending, setIsPending] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleClick(): Promise<void> {
    setIsPending(true);
    setError(null);
    setMessage(null);

    try {
      const result = await syncShipmentTracking();
      const summary = `Checked ${result.checked} shipment(s), ${result.updated} status change(s), ${result.orders_updated} order(s) advanced.`;
      setMessage(result.failed > 0 ? `${summary} ${result.failed} failed - see logs.` : summary);
      onSynced();
    } catch (err) {
      setError(getErrorMessage(err, 'Could not sync shipment tracking.'));
    } finally {
      setIsPending(false);
    }
  }

  return (
    <div className="d-flex flex-column align-items-end gap-1">
      <button
        type="button"
        className="btn btn-outline-secondary btn-sm"
        onClick={() => void handleClick()}
        disabled={isPending}
      >
        {isPending ? 'Syncing…' : 'Sync tracking'}
      </button>
      {message && <div className="small text-muted text-end">{message}</div>}
      {error && <div className="small text-danger text-end">{error}</div>}
    </div>
  );
}
