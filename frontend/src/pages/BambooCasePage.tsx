import { useEffect, useState } from 'react';
import { useProduct } from '../hooks/useProduct';
import { useSettings } from '../hooks/useSettings';
import { useAsync } from '../hooks/useAsync';
import { useReviewIntent } from '../hooks/useReviewIntent';
import { fetchReviewSummary } from '../api/reviews';
import {
  getActivePromotion,
  getDefaultVariant,
  getDownloads,
  getGalleryMediaForVariant,
  getPrimaryImage,
  getVariantPrice,
} from '../services/productCatalog';
import LoadingState from '../components/LoadingState';
import Seo from '../components/Seo';
import Icon from '../components/icons/Icon';
import { TrustIcon } from '../components/funnel/FunnelIcons';
import type { IconName } from '../components/icons/Icon';
import GalleryCarousel from '../components/product/GalleryCarousel';
import ProductDescription from '../components/product/ProductDescription';
import { ProductDownloads } from '../components/product/ProductMediaExtras';
import PriceBlock from '../components/product/PriceBlock';
import StockStatus from '../components/product/StockStatus';
import AddToCartButton from '../components/product/AddToCartButton';
import FavoriteButton from '../components/product/FavoriteButton';
import StarRating from '../components/reviews/StarRating';
import ReviewsSection from '../components/reviews/ReviewsSection';
import NotFoundPage from './NotFoundPage';
import { breadcrumbLabels, funnelAssurance, product as productCopy, reviews as reviewsCopy, seo } from '../content/copy';
import { buildBreadcrumbJsonLd } from '../services/structuredData';

/** The seeded product's slug (MiswakAccessoriesSeeder.php) - ProductPage.tsx routes it here. */
export const BAMBOO_CASE_SLUG = 'bambukov-keis-za-miswak';

/** Delivery, guarantee and returns - the funnel's own line icons (see FunnelIcons.tsx's TrustIcon), shown under the buy button. */
const assuranceItems: { icon: IconName; text: string }[] = [
  { icon: 'truck', text: funnelAssurance.delivery },
  { icon: 'check-badge', text: '100% гаранция за качество' },
  { icon: 'undo', text: funnelAssurance.returns },
];

/** Key facts - taken from the product's own description. */
const keyFacts: { icon: IconName; title: string; text: string }[] = [
  { icon: 'sparkle', title: 'Вентилация', text: 'Два отвора пазят Miswak-а сух между употребите.' },
  { icon: 'shield', title: 'Защита', text: 'Пази от прах и от съдържанието на чантата.' },
  { icon: 'leaf', title: 'Естествен бамбук', text: 'MOSO бамбук за многократна употреба.' },
];


/**
 * Upsell product page for the bamboo Miswak case. It's an add-on to Miswak,
 * so the buy box comes first and the page stays short: key facts, how to use
 * it, then the full description and reviews. Purchase behavior matches the
 * default product layout.
 */
