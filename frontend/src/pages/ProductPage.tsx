import { useEffect, useState } from 'react';
import { useLocation, useParams } from 'react-router-dom';
import { useProduct } from '../hooks/useProduct';
import { useSettings } from '../hooks/useSettings';
import {
  getActivePromotion,
  getDefaultVariant,
  getDownloads,
  getGalleryImagesForVariant,
  getVariantPrice,
  sortVariantsByPackSize,
} from '../services/productCatalog';
import { resolvePackageOffers } from '../services/funnelOffers';
import LoadingState from '../components/LoadingState';
import Seo from '../components/Seo';
import Icon from '../components/icons/Icon';
import ProductGallery from '../components/product/ProductGallery';
import ProductDescription from '../components/product/ProductDescription';
import { ProductDownloads } from '../components/product/ProductMediaExtras';
import VariantPicker from '../components/product/VariantPicker';
import PackageOfferSummary from '../components/product/PackageOfferSummary';
import PriceBlock from '../components/product/PriceBlock';
import StockStatus from '../components/product/StockStatus';
import AddToCartButton from '../components/product/AddToCartButton';
import FavoriteButton from '../components/product/FavoriteButton';
import ReviewsSection from '../components/reviews/ReviewsSection';
import NotFoundPage from './NotFoundPage';
import { buildBreadcrumbJsonLd } from '../services/structuredData';
import { breadcrumbLabels, funnelAssurance, product as productCopy, seo } from '../content/copy';
import type { ProductVariant } from '../types/product';

interface ReviewPromptState {
  reviewPrompt?: { orderId: number; productVariantId: number };
}

