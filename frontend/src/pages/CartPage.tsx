import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useCart } from '../hooks/useCart';
import { getErrorMessage } from '../api/errors';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import EmptyState from '../components/EmptyState';
import Seo from '../components/Seo';
import CartItemRow from '../components/cart/CartItemRow';
import CartTotalsSummary from '../components/cart/CartTotalsSummary';
import { cart as cartCopy } from '../content/copy';

export default function CartPage() {
  const { cart, isLoading, error, clear } = useCart();
  const [isClearing, setIsClearing] = useState(false);
  const [clearError, setClearError] = useState<string | null>(null);

  async function handleClear(): Promise<void> {
    setIsClearing(true);
    setClearError(null);

    try {
      await clear();
    } catch (err) {
      setClearError(getErrorMessage(err, cartCopy.clearError));
    } finally {
      setIsClearing(false);
    }
  }

  return (
    <div className="container py-4">
      <Seo title={cartCopy.seoTitle} description={cartCopy.seoDescription} />
      <h1 className="mb-4">{cartCopy.title}</h1>

      {isLoading && <LoadingState message={cartCopy.loading} />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && cart && cart.items.length === 0 && (
        <EmptyState title={cartCopy.empty.title} message={cartCopy.empty.message} />
      )}

      {!isLoading && !error && cart && cart.items.length > 0 && (
        <div className="row g-4">
          <div className="col-12 col-lg-8">
            <div className="card shadow-sm">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-center mb-2">
                  <h2 className="h6 mb-0">{cartCopy.itemsHeading}</h2>
                  <button
                    type="button"
                    className="btn btn-link btn-sm text-danger text-decoration-none p-0"
                    onClick={() => void handleClear()}
                    disabled={isClearing}
                  >
                    {cartCopy.clear}
                  </button>
                </div>

                {clearError && <div className="text-danger small mb-2">{clearError}</div>}

                {cart.items.map((item) => (
                  <CartItemRow key={item.id} item={item} />
                ))}
              </div>
            </div>

            <Link to="/search" className="btn btn-outline-primary mt-3">
              {cartCopy.continueShopping}
            </Link>
          </div>

          <div className="col-12 col-lg-4">
            <CartTotalsSummary totals={cart.totals} />
            <Link to="/checkout" className="btn btn-primary w-100 mt-3">
              {cartCopy.checkoutCta}
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}
