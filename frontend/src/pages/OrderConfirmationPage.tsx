import { useEffect, useState } from 'react';
import { Link, useLocation, useParams, useSearchParams } from 'react-router-dom';
import { fetchOrder } from '../api/checkout';
import { apiBaseUrl } from '../api/client';
import { getErrorMessage, getErrorReason, getErrorStatus } from '../api/errors';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import Seo from '../components/Seo';
import { trackPurchase } from '../services/analytics';
import { formatPrice } from '../services/productCatalog';
import { useAuth } from '../hooks/useAuth';
import {
  cart as cartCopy,
  checkout as checkoutCopy,
  orders as ordersCopy,
  reviews as reviewsCopy,
  tracking as trackingCopy,
} from '../content/copy';
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
  const { isAuthenticated } = useAuth();

  const [order, setOrder] = useState<Order | null>(state.order ?? null);
  const [isLoading, setIsLoading] = useState(state.order === undefined);
  const [error, setError] = useState<string | null>(null);
  const [accessIssue, setAccessIssue] = useState<'login_required' | 'guest_link_invalid' | null>(null);

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
    setAccessIssue(null);

    fetchOrder(Number(orderId), token)
      .then((fetched) => {
        if (isMounted) {
          setOrder(fetched);
        }
      })
      .catch((err: unknown) => {
        if (!isMounted) return;
        // A 403 here is ambiguous on its own - it covers two very
        // different situations (see AuthorizesOrderAccess on the backend):
        // this order belongs to a registered account and nobody's logged
        // in as its owner right now (reason=login_required, common when
        // the confirmation-email link is opened cold on another device),
        // or this is a guest order and the link's token is missing/wrong
        // (reason=guest_link_invalid - a guest has no account to log into
        // at all, so a login prompt would be actively misleading there).
        const reason = getErrorStatus(err) === 403 ? getErrorReason(err) : undefined;

        if (reason === 'login_required' || reason === 'guest_link_invalid') {
          setAccessIssue(reason);
        } else {
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

  // Purchase conversion — the bottom of the ad-campaign event chain.
  // trackPurchase itself deduplicates per order (sessionStorage), so a
  // page refresh doesn't double-report the revenue.
  useEffect(() => {
    if (order) {
      trackPurchase(order.order_number, order.totals.grand_total, order.currency);
    }
  }, [order]);

  return (
    <div className="container py-4">
      <Seo title={checkoutCopy.confirmation.seoTitle} noindex />

      {isLoading && <LoadingState message={checkoutCopy.confirmation.loading} />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && (accessIssue === 'login_required' || accessIssue === 'guest_link_invalid') && (
        <div className="text-center py-5">
          {accessIssue === 'guest_link_invalid' ? (
            <p className="mb-0">{checkoutCopy.confirmation.guestLinkInvalid}</p>
          ) : (
            <>
              <p className="mb-3">
                {isAuthenticated
                  ? checkoutCopy.confirmation.needsLoginWrongAccount
                  : checkoutCopy.confirmation.needsLogin}
              </p>
              <Link to="/login" state={{ from: location }} className="btn btn-primary">
                {checkoutCopy.confirmation.logIn}
              </Link>
            </>
          )}
        </div>
      )}

      {!isLoading && !error && !accessIssue && order && (
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
                    <li key={item.id} className="border-bottom py-2">
                      <div className="d-flex justify-content-between">
                        <span>
                          {item.product_name}
                          {item.variant_name ? ` (${item.variant_name})` : ''} × {item.quantity}
                        </span>
                        <span>{formatPrice(item.line_total)}</span>
                      </div>
                      {isAuthenticated && order.status === 'delivered' && item.product_slug && item.product_variant_id && (
                        <Link
                          to={`/products/${item.product_slug}`}
                          state={{ reviewPrompt: { orderId: order.id, productVariantId: item.product_variant_id } }}
                          className="small"
                        >
                          {reviewsCopy.writePrompt.cta}
                        </Link>
                      )}
                    </li>
                  ))}
                </ul>

                <div className="d-flex justify-content-between small mb-2">
                  <span className="text-muted">{cartCopy.itemsHeading}</span>
                  <span>{formatPrice(order.totals.subtotal)}</span>
                </div>

                {order.totals.discount_total > 0 && (
                  <div className="d-flex justify-content-between small mb-2 text-success">
                    <span>
                      {cartCopy.discount}
                      {order.totals.discount_code ? ` (${order.totals.discount_code})` : ''}
                    </span>
                    <span>-{formatPrice(order.totals.discount_total)}</span>
                  </div>
                )}

                <div className="d-flex justify-content-between small mb-2">
                  <span className="text-muted">{cartCopy.shipping}</span>
                  <span>{formatPrice(order.totals.shipping_total)}</span>
                </div>

                {order.totals.cod_fee > 0 && (
                  <div className="d-flex justify-content-between small mb-2">
                    <span className="text-muted">{checkoutCopy.paymentStep.methods.cash_on_delivery}</span>
                    <span>+{formatPrice(order.totals.cod_fee)}</span>
                  </div>
                )}

                <hr className="my-2" />

                <div className="d-flex justify-content-between fw-bold fs-5">
                  <span>{cartCopy.grandTotal}</span>
                  <span>{formatPrice(order.totals.grand_total)}</span>
                </div>
              </div>
            </div>

            {(order.status === 'pending' || order.status === 'awaiting_payment') && (
              <div className="alert alert-info">{checkoutCopy.confirmation.paymentNotice}</div>
            )}

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
              <Link to={`/orders/${order.id}/tracking${token ? `?token=${token}` : ''}`} className="btn btn-outline-secondary">
                {trackingCopy.title}
              </Link>
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
