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
 * - Trust row ("funnel.final_cta.trust_items"): delivery / card payment /
 *   returns / quality guarantee (see FunnelSeeder.php's trust_items
 *   comment for the full history). Cash on delivery's item was removed
 *   along with the payment method itself — BOX NOW deliveries are
 *   card-only now (see PaymentMethod::active() on the backend).
 * - Payment logos: Visa/Mastercard/Amex/Apple Pay/Google Pay — all real.
 *   Apple Pay/Google Pay aren't separate checkout flows in this codebase
 *   (see ICardPaymentGateway's docblock); iCard's own hosted card modal
 *   surfaces them as in-modal wallet buttons when the customer's device
 *   supports them, so the claim holds even though there's no distinct
 *   PaymentMethod path for either. Google Pay's mark is a locally-edited
 *   variant of the official one (see public/payments/google-pay.svg's own
 *   comment) — rounded-rectangle badge instead of the default pill/stadium
 *   shape, cropped to the same visual height as the Apple Pay mark beside
 *   it (its stock viewBox has a lot of transparent padding around the
 *   badge, which made it render smaller than Apple Pay at the same
 *   `height`).
 * - Payment copy: funnelAssurance.paymentCopy — the section's own
 *   "Сигурно онлайн плащане" heading was dropped (by request); the
 *   remaining fine print covers this whole logos row.
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
    <section className="section funnel-hero-tone funnel-final-cta" id="delivery-payment-returns">
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
          <p className="funnel-payment-block__copy">{funnelAssurance.paymentCopy}</p>
          <div className="funnel-payment-logos" role="img" aria-label={funnelAssurance.paymentLogosAria}>
            <img src="/payments/visa-2021.svg" alt="Visa" height={18} loading="lazy" />
            <img src="/payments/mastercard.svg" alt="Mastercard" height={30} loading="lazy" />
            <img src="/payments/amex.png" alt="American Express" height={30} loading="lazy" />
            <img src="/payments/apple-pay.svg" alt="Apple Pay" height={30} loading="lazy" />
            <img src="/payments/google-pay.svg" alt="Google Pay" height={30} loading="lazy" />
          </div>
        </div>
      </div>
    </section>
  );
}
