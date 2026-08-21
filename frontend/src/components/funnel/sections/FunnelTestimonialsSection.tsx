import { useEffect, useRef, useState } from 'react';
import Icon from '../../icons/Icon';
import StarRating from '../../reviews/StarRating';
import { funnelReviews, reviews as reviewsCopy } from '../../../content/copy';
import type { Review } from '../../../types/review';

interface FunnelTestimonialsSectionProps {
  topReviews: Review[];
}

/**
 * Section 12/20 — Reviews. A horizontally-scrolling carousel (scroll-snap +
 * prev/next arrows) over the top reviews from the reviews API — up to 10 of
 * them (ReviewService::listForProduct's default page size), not the
 * previous static 3-card grid. Deliberately no "see all reviews" link out
 * to the product page anymore: the full paginated list still lives at
 * components/reviews/ReviewsSection.tsx on the product detail page,
 * unchanged, but this landing page no longer sends visitors away from it —
 * by request.
 */
export default function FunnelTestimonialsSection({ topReviews }: FunnelTestimonialsSectionProps) {
  const trackRef = useRef<HTMLDivElement>(null);
  const [canScrollPrev, setCanScrollPrev] = useState(false);
  const [canScrollNext, setCanScrollNext] = useState(false);

  useEffect(() => {
    const track = trackRef.current;
    if (!track) {
      return;
    }

    function updateScrollState() {
      if (!track) {
        return;
      }
      setCanScrollPrev(track.scrollLeft > 4);
      setCanScrollNext(track.scrollLeft + track.clientWidth < track.scrollWidth - 4);
    }

    updateScrollState();
    track.addEventListener('scroll', updateScrollState, { passive: true });
    window.addEventListener('resize', updateScrollState);
    return () => {
      track.removeEventListener('scroll', updateScrollState);
      window.removeEventListener('resize', updateScrollState);
    };
  }, [topReviews]);

  if (topReviews.length === 0) {
    return null;
  }

  function scrollByCard(direction: 1 | -1) {
    const track = trackRef.current;
    const card = track?.querySelector<HTMLElement>('.funnel-review-card');
    if (!track || !card) {
      return;
    }
    const trackGap = parseFloat(getComputedStyle(track).columnGap || '0');
    track.scrollBy({ left: (card.getBoundingClientRect().width + trackGap) * direction, behavior: 'smooth' });
  }

  return (
    <section className="section funnel-hero-tone funnel-divided-section funnel-reviews" id="reviews">
      <div className="container">
        <h2 className="section-title mb-4 text-center">{funnelReviews.title}</h2>
        <div className="funnel-reviews-carousel">
          <button
            type="button"
            className="funnel-reviews-carousel__arrow funnel-reviews-carousel__arrow--prev"
            onClick={() => scrollByCard(-1)}
            disabled={!canScrollPrev}
            aria-label={funnelReviews.prevAria}
          >
            <Icon name="chevron-left" />
          </button>

          <div className="funnel-reviews-carousel__track" ref={trackRef}>
            {topReviews.map((review) => (
              <figure className="funnel-review-card" key={review.id}>
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
            ))}
          </div>

          <button
            type="button"
            className="funnel-reviews-carousel__arrow funnel-reviews-carousel__arrow--next"
            onClick={() => scrollByCard(1)}
            disabled={!canScrollNext}
            aria-label={funnelReviews.nextAria}
          >
            <Icon name="chevron-right" />
          </button>
        </div>
      </div>
    </section>
  );
}
