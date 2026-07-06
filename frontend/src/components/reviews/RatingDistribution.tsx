import { reviews as reviewsCopy } from '../../content/copy';
import type { ReviewSummary } from '../../types/review';

interface RatingDistributionProps {
  summary: ReviewSummary;
}

const STARS = [5, 4, 3, 2, 1];

export default function RatingDistribution({ summary }: RatingDistributionProps) {
  const total = summary.review_count;

  return (
    <div>
      {STARS.map((star) => {
        const count = summary.distribution[String(star)] ?? 0;
        const percent = total > 0 ? Math.round((count / total) * 100) : 0;

        return (
          <div key={star} className="d-flex align-items-center gap-2 small mb-1">
            <span className="text-nowrap" style={{ width: '4.5rem' }}>
              {reviewsCopy.starLabel(star)}
            </span>
            <div className="progress flex-grow-1" role="progressbar" aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100} style={{ height: '0.5rem' }}>
              <div className="progress-bar bg-warning" style={{ width: `${percent}%` }} />
            </div>
            <span className="text-muted text-end" style={{ width: '2rem' }}>
              {count}
            </span>
          </div>
        );
      })}
    </div>
  );
}
