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
  /** Defaults to "pricing", the canonical anchor every CTA links to (#pricing). Override for a second, non-anchor placement (see FunnelLandingPage's early instance right after the Hero) so the two don't collide as duplicate DOM ids. */
  id?: string;
  /** Both default true. The early instance right after the Hero hides the subtitle and sales-note copy — by request, so it reads as a compact buy box rather than repeating the full pitch the numbered #pricing section below already makes. */
  showSubtitle?: boolean;
  showSalesNote?: boolean;
}

/**
 * Section 16/20 — Pricing. Powered by the existing "funnel.checkout" UI
 * copy. Once consolidated to a single instance (see git history), but the
 * dual placement is back by request: FunnelLandingPage now renders this
 * twice — once right after the Hero (id="pricing-early", not a link
 * target) and once here, in its numbered slot (id="pricing", the anchor
 * every CTA/sticky bar links to). Both instances are otherwise identical;
 * PackageOffers/AddToCartButton have no shared DOM ids or radio-group
 * `name`s, so two live copies on the page don't collide.
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
  id = 'pricing',
  showSubtitle = true,
  showSalesNote = true,
}: PricingSectionProps) {
  return (
    <section
      className="section funnel-hero-tone funnel-final-cta funnel-checkout funnel-checkout--early"
      id={id}
    >
      <div className="container">
        <div className="row g-5 align-items-center justify-content-center">
          <div className="col-12 col-xl-9 text-center">
            <h2 className="section-title">{funnelCheckout.title}</h2>
            {showSubtitle && <p className="section-lead lead">{funnelCheckout.subtitle}</p>}
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

        {showSalesNote && <p className="funnel-checkout__sales-note">{funnelCheckout.salesNote}</p>}
      </div>
    </section>
  );
}
