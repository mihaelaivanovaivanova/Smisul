import { useState } from 'react';
import { Link } from 'react-router-dom';
import { reviews as reviewsCopy } from '../../content/copy';

export interface ReviewReward {
  code: string;
  discount_percent: number;
  expires_at: string | null;
}

/**
 * Shown directly in whichever page just approved the customer's review
 * (AddReviewWizard's pre-verified "done" step, ReviewConfirmPage) rather
 * than making them go check their email for the code - the backend already
 * hands it back in that same response (see ReviewController::
 * rewardPayload()/ProductController::submitReview()). Links to "/" - the
 * Miswak funnel landing page (see App.tsx: funnelModeEnabled renders
 * MiswakLandingPage at the site root), where the package picker lives.
 */
export default function ReviewRewardCard({ reward }: { reward: ReviewReward }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    await navigator.clipboard.writeText(reward.code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  return (
    <div className="card border-dashed mt-3">
      <div className="card-body text-center">
        <p className="fw-semibold mb-2">{reviewsCopy.reward.heading(reward.discount_percent)}</p>

        <div className="d-flex justify-content-center align-items-center gap-2 mb-2">
          <code className="fs-5 px-3 py-2 bg-light rounded">{reward.code}</code>
          <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => void handleCopy()}>
            {copied ? reviewsCopy.reward.copied : reviewsCopy.reward.copy}
          </button>
        </div>

        {reward.expires_at && (
          <p className="text-muted small mb-3">
            {reviewsCopy.reward.expires(new Date(reward.expires_at).toLocaleDateString('bg-BG'))}
          </p>
        )}

        <Link to="/" className="btn btn-primary">
          {reviewsCopy.reward.explorePackages}
        </Link>
      </div>
    </div>
  );
}
