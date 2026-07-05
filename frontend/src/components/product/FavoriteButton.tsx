import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useFavorites } from '../../hooks/useFavorites';
import { getErrorMessage } from '../../api/errors';
import { favorites as favoritesCopy } from '../../content/copy';
import Icon from '../icons/Icon';

interface FavoriteButtonProps {
  productVariantId: number;
  /** Icon-only, for tight spaces like a listing card. */
  compact?: boolean;
  /**
   * Same favorite underneath — "wishlist" only relabels the button for an
   * unavailable product, since "save this" reads as "notify me when it's
   * back" rather than "I like this" in that context. Callers pass this
   * based on the variant's current stock (see ProductPage/ProductCard).
   */
  mode?: 'favorite' | 'wishlist';
}

/**
 * Guests get an inline "log in to save" link instead of the toggle — there's
 * no existing inline-login-prompt pattern in this app to reuse (see the
 * sprint's research), so this establishes one, modeled on ProtectedRoute's
 * `state={{ from: location }}` redirect-back idiom.
 */
export default function FavoriteButton({ productVariantId, compact = false, mode = 'favorite' }: FavoriteButtonProps) {
  const { isAuthenticated } = useAuth();
  const { favoriteVariantIds, toggleFavorite } = useFavorites();
  const location = useLocation();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!isAuthenticated) {
    return (
      <Link
        to="/login"
        state={{ from: location }}
        className={`btn btn-outline-secondary ${compact ? 'btn-sm' : ''}`}
        title={favoritesCopy.loginToSave}
        aria-label={favoritesCopy.loginToSave}
      >
        <Icon name="heart" />
        {!compact && <span className="ms-2">{favoritesCopy.loginToSave}</span>}
      </Link>
    );
  }

  const isFavorited = favoriteVariantIds.has(productVariantId);

  async function handleClick() {
    setIsSubmitting(true);
    setError(null);

    try {
      await toggleFavorite(productVariantId);
    } catch (err) {
      setError(getErrorMessage(err, isFavorited ? favoritesCopy.removeError : favoritesCopy.addError));
    } finally {
      setIsSubmitting(false);
    }
  }

  const label = mode === 'wishlist'
    ? (isFavorited ? favoritesCopy.removeFromWishlist : favoritesCopy.addToWishlist)
    : (isFavorited ? favoritesCopy.remove : favoritesCopy.add);

  return (
    <div>
      <button
        type="button"
        className={`btn btn-outline-secondary ${compact ? 'btn-sm' : ''}`}
        onClick={() => void handleClick()}
        disabled={isSubmitting}
        aria-pressed={isFavorited}
        aria-label={label}
        title={label}
      >
        {isSubmitting ? (
          <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
        ) : (
          <span className={isFavorited ? 'text-danger' : ''}>
            <Icon name="heart" />
          </span>
        )}
        {!compact && <span className="ms-2">{label}</span>}
      </button>
      {mode === 'wishlist' && !compact && (
        <div className="text-muted small mt-1">{favoritesCopy.wishlistNotice}</div>
      )}
      {error && (
        <div className="text-danger small mt-1" aria-live="polite">
          {error}
        </div>
      )}
    </div>
  );
}
