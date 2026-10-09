import { useState } from 'react';
import { getErrorMessage } from '../../api/errors';

interface AdminActionButtonProps<T> {
  label: string;
  pendingLabel: string;
  action: () => Promise<T>;
  /** Renders the result as the one-line summary shown under the button (e.g. "Sent 3 email(s), 0 failed."). */
  summarize: (result: T) => string;
  /** Called after a successful action, e.g. to refetch whatever order list/stats are on screen - this component doesn't know what to refresh. */
  onSuccess: () => void;
  errorFallback: string;
}

/**
 * A manual "run this server-side action now" button for the admin Orders
 * page/dashboard - shared by the "Sync tracking" and "Send reminder
 * emails" buttons (see their own call sites), both of which replaced a
 * scheduled job with an on-demand one, by request. Generic over the
 * result shape so each caller only supplies what differs: the label, the
 * API call, and how to summarize its result.
 */
export default function AdminActionButton<T>({
  label,
  pendingLabel,
  action,
  summarize,
  onSuccess,
  errorFallback,
}: AdminActionButtonProps<T>) {
  const [isPending, setIsPending] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleClick(): Promise<void> {
    setIsPending(true);
    setError(null);
    setMessage(null);

    try {
      const result = await action();
      setMessage(summarize(result));
      onSuccess();
    } catch (err) {
      setError(getErrorMessage(err, errorFallback));
    } finally {
      setIsPending(false);
    }
  }

  return (
    <div className="d-flex flex-column align-items-end gap-1">
      <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => void handleClick()} disabled={isPending}>
        {isPending ? pendingLabel : label}
      </button>
      {message && <div className="small text-muted text-end">{message}</div>}
      {error && <div className="small text-danger text-end">{error}</div>}
    </div>
  );
}
