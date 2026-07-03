import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { recordPaymentReturn } from '../api/payment';
import { getErrorMessage } from '../api/errors';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import Seo from '../components/Seo';
import { payment as paymentCopy } from '../content/copy';
import type { Payment } from '../types/payment';

/**
 * The shared landing point after iCard's hosted page (see ICARD_RETURN_URL)
 * — both a genuine success and a failure land here, since the gateway
 * doesn't distinguish them by URL. recordPaymentReturn() is the
 * authoritative check (never the query string alone); a resolved Failed/
 * Cancelled payment redirects on to its own dedicated page.
 */
export default function PaymentSuccessPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const orderId = Number(searchParams.get('order'));
  const token = searchParams.get('token') ?? undefined;

  const [payment, setPayment] = useState<Payment | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const requestRef = useRef<ReturnType<typeof recordPaymentReturn> | null>(null);

  useEffect(() => {
    // recordPaymentReturn() has real side effects (a return-transaction log
    // entry, a gateway reconciliation call) — the request itself must only
    // ever be sent once, so it's memoized in a ref rather than re-sent by
    // StrictMode's dev-only double effect invocation. Each effect
    // invocation still attaches its own isMounted-scoped .then() to that
    // same shared promise, rather than skipping entirely — skipping would
    // leave the second (actually-kept-mounted) invocation with no handler
    // at all once the first invocation's cleanup already flipped its own
    // isMounted to false.
    let isMounted = true;

    requestRef.current ??= recordPaymentReturn(orderId, token);

    requestRef.current
      .then((result) => {
        if (!isMounted) {
          return;
        }

        if (result.status === 'failed' || result.status === 'expired') {
          navigate(`/payment/failed?order=${orderId}${token ? `&token=${token}` : ''}`, { replace: true });
          return;
        }

        if (result.status === 'cancelled') {
          navigate(`/payment/cancelled?order=${orderId}${token ? `&token=${token}` : ''}`, { replace: true });
          return;
        }

        setPayment(result);
      })
      .catch((err: unknown) => {
        if (isMounted) {
          setError(getErrorMessage(err, paymentCopy.loadError));
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

  const isConfirmed = payment?.status === 'paid' || payment?.status === 'authorized';

  return (
    <div className="container py-5">
      <Seo title={paymentCopy.success.seoTitle} />

      {isLoading && <LoadingState message={paymentCopy.loading} />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && payment && (
        <div className="row justify-content-center text-center">
          <div className="col-12 col-lg-6">
            {isConfirmed ? (
              <>
                <h1 className="h3 mb-3">{paymentCopy.success.title}</h1>
                <p className="text-muted mb-4">{paymentCopy.success.message}</p>
              </>
            ) : (
              <>
                <h1 className="h3 mb-3">{paymentCopy.success.stillProcessingTitle}</h1>
                <p className="text-muted mb-4">{paymentCopy.success.stillProcessingMessage}</p>
              </>
            )}

            <div className="d-flex justify-content-center gap-2 flex-wrap">
              <Link to={`/order-confirmation/${orderId}${token ? `?token=${token}` : ''}`} className="btn btn-primary">
                {paymentCopy.success.viewOrder}
              </Link>
              <Link to="/search" className="btn btn-outline-primary">
                {paymentCopy.success.continueShopping}
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
