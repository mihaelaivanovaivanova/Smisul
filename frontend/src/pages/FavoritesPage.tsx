import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useFavorites } from '../hooks/useFavorites';
import { getErrorMessage } from '../api/errors';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import EmptyState from '../components/EmptyState';
import Seo from '../components/Seo';
import Breadcrumbs from '../components/Breadcrumbs';
import PriceBlock from '../components/product/PriceBlock';
import StockStatus from '../components/product/StockStatus';
import { breadcrumbLabels, favorites as favoritesCopy, product as productCopy } from '../content/copy';

export default function FavoritesPage() {
  const { favorites, isLoading, error, toggleFavorite } = useFavorites();
  const [removingVariantId, setRemovingVariantId] = useState<number | null>(null);
  const [removeError, setRemoveError] = useState<string | null>(null);

  async function handleRemove(variantId: number) {
    setRemovingVariantId(variantId);
    setRemoveError(null);
    try {
      await toggleFavorite(variantId);
    } catch (err) {
      setRemoveError(getErrorMessage(err, favoritesCopy.removeError));
    } finally {
      setRemovingVariantId(null);
    }
  }

  return (
    <div className="container py-4">
      <Seo title={favoritesCopy.seoTitle} description={favoritesCopy.seoDescription} />
      <Breadcrumbs items={[{ label: breadcrumbLabels.home, to: '/' }, { label: favoritesCopy.title }]} />
      <h1 className="mb-4 mt-3">{favoritesCopy.title}</h1>

      {isLoading && <LoadingState message={favoritesCopy.loading} />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && favorites.length === 0 && (
        <EmptyState title={favoritesCopy.emptyTitle} message={favoritesCopy.emptyMessage} />
      )}

      {!isLoading && !error && favorites.length === 0 && (
        <div className="mt-3">
          <Link to="/search" className="btn btn-primary">
            {favoritesCopy.emptyCta}
          </Link>
        </div>
      )}

      {removeError && <ErrorState message={removeError} />}

      {!isLoading && !error && favorites.length > 0 && (
        <div className="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
          {favorites.map((favorite) => {
            const variant = favorite.product_variant;
            const product = variant.product;
            const price = variant.prices.find((candidate) => candidate.currency === 'EUR');

            return (
              <div className="col" key={favorite.id}>
                <div className="card h-100 shadow-sm">
                  {product && (
                    <Link to={`/products/${product.slug}`} className="text-decoration-none text-body">
                      <div className="ratio ratio-1x1 bg-white product-card__image-wrap">
                        {product.primary_image ? (
                          <img
                            src={product.primary_image.url}
                            alt={product.primary_image.alt_text ?? product.name}
                            className="object-fit-cover"
                          />
                        ) : (
                          <div className="d-flex align-items-center justify-content-center text-muted small">
                            {productCopy.noImage}
                          </div>
                        )}
                      </div>
                    </Link>
                  )}

                  <div className="card-body d-flex flex-column gap-2">
                    <h3 className="h6 mb-0">
                      {product ? <Link to={`/products/${product.slug}`} className="text-decoration-none">{product.name}</Link> : variant.name}
                    </h3>
                    <div className="text-muted small">{variant.name}</div>
                    <PriceBlock price={price} />
                    <StockStatus inventory={variant.inventory} />

                    <button
                      type="button"
                      className="btn btn-outline-danger btn-sm mt-auto"
                      disabled={removingVariantId === variant.id}
                      onClick={() => void handleRemove(variant.id)}
                    >
                      {removingVariantId === variant.id && (
                        <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />
                      )}
                      {favoritesCopy.remove}
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
