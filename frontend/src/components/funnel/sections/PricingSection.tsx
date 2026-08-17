import DispatchPromise from '../DispatchPromise';
import PackageOffers from '../PackageOffers';
import AddToCartButton from '../../product/AddToCartButton';
import { funnelCheckout } from '../../../content/copy';
import type { PackageOffer } from '../../../services/funnelOffers';
import type { ProductVariant } from '../../../types/product';

interface PricingSectionProps {
  packageOffers: PackageOffer[];
  defaultVariant: ProductVariant | null;
  fallbackCtaLabel: string;
  dispatchCutoff: string | null | undefined;
  onAdded: () => void;
}

/**
 * Section 16/20 — Pricing. Powered by the existing "funnel.checkout" UI
 * copy (previously the "early" placement of the old dual-instance
 * checkout block, shown once right after the hero); now the single,
 * consolidated purchase section per the new architecture, positioned
 * per the requested section order rather than duplicated at two scroll
 * positions. The sticky mobile/desktop buy bars (unchanged, rendered
 * outside the section list) still give a persistent path to purchase
 * regardless of scroll position, so this consolidation doesn't remove
 * the ability to buy early — only the duplicated full section.
 *
 * id renamed from the pre-refactor "buy" to "pricing" — every internal
 * link that used to point at #buy (hero CTA, mid-page CTA, sticky bars,
 * Navbar's order CTA) has been updated to #pricing alongside this.
 *
 * className list kept identical to the old "early" variant's — see
 * BrandStatementSection's comment for why (CSS background/padding
 * dependency, not touched in this pass).
 */
export default function PricingSection({
  packageOffers,
  defaultVariant,
  fallbackCtaLabel,
  dispatchCutoff,
  onAdded,
}: PricingSectionProps) {
  return (
    <section
      className="section funnel-hero-tone funnel-divided-section funnel-final-cta funnel-checkout funnel-checkout--early"
      id="pricing"
    >
      <div className="container">
        <div className="row g-5 align-items-center justify-content-center">
          <div className="col-12 col-xl-9 text-center">
            <h2 className="section-title">{funnelCheckout.title}</h2>
            <p className="section-lead lead">{funnelCheckout.subtitle}</p>
          </div>
        </div>

        <div className="text-center">
          <DispatchPromise cutoff={dispatchCutoff} className="funnel-dispatch--buy" />
        </div>

        {packageOffers.length > 0 ? (
          <PackageOffers offers={packageOffers} showImages />
        ) : (
          defaultVariant && (
            <div className="mb-3 d-flex justify-content-center">
              <AddToCartButton
                key={defaultVariant.id}
                productVariantId={defaultVariant.id}
                inventory={defaultVariant.inventory}
                label={fallbackCtaLabel}
                size="lg"
                hideQuantity
                onAdded={onAdded}
              />
            </div>
          )
        )}

        <p className="funnel-checkout__sales-note">{funnelCheckout.salesNote}</p>
      </div>
    </section>
  );
}
