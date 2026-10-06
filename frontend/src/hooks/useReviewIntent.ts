import { useEffect, useState } from 'react';
import { useLocation, useSearchParams } from 'react-router-dom';
import { fetchOrderReviewIdentity } from '../api/checkout';

export interface ReviewPromptState {
  reviewPrompt?: { orderId: number; productVariantId: number };
}

export interface KnownReviewerIdentity {
  email: string;
  displayName: string;
  orderId: number;
  expires: string;
  signature: string;
}

/**
 * Review-related entry points a product page has to honor: in-app
 * navigation state (e.g. OrderConfirmationPage's "write a review" link) and
 * the shareable `?write_review=1` links from review emails. Extracted from
 * ProductPage.tsx so every product page layout handles them the same way.
 *
 * The shareable link opens the guest-friendly wizard instead of the
 * authenticated form, since an email recipient usually isn't logged in -
 * the wizard checks eligibility by the typed email at submission time.
 * Signed identity links resolve server-side (see OrderThirtyDayReminderMail::reviewUrl),
 * and an expired or tampered one silently falls back to the wizard's own
 * ask-for-email-and-name step.
 */
export function useReviewIntent() {
  const location = useLocation();
  const [searchParams] = useSearchParams();
  const writePrompt = (location.state as ReviewPromptState | null)?.reviewPrompt;

  const wantsReviewWizard = searchParams.get('write_review') === '1';
  const reviewOrderId = searchParams.get('order_id');
  const reviewExpires = searchParams.get('expires');
  const reviewSignature = searchParams.get('signature');
  const [knownReviewerIdentity, setKnownReviewerIdentity] = useState<KnownReviewerIdentity | undefined>(undefined);
  // Starts true (nothing to resolve) unless there's actually an order to
  // look up - avoids flashing the wizard's email/name step for a moment
  // before the fetch below resolves and the identity becomes known.
  const [reviewerIdentityResolved, setReviewerIdentityResolved] = useState(reviewOrderId === null);

  useEffect(() => {
    if (reviewOrderId === null || reviewExpires === null || reviewSignature === null) {
      return;
    }

    let cancelled = false;
    fetchOrderReviewIdentity(Number(reviewOrderId), { expires: reviewExpires, signature: reviewSignature })
      .then((identity) => {
        if (!cancelled) {
          setKnownReviewerIdentity({
            email: identity.email,
            displayName: identity.display_name,
            orderId: Number(reviewOrderId),
            expires: reviewExpires,
            signature: reviewSignature,
          });
        }
      })
      .catch(() => {
        // Expired or tampered link, or the order no longer exists - fine,
        // just fall back to the wizard's own email/name step.
      })
      .finally(() => {
        if (!cancelled) {
          setReviewerIdentityResolved(true);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [reviewOrderId, reviewExpires, reviewSignature]);

  return {
    writePrompt,
    openReviewWizard: wantsReviewWizard && reviewerIdentityResolved,
    knownReviewerIdentity,
  };
}
