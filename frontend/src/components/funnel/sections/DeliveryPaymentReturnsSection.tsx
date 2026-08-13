import { TrustIcon } from '../FunnelIcons';
import { funnelAssurance } from '../../../content/copy';
import type { FunnelTrustItem } from '../../../types/funnel';

interface DeliveryPaymentReturnsSectionProps {
  trustItems: FunnelTrustItem[];
}

/**
 * Section 17/20 — Delivery / Payment / Returns, rebuilt to show only facts
 * that trace to real configuration:
 *
 * - Trust row ("funnel.final_cta.trust_items"): now 3 items (delivery /
 *   payment / returns) — dropped the old 4th "100% гаранция за качество"
 *   item, a vague claim with no backing policy behind it (see
 *   FunnelSeeder.php's trust_items comment).
 * - Payment logos: Visa/Mastercard/Amex/Apple Pay/Google Pay — all real.
 *   Apple Pay/Google Pay aren't separate checkout flows in this codebase
 *   (see ICardPaymentGateway's docblock); iCard's own hosted card modal
 *   surfaces them as in-modal wallet buttons when the customer's device
 *   supports them, so the claim holds even though there's no distinct
 *   PaymentMethod path for either.
 * - Payment heading/copy: funnelAssurance.paymentHeading/paymentCopy,
 *   replacing the old single paragraph that also justified card-only
 *   checkout via the BIO/ECO philosophy — removed, since the real reason
 *   is simply that iCard is the only integrated gateway today.
 *
 * className list keeps "funnel-final-cta" purely for a mobile CSS rule
 * (`.funnel-final-cta .funnel-trust-item` tightens padding at <=575px —
 * see funnel.css) that this content relied on in its old location; not a
 * new dependency, just preserved so nothing shifts visually. Does not
 * carry the "funnel-checkout" classes since this section has no purchase
 * mechanics of its own.
 */
export default function DeliveryPaymentReturnsSection({ trustItems }: DeliveryPaymentReturnsSectionProps) {
  return (
    <section className="section funnel-hero-tone funnel-divided-section funnel-final-cta" id="delivery-payment-returns">
      <div className="container">
        <div className="funnel-trust-row funnel-final-cta__trust">
          {trustItems.map((item) => (
            <div className="funnel-trust-item" key={item.label}>
              <TrustIcon icon={item.icon} />
              <span className="funnel-trust-item__label">{item.label}</span>
            </div>
          ))}
        </div>

        <div className="funnel-payment-block">
          <h3 className="funnel-payment-block__title">{funnelAssurance.paymentHeading}</h3>
          <p className="funnel-payment-block__copy">{funnelAssurance.paymentCopy}</p>
          <div className="funnel-payment-logos" role="img" aria-label={funnelAssurance.paymentLogosAria}>
            <img src="/payments/visa-2021.svg" alt="Visa" height={18} loading="lazy" />
            <img src="/payments/mastercard.svg" alt="Mastercard" height={30} loading="lazy" />
            <img src="/payments/amex.png" alt="American Express" height={30} loading="lazy" />
            <img src="/payments/apple-pay.svg" alt="Apple Pay" height={30} loading="lazy" />
            {/* Taller than the other marks (30) — the G Pay mark's thin
                outline and light-gray wordmark read visually smaller than
                the bolder Visa/Mastercard/Amex/Apple Pay marks at the same
                height, so it needs a bump to look the same size. */}
            <img src="/payments/google-pay.svg" alt="Google Pay" height={45} loading="lazy" />
          </div>
        </div>
      </div>
    </section>
  );
}
