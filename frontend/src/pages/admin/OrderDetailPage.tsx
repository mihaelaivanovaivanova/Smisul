import { Fragment, useState } from 'react';
import { useParams } from 'react-router-dom';
import { fetchAdminOrder, refundPayment, reversePayment, updateOrderStatus } from '../../api/admin/orders';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import StatusBadge from '../../components/admin/StatusBadge';
import { formatPrice } from '../../services/productCatalog';
import { ORDER_STATUSES } from '../../constants/orderStatus';

export default function OrderDetailPage() {
  const { orderId } = useParams<{ orderId: string }>();
  const id = Number(orderId);
  const [reloadKey, setReloadKey] = useState(0);
  const { data: order, isLoading, error } = useAsync(() => fetchAdminOrder(id), [id, reloadKey], 'Could not load the order.');

  const [newStatus, setNewStatus] = useState('');
  const [note, setNote] = useState('');
  const [isUpdating, setIsUpdating] = useState(false);
  const [updateError, setUpdateError] = useState<string | null>(null);
  const [operationPaymentId, setOperationPaymentId] = useState<number | null>(null);
  const [refundAmounts, setRefundAmounts] = useState<Record<number, number>>({});

  async function handlePaymentOperation(paymentId: number, operation: 'reverse' | 'refund') {
    setOperationPaymentId(paymentId);
    setUpdateError(null);
    try {
      if (operation === 'reverse') await reversePayment(paymentId);
      else await refundPayment(paymentId, refundAmounts[paymentId] ?? 0);
      setReloadKey((key) => key + 1);
    } catch (err) {
      setUpdateError(getErrorMessage(err, `Could not ${operation} the payment.`));
    } finally {
      setOperationPaymentId(null);
    }
  }

  async function handleStatusUpdate() {
    if (!newStatus) return;
    setIsUpdating(true);
    setUpdateError(null);
    try {
      await updateOrderStatus(id, newStatus, note || undefined);
      setNewStatus('');
      setNote('');
      setReloadKey((key) => key + 1);
    } catch (err) {
      setUpdateError(getErrorMessage(err, 'Could not update the order status.'));
    } finally {
      setIsUpdating(false);
    }
  }

  if (isLoading) return <LoadingState message="Loading order..." />;
  if (error) return <ErrorState message={error} />;
  if (!order) return null;

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1 className="h3 mb-0">
          Order {order.order_number} <StatusBadge status={order.status} />
        </h1>
        <span className="text-muted">Placed {new Date(order.placed_at).toLocaleString('bg-BG')}</span>
      </div>

      <div className="row g-4">
        <div className="col-lg-8">
          <div className="card mb-4">
            <div className="card-header">Items</div>
            <div className="table-responsive">
              <table className="table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th className="text-end">Qty</th>
                    <th className="text-end">Unit price</th>
                    <th className="text-end">Line total</th>
                  </tr>
                </thead>
                <tbody>
                  {order.items.map((item) => (
                    <tr key={item.id}>
                      <td>
                        {item.product_name}
                        {item.variant_name && <div className="text-muted small">{item.variant_name}</div>}
                      </td>
                      <td>{item.sku}</td>
                      <td className="text-end">{item.quantity}</td>
                      <td className="text-end">{formatPrice(item.unit_price)}</td>
                      <td className="text-end">{formatPrice(item.line_total)}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr>
                    <td colSpan={4} className="text-end fw-semibold">
                      Subtotal
                    </td>
                    <td className="text-end">{formatPrice(order.totals.subtotal)}</td>
                  </tr>
                  <tr>
                    <td colSpan={4} className="text-end fw-semibold">
                      Discount
                    </td>
                    <td className="text-end">-{formatPrice(order.totals.discount_total)}</td>
                  </tr>
                  <tr>
                    <td colSpan={4} className="text-end fw-semibold">
                      Shipping
                    </td>
                    <td className="text-end">{formatPrice(order.totals.shipping_total)}</td>
                  </tr>
                  <tr>
                    <td colSpan={4} className="text-end fw-bold">
                      Grand total
                    </td>
                    <td className="text-end fw-bold">{formatPrice(order.totals.grand_total)}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div className="card mb-4">
            <div className="card-header">Timeline</div>
            <ul className="list-group list-group-flush">
              {(order.timeline ?? []).map((entry) => (
                <li key={entry.id} className="list-group-item">
                  <div className="d-flex justify-content-between">
                    <StatusBadge status={entry.status} />
                    <span className="text-muted small">{new Date(entry.changed_at).toLocaleString('bg-BG')}</span>
                  </div>
                  {entry.changed_by && <div className="small text-muted">by {entry.changed_by}</div>}
                  {entry.note && <div className="small">{entry.note}</div>}
                </li>
              ))}
              {(order.timeline ?? []).length === 0 && <li className="list-group-item text-muted">No history yet.</li>}
            </ul>
          </div>

          <div className="card mb-4">
            <div className="card-header">Payments</div>
            <div className="table-responsive">
              <table className="table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Provider</th>
                    <th>Status</th>
                    <th className="text-end">Amount</th>
                    <th>Initiated</th>
                    <th>Gateway actions</th>
                  </tr>
                </thead>
                <tbody>
                  {order.payments.map((payment) => (
                    <Fragment key={payment.id}>
                      <tr>
                        <td>
                          <strong>{payment.payment_method === 'cash_on_delivery' ? 'Наложен платеж' : 'Плащане с карта'}</strong>
                          <div className="small text-muted">Provider: {payment.provider === 'icard' ? 'iCard' : 'Cash on delivery'}</div>
                          {payment.provider === 'icard' && (
                            <span className={`badge mt-1 text-bg-${payment.gateway_environment === 'production' ? 'danger' : 'info'}`}>
                              iCard {payment.gateway_environment === 'production' ? 'Production' : 'Sandbox'}
                            </span>
                          )}
                        </td>
                        <td>
                          <StatusBadge status={payment.status} />
                          {payment.operation_status && <div className="small mt-1">Operation: {payment.operation_status}</div>}
                          {payment.operation_message && <div className="small text-muted">{payment.operation_message}</div>}
                        </td>
                        <td className="text-end">{formatPrice(payment.amount)}</td>
                        <td>
                          {payment.initiated_at ? new Date(payment.initiated_at).toLocaleString('bg-BG') : '—'}
                          {payment.paid_at && <div className="small text-success">Paid: {new Date(payment.paid_at).toLocaleString('bg-BG')}</div>}
                          {payment.gateway_transaction_reference && <div className="small text-muted">TRN: {payment.gateway_transaction_reference}</div>}
                          {payment.approval_code && <div className="small text-muted">Approval: {payment.approval_code}</div>}
                          {payment.masked_pan && <div className="small text-muted">{payment.card_type ?? 'Card'} {payment.masked_pan}</div>}
                        </td>
                        <td style={{ minWidth: 260 }}>
                          <div className="d-flex gap-2 align-items-center">
                            {payment.provider === 'icard' && (payment.status === 'paid' || payment.status === 'authorized') && !payment.reversed_at && (
                              <button type="button" className="btn btn-sm btn-outline-danger" disabled={operationPaymentId === payment.id || !payment.gateway_transaction_reference} onClick={() => void handlePaymentOperation(payment.id, 'reverse')}>Reverse</button>
                            )}
                            {payment.provider === 'icard' && payment.status === 'paid' && (
                              <>
                                <input type="number" min="0.01" step="0.01" className="form-control form-control-sm" style={{ width: 90 }} value={refundAmounts[payment.id] ?? Math.max(0, payment.amount - payment.refunded_amount)} onChange={(event) => setRefundAmounts((values) => ({ ...values, [payment.id]: Number(event.target.value) }))} />
                                <button type="button" className="btn btn-sm btn-outline-primary" disabled={operationPaymentId === payment.id || !payment.gateway_transaction_reference} onClick={() => void handlePaymentOperation(payment.id, 'refund')}>Refund</button>
                              </>
                            )}
                            {payment.provider === 'icard' && !payment.gateway_transaction_reference && <span className="small text-muted">Awaiting iCard TRN</span>}
                          </div>
                        </td>
                      </tr>
                      {payment.provider === 'icard' && (
                        <tr>
                          <td colSpan={5} className="bg-body-tertiary">
                            <details>
                              <summary className="small fw-semibold">Callback logs ({payment.callback_logs?.length ?? 0})</summary>
                              <div className="mt-2 d-flex flex-column gap-2">
                                {(payment.callback_logs ?? []).map((log) => (
                                  <div className="border rounded p-2 bg-body" key={log.id}>
                                    <div className="small">
                                      <strong>{log.event_type ?? 'Callback'}</strong>{' '}
                                      <span className={`badge text-bg-${log.signature_valid ? 'success' : 'danger'}`}>
                                        Signature {log.signature_valid ? 'valid' : 'invalid'}
                                      </span>{' '}
                                      {log.created_at && new Date(log.created_at).toLocaleString('bg-BG')}
                                    </div>
                                    {log.error_message && <div className="small text-danger mt-1">{log.error_message}</div>}
                                    <pre className="small mt-2 mb-0 text-wrap">{JSON.stringify(log.payload, null, 2)}</pre>
                                  </div>
                                ))}
                                {(payment.callback_logs ?? []).length === 0 && <span className="small text-muted">No callbacks received yet.</span>}
                              </div>
                            </details>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  ))}
                  {order.payments.length === 0 && (
                    <tr>
                      <td colSpan={5} className="text-muted text-center py-3">
                        No payment attempts.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {order.shipment && (
            <div className="card mb-4">
              <div className="card-header">Shipment</div>
              <div className="card-body">
                <p className="mb-1">
                  <strong>Carrier:</strong> {order.shipment.carrier} ({order.shipment.delivery_type})
                </p>
                <p className="mb-1">
                  <strong>Tracking #:</strong> {order.shipment.tracking_number ?? '—'}
                </p>
                <p className="mb-0">
                  <strong>Status:</strong> <StatusBadge status={order.shipment.status} />
                </p>
              </div>
            </div>
          )}
        </div>

        <div className="col-lg-4">
          <div className="card mb-4">
            <div className="card-header">Customer</div>
            <div className="card-body">
              <p className="mb-1">
                {order.customer.first_name} {order.customer.last_name}
              </p>
              <p className="mb-1">{order.customer.email}</p>
              <p className="mb-0">{order.customer.phone}</p>
            </div>
          </div>

          <div className="card mb-4">
            <div className="card-header">Shipping address</div>
            <div className="card-body">
              <p className="mb-1">{order.address.address_line}</p>
              {order.address.apartment && <p className="mb-1">{order.address.apartment}</p>}
              <p className="mb-0">
                {order.address.city}, {order.address.postal_code}, {order.address.country}
              </p>
            </div>
          </div>

          <div className="card mb-4">
            <div className="card-header">Update status</div>
            <div className="card-body d-flex flex-column gap-2">
              {updateError && <div className="alert alert-danger py-2 mb-0">{updateError}</div>}
              <select className="form-select" value={newStatus} onChange={(event) => setNewStatus(event.target.value)}>
                <option value="">Select new status...</option>
                {ORDER_STATUSES.filter((status) => status !== order.status).map((status) => (
                  <option key={status} value={status}>
                    {status.replace(/_/g, ' ')}
                  </option>
                ))}
              </select>
              <textarea
                className="form-control"
                rows={2}
                placeholder="Note (optional)"
                value={note}
                onChange={(event) => setNote(event.target.value)}
              />
              <button
                type="button"
                className="btn btn-primary"
                disabled={!newStatus || isUpdating}
                onClick={() => void handleStatusUpdate()}
              >
                {isUpdating && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
                Update status
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
