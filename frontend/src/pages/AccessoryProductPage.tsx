import { useEffect, useState } from 'react';
import { useProduct } from '../hooks/useProduct';
import { useSettings } from '../hooks/useSettings';
import { useCart } from '../hooks/useCart';
import { useAsync } from '../hooks/useAsync';
import { useReviewIntent } from '../hooks/useReviewIntent';
import { fetchReviewSummary } from '../api/reviews';
import { fetchCartUpsell } from '../api/cart';
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
import UpsellCard from '../components/cart/UpsellCard';
import NotFoundPage from './NotFoundPage';
import { breadcrumbLabels, funnelAssurance, product as productCopy, reviews as reviewsCopy, seo } from '../content/copy';
import { buildBreadcrumbJsonLd } from '../services/structuredData';

/** Delivery, guarantee and returns - the funnel's own line icons (see FunnelIcons.tsx's TrustIcon), shown under the buy button on every accessory page. */
const ASSURANCE_ITEMS: { icon: IconName; text: string }[] = [
  { icon: 'truck', text: funnelAssurance.delivery },
  { icon: 'check-badge', text: '100% гаранция за качество' },
  { icon: 'undo', text: funnelAssurance.returns },
];

export interface AccessoryKeyFact {
  icon: IconName;
  title: string;
  text: string;
}

export interface AccessoryCrossSell {
  /** The other accessory's slug - its CartService::upsellOffers() entry is looked up by this. */
  slug: string;
  heading: string;
}

interface AccessoryProductPageProps {
  slug: string;
  /** Short terracotta kicker above the title. */
  eyebrow: string;
  /** Three icon facts shown right under the hero, taken from the product's own description. */
  keyFacts: AccessoryKeyFact[];
  /** The sibling accessory's own cross-sell card (e.g. the case's page offers the scraper, and vice versa) - omitted entirely once that product isn't an eligible offer right now. */
  crossSell: AccessoryCrossSell;
}

/**
 * Shared layout for the two Miswak accessory product pages (the bamboo
 * case and the tongue scraper) - same buy box, key facts strip, cross-sell
 * card, description and reviews, same buttons throughout. Only the copy
 * (eyebrow, key facts, which sibling product to cross-sell) differs between
 * them; see BambooCasePage.tsx and ScraperPage.tsx for those two call sites.
 * Purchase behavior matches the default product layout (ProductPage.tsx).
 */
export default function AccessoryProductPage({ slug, eyebrow, keyFacts, crossSell }: AccessoryProductPageProps) {
  const { product, isLoading, error } = useProduct(slug);
  const { funnelModeEnabled } = useSettings();
  const { cart, addItem, openDrawer } = useCart();
  const { writePrompt, openReviewWizard, knownReviewerIdentity } = useReviewIntent();
  const [stickyTop, setStickyTop] = useState(0);

  const { data: reviewSummary } = useAsync(
    async () => (product ? fetchReviewSummary(product.slug) : null),
    [product?.slug],
    reviewsCopy.loadError,
  );

  // Same eligibility the cart drawer and checkout use (see CartService::
  // upsellOffers()) — only the sibling accessory's own offer is shown here,
  // never this page's own product.
  const { data: upsellOffers } = useAsync(fetchCartUpsell, [cart], '');
  const crossSellOffer = (upsellOffers ?? []).find((offer) => offer.product.slug === crossSell.slug);

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
            <p className="bamboo-hero__eyebrow">{eyebrow}</p>

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
              {ASSURANCE_ITEMS.map((item) => (
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

      {crossSellOffer && (
        <section className="bamboo-block">
          <header className="bamboo-block__header">
            <h2 className="bamboo-block__title">{crossSell.heading}</h2>
          </header>
          <UpsellCard offer={crossSellOffer} onAdd={addItem} onAdded={openDrawer} className="cart-drawer-upsell--card" />
        </section>
      )}

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
