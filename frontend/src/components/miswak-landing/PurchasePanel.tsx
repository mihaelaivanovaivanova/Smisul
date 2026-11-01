import { useEffect, useState } from 'react';
import GalleryCarousel from '../product/GalleryCarousel';
import PackageRadioSelector from './PackageRadioSelector';
import BundleRadioSelector from './BundleRadioSelector';
import TrustBullets from './TrustBullets';
import StarRating from '../reviews/StarRating';
import TrustAndPaymentRow from '../funnel/TrustAndPaymentRow';
import DispatchPromise from '../funnel/DispatchPromise';
import { getGalleryMediaForVariant, formatPrice } from '../../services/productCatalog';
import { bundleOffer as bundleCopy, reviews as reviewsCopy } from '../../content/copy';
import { miswakTagline } from '../../content/miswakLanding';
import { KIT_GALLERY_IMAGES } from '../../content/kitProducts';
import type { BundleOffer } from '../../services/bundleOffers';
import type { PackageOffer } from '../../services/funnelOffers';

type PurchaseMode = 'kits' | 'sticks';
import type { Product } from '../../types/product';
import type { ReviewSummary } from '../../types/review';
import type { FunnelTrustItem } from '../../types/funnel';

interface PurchasePanelProps {
  product: Product;
  offers: PackageOffer[];
  /** Active kits (GET /v1/bundles) - when non-empty, a "Complete Kits" / "Just Miswak" toggle appears above the buy box, defaulting to kits. Empty hides the toggle and shows only the package selector, exactly as before. */
  bundleOffers?: BundleOffer[];
  reviewSummary: ReviewSummary | null;
  /** Rendered here (desktop only, right after the buy button) instead of
   * MiswakLandingPage's own separate DeliveryPaymentReturnsSection, by
   * request — desktop keeps it inside this column so the sticky gallery
   * stays pinned through it too; mobile still gets it from that separate
   * section (see DeliveryPaymentReturnsSection's className prop, passed
   * "d-lg-none" there for exactly this split). */
  trustItems: FunnelTrustItem[];
  /** Same-day dispatch cutoff (Settings → General) — see DispatchPromise.tsx; renders nothing outside the window. */
  dispatchCutoff: string | null | undefined;
  /** Live free-shipping threshold (Settings → General) — passed through to PackageRadioSelector's badge. */
  freeShippingThreshold?: number;
}

/**
 * The reference's top panel: gallery + buy box side by side (two-column on
 * desktop, stacked on mobile), no separate lifestyle hero banner above it —
 * unlike the live funnel page's HeroSection, this page starts directly
 * here, matching the reference's own structure.
 *
 * Owns the selected package index (not PackageRadioSelector) so it can
 * swap the gallery to that variant's own admin-uploaded photo via
 * getGalleryMediaForVariant — same fallback-to-product-gallery behavior
 * the generic ProductPage.tsx already uses for its own variant picker.
 *
 * Desktop only (matching the col-lg-6 split below): the gallery is pinned
 * in place via plain CSS position: sticky (see .miswak-purchase__gallery-sticky)
 * while the page scrolls normally past the taller info column beside it,
 * same behavior as https://juun.bg/products/creatine-gummies. An earlier
 * version of this reimplemented the same thing with a scroll listener
 * driving explicit position: fixed math, because sticky initially appeared
 * not to hold in real-browser testing — that turned out to be caused by
 * .funnel-page's own `overflow-x: hidden` (see funnel.css), which forces
 * overflow-y to compute as auto and makes it register as a scroll
 * container for sticky's purposes even though it never actually scrolls
 * itself, silently neutering every sticky descendant. Fixed there (switched
 * to `overflow: clip`, which doesn't have that side effect) instead of
 * carrying a JS reimplementation — the manual version also turned out to
 * have real per-frame latency versus native sticky, which was very visible
 * on fast/flung scrolling. `stickyTop` is measured from the live navbar
 * height (not hardcoded) so the photo sticks flush underneath it rather
 * than guessing a pixel offset that could drift if the navbar's own height
 * ever changes.
 */
