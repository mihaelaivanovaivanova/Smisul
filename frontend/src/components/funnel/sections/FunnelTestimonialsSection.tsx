import { Link } from 'react-router-dom';
import Icon from '../../icons/Icon';
import StarRating from '../../reviews/StarRating';
import { funnelReviews, reviews as reviewsCopy } from '../../../content/copy';
import type { Review, ReviewSummary } from '../../../types/review';

interface FunnelTestimonialsSectionProps {
  topReviews: Review[];
  reviewSummary: ReviewSummary | null;
  productSlug: string;
}

/**
 * Section 12/20 — Reviews. Top approved reviews straight from the
 * reviews API, unchanged behavior/copy. Named "FunnelTestimonialsSection"
 * (not "ReviewsSection") to avoid colliding with the existing, more
 * full-featured components/reviews/ReviewsSection.tsx used on the
 * product detail page — this one is the lightweight 3-card strip, not a
 * genuine reuse candidate for that component.
 */
export default function FunnelTestimonialsSection({ topReviews, reviewSummary, productSlug }: FunnelTestimonialsSectionProps) {
  if (topReviews.length === 0) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone funnel-divided-section funnel-reviews" id="reviews">
      <div className="container">
        <h2 className="section-title mb-4 text-center">{funnelReviews.title}</h2>
        <div className="row row-cols-1 row-cols-md-3 g-3 g-lg-4">
          {topReviews.map((review) => (
            <div className="col" key={review.id}>
              <figure className="funnel-review-card">
                <StarRating rating={review.rating} />
                <blockquote className="funnel-review-card__body mb-0">{review.body}</blockquote>
                <figcaption className="funnel-review-card__author">
                  {review.author_name}
                  {review.verified_purchase && (
                    <span className="funnel-review-card__verified">
                      <Icon name="check-badge" /> {reviewsCopy.verifiedPurchase}
                    </span>
                  )}
                </figcaption>
              </figure>
            </div>
          ))}
        </div>
        {reviewSummary && reviewSummary.review_count > topReviews.length && (
          <div className="text-center mt-4">
            <Link to={`/products/${productSlug}`} className="funnel-reviews__see-all">
              {funnelReviews.seeAll(reviewSummary.review_count)}
            </Link>
          </div>
        )}
      </div>
    </section>
  );
}