export default function BambooCasePage() {
  const { product, isLoading, error } = useProduct(BAMBOO_CASE_SLUG);
  const { funnelModeEnabled } = useSettings();
  const { writePrompt, openReviewWizard, knownReviewerIdentity } = useReviewIntent();
  const [stickyTop, setStickyTop] = useState(0);

  const { data: reviewSummary } = useAsync(
    async () => (product ? fetchReviewSummary(product.slug) : null),
    [product?.slug],
    reviewsCopy.loadError,
  );

  // Sticky gallery sits just below the frosted navbar; measure it rather than hardcode.
  useEffect(() => {
    function syncStickyTop() {
      const navbar = document.querySelector('.navbar-frosted');
      setStickyTop(navbar ? navbar.getBoundingClientRect().height + 16 : 0);
    }

    syncStickyTop();
    window.addEventListener('resize', syncStickyTop);
    return () => window.removeEventListener('resize', syncStickyTop);
  }, []);

  if (isLoading) {
    return <LoadingState message={productCopy.loading} />;
  }

  if (error || !product) {
    return <NotFoundPage />;
  }

  // Single-pack product - the default variant is the only one.
  const activeVariant = getDefaultVariant(product) ?? product.variants[0];
  const price = activeVariant ? getVariantPrice(activeVariant) : undefined;
  const promotion = getActivePromotion(product);
  const images = getGalleryMediaForVariant(product, activeVariant);
  const downloads = getDownloads(product);
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
    <div className="container bamboo-page">
      <Seo
        title={seoTitle}
        description={seoDescription}
        ogImage={ogImage}
        ogType="product"
        jsonLd={[jsonLd, buildBreadcrumbJsonLd(breadcrumbItems)]}
      />

      <section className="bamboo-hero">
        <div className="row g-4 g-lg-5 align-items-lg-center">
          <div className="col-12 col-lg-6">
            <div className="bamboo-hero__gallery" style={{ top: stickyTop }}>
              <GalleryCarousel images={images} productName={product.name} />
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <p className="bamboo-hero__eyebrow">Не оставяй Miswak-а без дом</p>

            <div className="d-flex align-items-start justify-content-between gap-3">
              <h1 className="bamboo-hero__title">{product.name}</h1>
              {activeVariant && !funnelModeEnabled && <FavoriteButton productVariantId={activeVariant.id} compact />}
            </div>

            {reviewSummary && reviewSummary.review_count > 0 && (
              <div className="bamboo-hero__rating">
                <StarRating rating={reviewSummary.average_rating} size="sm" />
                <span>{reviewsCopy.reviewCount(reviewSummary.review_count)}</span>
              </div>
            )}

            {product.short_description && <p className="bamboo-hero__lead">{product.short_description}</p>}

            <div className="bamboo-hero__price">
              <StockStatus inventory={activeVariant?.inventory} />
              <PriceBlock price={price} promotion={promotion} size="lg" />
            </div>

            {activeVariant && (
              <div className="bamboo-hero__cta">
                <AddToCartButton
                  key={activeVariant.id}
                  productVariantId={activeVariant.id}
                  inventory={activeVariant.inventory}
                  size="md"
                />
              </div>
            )}

            <ul className="bamboo-hero__assurance">
              {assuranceItems.map((item) => (
                <li key={item.text}>
                  <span className="bamboo-hero__assurance-icon" aria-hidden="true">
                    <TrustIcon icon={item.icon} />
                  </span>
                  <span className="bamboo-hero__assurance-text">{item.text}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      <section className="bamboo-facts" aria-label="Основни предимства">
        <div className="row g-3 g-lg-4">
          {keyFacts.map((fact) => (
            <div className="col-12 col-md-4" key={fact.title}>
              <article className="bamboo-fact">
                <span className="bamboo-fact__icon" aria-hidden="true">
                  <Icon name={fact.icon} />
                </span>
                <div>
                  <h3 className="bamboo-fact__title">{fact.title}</h3>
                  <p className="bamboo-fact__text">{fact.text}</p>
                </div>
              </article>
            </div>
          ))}
        </div>
      </section>

      {(product.description || downloads.length > 0) && (
        <section className="bamboo-block">
          <header className="bamboo-block__header">
            <h2 className="bamboo-block__title">{productCopy.descriptionTitle}</h2>
          </header>
          {product.description && <ProductDescription text={product.description} emphasizeSpecs />}
          <ProductDownloads downloads={downloads} />
        </section>
      )}

      <section className="bamboo-block bamboo-block--last">
        <ReviewsSection
          productSlug={product.slug}
          productName={product.name}
          productImageUrl={getPrimaryImage(product)?.url}
          writePrompt={writePrompt}
          openWizard={openReviewWizard}
          knownReviewerIdentity={knownReviewerIdentity}
        />
      </section>
    </div>
  );
}
