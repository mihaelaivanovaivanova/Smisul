import TrustAndPaymentRow from '../TrustAndPaymentRow';
import type { FunnelTrustItem } from '../../../types/funnel';

interface DeliveryPaymentReturnsSectionProps {
  trustItems: FunnelTrustItem[];
  /** Miswak page passes "d-lg-none" here — on desktop this same content
   * renders inside PurchasePanel's own right column instead (see its
   * TrustAndPaymentRow usage), so this top-level section is mobile-only
   * there. Unused (no-op) on the live funnel page. */
  className?: string;
}

/**
 * Section 17/20 — Delivery / Payment / Returns, rebuilt to show only facts
 * that trace to real configuration:
 *
 * - Trust row ("funnel.final_cta.trust_items"): delivery / card payment /
 *   returns / quality guarantee / cash-on-delivery (see FunnelSeeder.php's
 *   trust_items comment for the full history — the "100% гаранция за
 *   качество" and "Наложен платеж" items were both dropped at one point
 *   and are both back by request; COD specifically traces to
 *   PaymentService::availablePaymentMethods() allowing CashOnDelivery for
 *   Speedy orders).
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
 *   `height`). The cash-on-delivery icon that used to sit at the end of
 *   this row (and the "Сигурно онлайн плащане" fine print above it) were
 *   both dropped by request — COD is still covered by the trust row's own
 *   "Наложен платеж" item above.
 *
 * className list keeps "funnel-final-cta" purely for a mobile CSS rule
 * (`.funnel-final-cta .funnel-trust-item` tightens padding at <=575px —
 * see funnel.css) that this content relied on in its old location; not a
 * new dependency, just preserved so nothing shifts visually. Does not
 * carry the "funnel-checkout" classes since this section has no purchase
 * mechanics of its own.
 */
export default function DeliveryPaymentReturnsSection({ trustItems, className = '' }: DeliveryPaymentReturnsSectionProps) {
  return (
    <section
      className={`section funnel-hero-tone funnel-final-cta ${className}`.trim()}
      id="delivery-payment-returns"
    >
      <div className="container">
        <TrustAndPaymentRow trustItems={trustItems} />
      </div>
    </section>
  );
}
