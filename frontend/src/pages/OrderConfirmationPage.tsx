import { useEffect, useState } from 'react';
import { Link, useLocation, useParams, useSearchParams } from 'react-router-dom';
import { fetchOrder } from '../api/checkout';
import { apiBaseUrl } from '../api/client';
import { getErrorMessage } from '../api/errors';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import Seo from '../components/Seo';
import { formatPrice } from '../services/productCatalog';
import { cart as cartCopy, checkout as checkoutCopy, orders as ordersCopy } from '../content/copy';
import type { Order } from '../types/checkout';

interface LocationState {
  order?: Order;
  guestAccessToken?: string | null;
}

export default function OrderConfirmationPage() {
  const { orderId = '' } = useParams<{ orderId: string }>();
  const [searchParams] = useSearchParams();
  const location = useLocation();
  const state = (location.state ?? {}) as LocationState;

  const [order, setOrder] = useState<Order | null>(state.order ?? null);
  const [isLoading, setIsLoading] = useState(state.order === undefined);
  const [error, setError] = useState<string | null>(null);

  const token = searchParams.get('token') ?? state.guestAccessToken ?? undefined;

  useEffect(() => {
    // Arrived straight from checkout with the order already in hand —
    // no need to re-fetch (and guests without ?token= couldn't anyway).
    if (state.order) {
      return;
    }

    let isMounted = true;
    setIsLoading(true);
    setError(null);

    fetchOrder(Number(orderId), token)
      .then((fetched) => {
        if (isMounted) {
          setOrder(fetched);
        }
      })
      .catch((err: unknown) => {
        if (isMounted) {
          setError(getErrorMessage(err, checkoutCopy.confirmation.loadError));
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [orderId, token]);

  return (
    <div className="container py-4">
      <Seo title={checkoutCopy.confirmation.seoTitle} />

      {isLoading && <LoadingState message={checkoutCopy.confirmation.loading} />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && order && (
        <div className="row justify-content-center">
          <div className="col-12 col-lg-8">
            <div className="text-center mb-4">
              <h1 className="h3">{checkoutCopy.confirmation.thankYou}</h1>
              <p className="text-muted mb-0">
                {checkoutCopy.confirmation.orderNumberLabel}: <strong>{order.order_number}</strong>
              </p>
              <p className="text-muted mb-0">
                {checkoutCopy.confirmation.statusLabel}: {ordersCopy.status[order.status] ?? order.status}
              </p>
            </div>

            <div className="card shadow-sm mb-4">
              <div className="card-body">
                <ul className="list-unstyled mb-3">
                  {order.items.map((item) => (
                    <li key={item.id} className="d-flex justify-content-between border-bottom py-2">
                      <span>
                        {item.product_name}
                        {item.variant_name ? ` (${item.variant_name})` : ''} × {item.quantity}
                      </span>
                      <span>{formatPrice(item.line_total)}</span>
                    </li>
                  ))}
                </ul>

                <div className="d-flex justify-content-between fw-bold fs-5">
                  <span>{cartCopy.grandTotal}</span>
                  <span>{formatPrice(order.totals.grand_total)}</span>
                </div>
              </div>
            </div>

            <div className="alert alert-info">{checkoutCopy.confirmation.paymentNotice}</div>

            {order.timeline && order.timeline.length > 0 && (
              <div className="card shadow-sm mb-4">
                <div className="card-body">
                  <h2 className="h6 mb-3">{ordersCopy.timelineHeading}</h2>
                  <ul className="list-unstyled mb-0">
                    {order.timeline.map((entry) => (
                      <li key={entry.id} className="border-bottom py-2">
                        <div className="d-flex justify-content-between">
                          <span className="fw-semibold">{ordersCopy.status[entry.status] ?? entry.status}</span>
                          <span className="text-muted small">{new Date(entry.changed_at).toLocaleString('bg-BG')}</span>
                        </div>
                        <div className="text-muted small">
                          {entry.changed_by ?? ordersCopy.timelineChangedBySystem}
                          {entry.note ? ` — ${entry.note}` : ''}
                        </div>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            )}

            <div className="text-center d-flex flex-column flex-sm-row justify-content-center gap-2">
              <a
                href={`${apiBaseUrl}/orders/${order.id}/invoice${token ? `?token=${token}` : ''}`}
                className="btn btn-outline-secondary"
                target="_blank"
                rel="noopener noreferrer"
              >
                {ordersCopy.downloadInvoice}
              </a>
              <Link to="/search" className="btn btn-outline-primary">
                {checkoutCopy.confirmation.continueShopping}
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
