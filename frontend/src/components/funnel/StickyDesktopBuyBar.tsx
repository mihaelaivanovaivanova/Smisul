import StarRating from '../reviews/StarRating';
import { funnelReviews, reviews as reviewsCopy } from '../../content/copy';
import type { Media } from '../../types/product';
import type { ReviewSummary } from '../../types/review';

interface StickyDesktopBuyBarProps {
  visible: boolean;
  barImage: Media | undefined;
  productName: string;
  fromPriceLabel: string | null;
  reviewSummary: ReviewSummary | null;
  ctaLabel: string;
}

/**
 * Persistent desktop buy bar (reveals once the hero scrolls out of view —
 * see FunnelLandingPage's IntersectionObserver). Not one of the page's 20
 * numbered sections; unchanged from before the refactor beyond the
 * #buy->#pricing anchor rename. Relocated out of FunnelLandingPage.tsx to
 * keep that file focused on composition.
 */
export default function StickyDesktopBuyBar({
  visible,
  barImage,
  productName,
  fromPriceLabel,
  reviewSummary,
  ctaLabel,
}: StickyDesktopBuyBarProps) {
  if (!visible) {
    return null;
  }

  return (
    <div className="funnel-desktop-bar d-none d-md-block">
      <div className="container d-flex align-items-center justify-content-between gap-3">
        <div className="d-flex align-items-center gap-3">
          {barImage && <img src={barImage.url} alt="" loading="lazy" className="funnel-desktop-bar__image" />}
          <div>
            <div className="d-flex align-items-baseline gap-3">
              <strong className="funnel-desktop-bar__name">{productName}</strong>
              {fromPriceLabel && <span className="funnel-desktop-bar__price">{fromPriceLabel}</span>}
            </div>
            {reviewSummary && (
              <div className="funnel-desktop-bar__rating">
                <StarRating
                  rating={reviewSummary.average_rating}
                  ariaLabel={funnelReviews.average(reviewSummary.average_rating.toFixed(1))}
                />
                <span className="funnel-desktop-bar__rating-count">{reviewsCopy.reviewCount(reviewSummary.review_count)}</span>
              </div>
            )}
          </div>
        </div>
        <a href="#pricing" className="btn btn-primary">
          {ctaLabel}
        </a>
      </div>
    </div>
  );
}
