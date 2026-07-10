import { useEffect, useState } from 'react';
import { fetchStoredPaymentMethods, removeStoredPaymentMethod } from '../api/payment';
import { getErrorMessage } from '../api/errors';
import type { StoredPaymentMethod } from '../types/payment';

export default function StoredPaymentMethodsPanel() {
  const [methods, setMethods] = useState<StoredPaymentMethod[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => { fetchStoredPaymentMethods().then(setMethods).catch((err) => setError(getErrorMessage(err, 'Could not load saved cards.'))); }, []);

  async function remove(id: number) {
    try { await removeStoredPaymentMethod(id); setMethods((current) => current?.filter((method) => method.id !== id) ?? []); }
    catch (err) { setError(getErrorMessage(err, 'Could not remove the saved card.')); }
  }

  return (
    <div className="card">
      <div className="card-header">Saved payment cards</div>
      <div className="card-body">
        <p className="text-muted small">Only an encrypted iCard token and masked card details are stored. Smisul never receives the card number or CVC.</p>
        {error && <div className="alert alert-danger">{error}</div>}
        {methods === null && <div className="text-muted">Loading…</div>}
        {methods?.length === 0 && <div className="text-muted">No saved cards.</div>}
        <div className="list-group list-group-flush">
          {methods?.map((method) => (
            <div className="list-group-item px-0 d-flex justify-content-between align-items-center" key={method.id}>
              <span>{method.brand ?? 'Card'} •••• {method.last_four ?? '????'} <span className="text-muted">{method.expiry_month}/{method.expiry_year}</span></span>
              <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => void remove(method.id)}>Remove</button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
