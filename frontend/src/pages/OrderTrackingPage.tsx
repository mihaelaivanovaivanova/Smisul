import { useEffect, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { fetchShipment } from '../api/checkout';
import { getErrorMessage } from '../api/errors';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import EmptyState from '../components/EmptyState';
import Seo from '../components/Seo';
import { tracking as trackingCopy } from '../content/copy';
import type { Shipment, ShippingCarrier } from '../types/checkout';

const CARRIER_LABELS: Record<ShippingCarrier, string> = {
  speedy: 'Speedy',
  box_now: 'BOX NOW',
};

/**
 * A placeholder tracking view (see the Sprint 8 scope — no live carrier
 * polling here, just the persisted shipment/history ShipmentController
 * already serves). An order with no shipment yet (nothing "prepare backend
 * support" has wired to run automatically) shows an EmptyState rather than
 * an error, since that's the normal state for most orders today.
 */
export default function OrderTrackingPage() {
  const { orderId = '' } = useParams<{ orderId: string }>();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? undefined;

  const [shipment, setShipment] = useState<Shipment | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [notShipped, setNotShipped] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;
    setIsLoading(true);
    setError(null);
    setNotShipped(false);

    fetchShipment(Number(orderId), token)
      .then((fetched) => {
        if (isMounted) setShipment(fetched);
      })
      .catch((err: unknown) => {
        if (!isMounted) return;
        const status = (err as { response?: { status?: number } })?.response?.status;
        if (status === 404) {
          setNotShipped(true);
        } else {
          setError(getErrorMessage(err, trackingCopy.loadError));
        }
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [orderId, token]);

  return (
    <div className="container py-4">
      <Seo title={trackingCopy.seoTitle} />
      <h1 className="h3 mb-4">{trackingCopy.title}</h1>

      {isLoading && <LoadingState message={trackingCopy.loading} />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && notShipped && <EmptyState title={trackingCopy.title} message={trackingCopy.notShippedYet} />}

      {!isLoading && !error && shipment && (
        <div className="row justify-content-center">
          <div className="col-12 col-lg-8">
            <div className="card shadow-sm mb-4">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start mb-3">
                  <div>
                    <div className="text-muted small">{trackingCopy.carrierLabel}</div>
                    <div className="fw-semibold">{CARRIER_LABELS[shipment.carrier]}</div>
                  </div>
                  <span className="badge text-bg-primary">{shipment.status_label}</span>
                </div>

                {shipment.tracking_number && (
                  <p className="mb-1 small">
                    {trackingCopy.trackingNumberLabel}: <strong>{shipment.tracking_number}</strong>
                  </p>
                )}

                {shipment.office_name && (
                  <p className="mb-1 small">
                    {trackingCopy.officeLabel}: {shipment.office_name}
                  </p>
                )}

                {shipment.estimated_delivery_at && (
                  <p className="mb-0 small">
                    {trackingCopy.estimatedDeliveryLabel}: {new Date(shipment.estimated_delivery_at).toLocaleDateString('bg-BG')}
                  </p>
                )}
              </div>
            </div>

            {shipment.events.length > 0 && (
              <div className="card shadow-sm mb-4">
                <div className="card-body">
                  <h2 className="h6 mb-3">{trackingCopy.historyHeading}</h2>
                  <ul className="list-unstyled mb-0">
                    {shipment.events.map((event, index) => (
                      <li key={`${event.status}-${index}`} className="border-bottom py-2">
                        <div className="d-flex justify-content-between">
                          <span className="fw-semibold">{event.label}</span>
                          <span className="text-muted small">{new Date(event.occurred_at).toLocaleString('bg-BG')}</span>
                        </div>
                        {event.description && <div className="text-muted small">{event.description}</div>}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      <div className="text-center mt-3">
        <Link to={`/order-confirmation/${orderId}${token ? `?token=${token}` : ''}`} className="btn btn-outline-secondary">
          {trackingCopy.backToOrder}
        </Link>
      </div>
    </div>
  );
}
