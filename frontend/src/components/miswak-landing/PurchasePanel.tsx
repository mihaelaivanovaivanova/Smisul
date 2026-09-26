import { useState } from 'react';
import GalleryCarousel from './GalleryCarousel';
import PackageRadioSelector from './PackageRadioSelector';
import TrustBullets from './TrustBullets';
import StarRating from '../reviews/StarRating';
import { getGalleryMediaForVariant } from '../../services/productCatalog';
import { reviews as reviewsCopy } from '../../content/copy';
import type { PackageOffer } from '../../services/funnelOffers';
import type { Product } from '../../types/product';
import type { ReviewSummary } from '../../types/review';

interface PurchasePanelProps {
  product: Product;
  offers: PackageOffer[];
  reviewSummary: ReviewSummary | null;
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
 */
export default function PurchasePanel({ product, offers, reviewSummary }: PurchasePanelProps) {
  const featuredIndex = offers.findIndex(({ variant }) => variant.pack_size === 5);
  const [selectedIndex, setSelectedIndex] = useState(featuredIndex >= 0 ? featuredIndex : 0);

  const selectedVariant = offers[Math.min(selectedIndex, offers.length - 1)]?.variant;
  const images = getGalleryMediaForVariant(product, selectedVariant);

  return (
    // id="pricing" (not "purchase") — StickyMobileBuyBar/StickyDesktopBuyBar
    // (reused as-is from the funnel components) hardcode href="#pricing".
    <section className="section funnel-hero-tone" id="pricing">
      <div className="container">
        <div className="row g-4 g-lg-5">
          <div className="col-12 col-lg-6">
            <GalleryCarousel images={images} productName={product.name} />
          </div>
          <div className="col-12 col-lg-6">
            <h1 className="miswak-purchase__title">{product.name}</h1>

            {reviewSummary && reviewSummary.review_count > 0 && (
              <div className="miswak-purchase__rating">
                <StarRating rating={reviewSummary.average_rating} />
                <span>{reviewsCopy.reviewCount(reviewSummary.review_count)}</span>
              </div>
            )}

            {product.short_description && <p className="section-lead lead">{product.short_description}</p>}

            <TrustBullets />

            <PackageRadioSelector offers={offers} selectedIndex={selectedIndex} onSelect={setSelectedIndex} />
          </div>
        </div>
      </div>
    </section>
  );
}
