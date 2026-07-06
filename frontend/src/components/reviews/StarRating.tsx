import Icon from '../icons/Icon';

interface StarRatingProps {
  rating: number;
  /** Renders as a set of clickable buttons instead of a static display. */
  onChange?: (rating: number) => void;
  size?: 'sm' | 'lg';
  ariaLabel?: string;
}

const STARS = [1, 2, 3, 4, 5];

export default function StarRating({ rating, onChange, size = 'sm', ariaLabel }: StarRatingProps) {
  const iconClass = size === 'lg' ? 'fs-3' : '';

  if (onChange) {
    return (
      <div role="radiogroup" aria-label={ariaLabel ?? 'Rating'} className="d-flex gap-1">
        {STARS.map((star) => (
          <button
            key={star}
            type="button"
            role="radio"
            aria-checked={star === rating}
            aria-label={`${star}`}
            className={`btn btn-link p-0 border-0 ${iconClass}`}
            onClick={() => onChange(star)}
          >
            <span className={star <= rating ? 'text-warning' : 'text-muted'}>
              <Icon name="star" />
            </span>
          </button>
        ))}
      </div>
    );
  }

  return (
    <span aria-label={ariaLabel ?? `${rating} / 5`} className={`d-inline-flex gap-1 ${iconClass}`}>
      {STARS.map((star) => (
        <span key={star} className={star <= Math.round(rating) ? 'text-warning' : 'text-muted'} aria-hidden="true">
          <Icon name="star" />
        </span>
      ))}
    </span>
  );
}
