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
 *   returns / quality guarantee / cash-on-delivery (see FunnelSeeder.php's
 *   trust_items comment for the full history — the "100% гаранция за
 *   качество" and "Наложен платеж" items were both dropped at one point
 *   and are both back by request; COD specifically traces to
 *   PaymentService::availablePaymentMethods() allowing CashOnDelivery for
 *   BoxNow orders).
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
 *   `height`). Last is a plain icon (public/icons/cash-on-delivery.png,
 *   CC0/no-attribution per its iconpacks.net source) for "Наложен платеж"
 *   — no label here (icon-only, by request), unlike the trust row's own
 *   "Наложен платеж" item, which keeps the hand-drawn Icon.tsx banknote
 *   glyph and its label (also by request — the two aren't meant to match).
 * - Payment copy: funnelAssurance.paymentCopy — the section's own
 *   "Сигурно онлайн плащане" heading was dropped (by request); the
 *   remaining fine print covers this whole logos row, cash icon included.
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
            {/* Not a card-network logo (there's no "brand mark" for cash) —
                covers the same CashOnDelivery option the trust row's own
                "Наложен платеж" item (icon + label) already advertises;
                icon-only here since this row is otherwise bare marks. */}
            <img src="/icons/cash-on-delivery.png" alt="Наложен платеж" height={30} loading="lazy" />
          </div>
        </div>
      </div>
    </section>
  );
}
