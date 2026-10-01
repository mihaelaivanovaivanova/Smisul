import { TrustIcon } from './FunnelIcons';
import { funnelAssurance } from '../../content/copy';
import type { FunnelTrustItem } from '../../types/funnel';

interface TrustAndPaymentRowProps {
  trustItems: FunnelTrustItem[];
  /** Extra class(es) on the trust row itself — e.g. to override its
   * desktop nowrap when placed somewhere narrower than a full section
   * (see PurchasePanel.tsx's own use of this). */
  trustRowClassName?: string;
}

/**
 * The actual trust-row + payment-logos content, extracted out of
 * DeliveryPaymentReturnsSection so PurchasePanel.tsx can place the exact
 * same content (same data, same markup) inside its own narrower right
 * column on desktop, without nesting a whole <section>/<container> inside
 * another one. DeliveryPaymentReturnsSection itself now just wraps this in
 * its section/container — see that file's own docblock for what the
 * content actually is and where each piece traces to.
 */
export default function TrustAndPaymentRow({ trustItems, trustRowClassName = '' }: TrustAndPaymentRowProps) {
  return (
    <>
      <div className={`funnel-trust-row funnel-final-cta__trust ${trustRowClassName}`.trim()}>
        {trustItems.map((item) => (
          <div className="funnel-trust-item" key={item.label}>
            <TrustIcon icon={item.icon} />
            <span className="funnel-trust-item__label">{item.label}</span>
          </div>
        ))}
      </div>

      <div className="funnel-payment-block">
        <div className="funnel-payment-logos" role="img" aria-label={funnelAssurance.paymentLogosAria}>
          <img src="/payments/visa-2021.svg" alt="Visa" height={18} loading="lazy" />
          <img src="/payments/mastercard.svg" alt="Mastercard" height={30} loading="lazy" />
          <img src="/payments/amex.png" alt="American Express" height={30} loading="lazy" />
          <img src="/payments/apple-pay.svg" alt="Apple Pay" height={30} loading="lazy" />
          <img src="/payments/google-pay.svg" alt="Google Pay" height={30} loading="lazy" />
        </div>
      </div>
    </>
  );
}