export default function PurchasePanel({
  product,
  offers,
  bundleOffers = [],
  reviewSummary,
  trustItems,
  dispatchCutoff,
  freeShippingThreshold,
}: PurchasePanelProps) {
  const featuredIndex = offers.findIndex(({ variant }) => variant.pack_size === 5);
  const [selectedIndex, setSelectedIndex] = useState(featuredIndex >= 0 ? featuredIndex : 0);
  const [selectedBundleIndex, setSelectedBundleIndex] = useState(0);

  // "Complete Kits" is the default view whenever any kit exists - the kits
  // arrive from their own fetch (see MiswakLandingPage), so the default is
  // derived rather than stored, and only an explicit toggle click pins it.
  const [chosenMode, setChosenMode] = useState<PurchaseMode | null>(null);
  const hasKits = bundleOffers.length > 0;
  const mode: PurchaseMode = hasKits ? (chosenMode ?? 'kits') : 'sticks';

  const selectedVariant = offers[Math.min(selectedIndex, offers.length - 1)]?.variant;
  // Kits get their own real packaging/lifestyle photos (same gallery as the
  // dedicated kit pages - see KitProductPage.tsx) instead of the plain
  // Miswak-stick variant photo, since a kit is a different physical product.
  const images = mode === 'kits' ? KIT_GALLERY_IMAGES : getGalleryMediaForVariant(product, selectedVariant);

  const [stickyTop, setStickyTop] = useState(0);

  useEffect(() => {
    function syncStickyTop() {
      const navbar = document.querySelector('.navbar-frosted');
      setStickyTop(navbar ? navbar.getBoundingClientRect().height : 0);
    }

    syncStickyTop();
    window.addEventListener('resize', syncStickyTop);
    return () => window.removeEventListener('resize', syncStickyTop);
  }, []);

  const cheapestOffer = offers.reduce<PackageOffer | null>(
    (cheapest, offer) => (cheapest === null || offer.price.amount < cheapest.price.amount ? offer : cheapest),
    null,
  );

  return (
    // id="pricing" (not "purchase") — StickyMobileBuyBar/StickyDesktopBuyBar
    // (reused as-is from the funnel components) hardcode href="#pricing".
    <section className="section funnel-hero-tone" id="pricing">
      <div className="container">
        <div className="row g-4 g-lg-5">
          <div className="col-12 col-lg-6">
            <div className="miswak-purchase__gallery-sticky" style={{ top: stickyTop }}>
              <GalleryCarousel images={images} productName={product.name} />
            </div>
          </div>
          <div className="col-12 col-lg-6">
            <h1 className="miswak-purchase__title">{product.name}</h1>

            {reviewSummary && reviewSummary.review_count > 0 && (
              <div className="miswak-purchase__rating">
                <StarRating rating={reviewSummary.average_rating} size="lg" />
                <span>{reviewsCopy.reviewCount(reviewSummary.review_count)}</span>
              </div>
            )}

            {cheapestOffer && (
              <p className="miswak-purchase__from-price">
                от {formatPrice(cheapestOffer.price.amount, cheapestOffer.price.currency)}
              </p>
            )}

            <p className="miswak-purchase__tagline">{miswakTagline}</p>

            <TrustBullets />

            {hasKits && (
              <div className="miswak-purchase-toggle" role="group" aria-label={bundleCopy.toggleAria}>
                {(['kits', 'sticks'] as const).map((option) => (
                  <button
                    key={option}
                    type="button"
                    aria-pressed={mode === option}
                    className={`miswak-purchase-toggle__option ${mode === option ? 'is-active' : ''}`}
                    onClick={() => setChosenMode(option)}
                  >
                    {option === 'kits' ? bundleCopy.kitsTab : bundleCopy.sticksTab}
                  </button>
                ))}
              </div>
            )}

            {mode === 'kits' ? (
              <BundleRadioSelector
                offers={bundleOffers}
                selectedIndex={selectedBundleIndex}
                onSelect={setSelectedBundleIndex}
                freeShippingThreshold={freeShippingThreshold}
              />
            ) : (
              <PackageRadioSelector
                offers={offers}
                selectedIndex={selectedIndex}
                onSelect={setSelectedIndex}
                freeShippingThreshold={freeShippingThreshold}
              />
            )}

            <div className="text-center mb-3">
              <DispatchPromise cutoff={dispatchCutoff} />
            </div>

            <div className="d-none d-lg-block miswak-purchase__trust">
              <TrustAndPaymentRow trustItems={trustItems} trustRowClassName="miswak-purchase__trust-row" />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