export default function ProductPage() {
  const { slug = '' } = useParams<{ slug: string }>();
  const location = useLocation();
  const { funnelModeEnabled, funnelPackages } = useSettings();
  const { product, isLoading, error } = useProduct(slug);
  const [selectedVariant, setSelectedVariant] = useState<ProductVariant | null>(null);
  const writePrompt = (location.state as ReviewPromptState | null)?.reviewPrompt;

  /**
   * Switching pack size swaps the gallery to that variant's own photo
   * (see getGalleryImagesForVariant/ProductGallery's key remount) — without
   * this, the very first time a given pack size is picked, the browser has
   * never fetched that photo before and shows a blank gallery until it
   * loads. Every variant photo is small (one JPEG each), so preloading the
   * full set up front — not just the active one — makes every subsequent
   * switch instant from cache instead of only fixing the second click
   * onward.
   */
  useEffect(() => {
    if (!product) {
      return;
    }

    const urls = new Set<string>();
    const collectImageUrls = (media: typeof product.media) => {
      media.forEach((item) => {
        if (item.mime_type?.startsWith('image/') ?? true) {
          urls.add(item.url);
        }
      });
    };

    collectImageUrls(product.media);
    product.variants.forEach((variant) => variant.media && collectImageUrls(variant.media));

    urls.forEach((url) => {
      const preloadImage = new Image();
      preloadImage.src = url;
    });
  }, [product]);

  if (isLoading) {
    return <LoadingState message={productCopy.loading} />;
  }

  // The API returns 404 for unknown/unpublished slugs; useProduct only
  // surfaces a message string (not the HTTP status), so any fetch failure
  // here is treated as "not found" rather than a distinct transient error.
  if (error || !product) {
    return <NotFoundPage />;
  }

  const variants = sortVariantsByPackSize(product.variants);
  const activeVariant = selectedVariant ?? getDefaultVariant(product) ?? variants[0];
  const price = activeVariant ? getVariantPrice(activeVariant) : undefined;
  const promotion = getActivePromotion(product);
  const images = getGalleryImagesForVariant(product, activeVariant);
  const downloads = getDownloads(product);
  // The funnel landing page's per-pack marketing copy (badge/tagline/
  // discount/per-unit price) — shown once for the active pack size via
  // PackageOfferSummary below the price, rather than repeated inside
  // every VariantPicker option. Resolved independent of funnelModeEnabled:
  // this is content data, not the landing-page override.
  const packageOffers = resolvePackageOffers(product, funnelPackages);
  const activeOffer = packageOffers.find((offer) => offer.variant.id === activeVariant?.id);
  const category = product.categories[0];

  const seoTitle = product.seo?.meta_title ?? `${product.name}${seo.productTitleSuffix}`;
  const seoDescription = product.seo?.meta_description ?? product.short_description ?? seo.productDescriptionFallback;
  const ogImage = product.seo?.og_image_url ?? images[0]?.url ?? null;

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.short_description ?? product.description ?? undefined,
    image: images.map((image) => image.url),
    sku: activeVariant?.sku,
    inLanguage: 'bg',
    ...(activeVariant &&
      price && {
        offers: {
          '@type': 'Offer',
          priceCurrency: price.currency,
          price: price.amount.toFixed(2),
          availability: activeVariant.inventory?.is_in_stock
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
          url: `${window.location.origin}/products/${product.slug}`,
        },
      }),
  };

  const breadcrumbItems = [
    { label: breadcrumbLabels.home, to: '/' },
    ...(category ? [{ label: category.name, to: `/categories/${category.slug}` }] : []),
    { label: product.name },
  ];

  return (
    <div className="container py-4 py-md-5">
      <Seo
        title={seoTitle}
        description={seoDescription}
        ogImage={ogImage}
        ogType="product"
        jsonLd={[jsonLd, buildBreadcrumbJsonLd(breadcrumbItems)]}
      />

      <div className="row g-5 mt-1">
        <div className="col-12 col-lg-6">
          {/* Not remounted (no key) across variant switches on purpose —
              ProductGallery holds the previous photo on screen until the
              newly selected one has actually finished loading, which only
              works if it stays mounted; a remount would reset straight to
              the new (possibly not-yet-loaded) photo and reintroduce the
              blank-flash this was built to avoid. It resets its own active
              thumbnail internally when the images set changes. */}
          <ProductGallery images={images} productName={product.name} />
        </div>
        <div className="col-12 col-lg-6">
          <div className="d-flex align-items-start justify-content-between gap-3 mb-2">
            <h1 className="product-detail__title mb-0">{product.name}</h1>
            {activeVariant && !funnelModeEnabled && <FavoriteButton productVariantId={activeVariant.id} compact />}
          </div>

          {product.short_description && <p className="lead">{product.short_description}</p>}

          <div className="mb-3">
            {activeOffer ? (
              <PackageOfferSummary offer={activeOffer} offers={packageOffers} />
            ) : (
              <PriceBlock price={price} promotion={promotion} size="lg" />
            )}
          </div>

          <div className="mb-4">
            <StockStatus inventory={activeVariant?.inventory} />
          </div>

          <VariantPicker variants={variants} selectedId={activeVariant?.id ?? 0} onSelect={setSelectedVariant} />

          {activeVariant && (
            <div className="product-detail__add-to-cart mt-5">
              <AddToCartButton
                key={activeVariant.id}
                productVariantId={activeVariant.id}
                inventory={activeVariant.inventory}
              />
            </div>
          )}

          {/* AddToCartButton renders nothing when out of stock — this fills
              that gap with a clear next step instead of leaving it empty. */}
          {activeVariant && !activeVariant.inventory?.is_in_stock && !funnelModeEnabled && (
            <FavoriteButton key={activeVariant.id} productVariantId={activeVariant.id} mode="wishlist" />
          )}

          <ul className="trust-list product-detail__assurance mt-4">
            <li>
              <span className="icon-tile icon-tile--muted">
                <Icon name="truck" />
              </span>
              <span className="pt-1">{funnelAssurance.delivery}</span>
            </li>
            <li>
              <span className="icon-tile icon-tile--muted">
                <Icon name="undo" />
              </span>
              <span className="pt-1">{funnelAssurance.returns}</span>
            </li>
          </ul>
        </div>
      </div>

      <div className="row mt-5">
        <div className="col-12 col-lg-8">
          {product.description && (
            <div className="mb-5">
              <h2 className="section-title">{productCopy.descriptionTitle}</h2>
              <ProductDescription text={product.description} />
            </div>
          )}

          <ProductDownloads downloads={downloads} />
        </div>
      </div>

      <div className="row mt-5">
        <div className="col-12 col-lg-8">
          <ReviewsSection productSlug={product.slug} writePrompt={writePrompt} />
        </div>
      </div>
    </div>
  );
}
