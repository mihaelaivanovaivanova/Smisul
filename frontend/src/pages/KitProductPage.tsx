import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useProduct } from '../hooks/useProduct';
import { useCart } from '../hooks/useCart';
import { useAsync } from '../hooks/useAsync';
import { useReviewIntent } from '../hooks/useReviewIntent';
import { fetchProduct } from '../api/products';
import { fetchReviewSummary } from '../api/reviews';
import { fetchBundleOffers } from '../services/bundleOffers';
import { getErrorMessage } from '../api/errors';
import { getDownloads, getPrimaryImage } from '../services/productCatalog';
import { trackFunnelAddToCart } from '../services/analytics';
import LoadingState from '../components/LoadingState';
import Seo from '../components/Seo';
import Icon from '../components/icons/Icon';
import { TrustIcon } from '../components/funnel/FunnelIcons';
import type { IconName } from '../components/icons/Icon';
import GalleryCarousel from '../components/product/GalleryCarousel';
import ProductDescription from '../components/product/ProductDescription';
import { ProductDownloads } from '../components/product/ProductMediaExtras';
import PriceBlock from '../components/product/PriceBlock';
import StarRating from '../components/reviews/StarRating';
import ReviewsSection from '../components/reviews/ReviewsSection';
import NotFoundPage from './NotFoundPage';
import {
  breadcrumbLabels,
  bundleOffer as bundleCopy,
  funnelAssurance,
  product as productCopy,
  reviews as reviewsCopy,
  seo,
  stock as stockCopy,
} from '../content/copy';
import { KIT_BONUS_ITEMS, KIT_COMPONENT_COPY, KIT_GALLERY_IMAGES } from '../content/kitProducts';
import { buildBreadcrumbJsonLd } from '../services/structuredData';

const MISWAK_SLUG = 'miswak';

/** Delivery, guarantee and returns - same line icons AccessoryProductPage shows under its buy button. */
const ASSURANCE_ITEMS: { icon: IconName; text: string }[] = [
  { icon: 'truck', text: funnelAssurance.delivery },
  { icon: 'check-badge', text: '100% гаранция за качество' },
  { icon: 'undo', text: funnelAssurance.returns },
];

export interface KitKeyFact {
  icon: IconName;
  title: string;
  text: string;
}

export interface KitCrossSell {
  slug: string;
  heading: string;
}

interface KitProductPageProps {
  /** The kit's own slug (ProductBundle.slug) - resolved against GET /v1/bundles, same list the main page's toggle fetches. */
  slug: string;
  /** Short terracotta kicker above the title. */
  eyebrow: string;
  /** Three icon facts shown right under the hero. */
  keyFacts: KitKeyFact[];
  /** The other kit's own cross-sell card (1-person page offers the 2-person kit, and vice versa) - its name is resolved live from GET /v1/bundles, not hardcoded, so it can never drift from the other kit's own admin-edited name. */
  crossSell: KitCrossSell;
}

/**
 * A kit's own product page - same layout/classes as AccessoryProductPage.tsx
 * (shared between the bamboo case and tongue scraper pages): hero gallery +
 * buy box, key facts strip, cross-sell card, description, reviews. Not
 * built on AccessoryProductPage itself since a kit isn't a backend Product
 * (see ProductBundle - no catalog record, no own description/SEO fields):
 * this resolves the kit from GET /v1/bundles by slug instead of useProduct,
 * and reuses the Miswak product only for gallery photos (matched to the
 * kit's own pack size), description and reviews - see OnePersonKitPage.tsx/
 * TwoPersonKitPage.tsx for the two real call sites.
 */
export default function KitProductPage({ slug, eyebrow, keyFacts, crossSell }: KitProductPageProps) {
  const { product } = useProduct(MISWAK_SLUG);
  const { data: bundleOffers, isLoading } = useAsync(fetchBundleOffers, [], '');
  const { addBundle, openDrawer } = useCart();
  const { writePrompt, openReviewWizard, knownReviewerIdentity } = useReviewIntent();
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Resolved ahead of the loading/not-found guards below (hooks must run
  // unconditionally every render) so the fetches right after can already
  // use it.
  const kit = bundleOffers?.find((offer) => offer.bundle.slug === slug);

  // Both kits' own catalog shell variant ids (see backend's
  // KitShellProductService) - pooled so both kit pages show the SAME
  // combined review set, rather than the 1-Person Kit's page only ever
  // showing reviews left for that one kit and the 2-Person Kit's page only
  // its own (which, before kits had real reviews of their own, is what
  // left one page with zero - pooling avoids a repeat of that). Undefined
  // (not an empty array) until bundleOffers has loaded, so the fetches
  // below fall back to every review of this kit's own shell product
  // instead of matching nothing.
  const kitShellVariantIds = bundleOffers
    ? bundleOffers.flatMap((offer) => (offer.bundle.shell_product_variant_id !== null ? [offer.bundle.shell_product_variant_id] : []))
    : undefined;

  // The kit's own lightweight catalog "shell" product (see
  // KitShellProductService) - its description is admin-editable via
  // /admin/products and renders in the "Описание" block below. Deferred
  // until `kit` resolves (its slug, same as the shell's own, isn't known
  // before then).
  const { data: shellProduct } = useAsync(
    async () => (kit ? fetchProduct(kit.bundle.slug) : null),
    [kit?.bundle.slug],
    productCopy.loading,
  );

  const { data: reviewSummary } = useAsync(
    async () => (kit ? fetchReviewSummary(kit.bundle.slug, kitShellVariantIds) : null),
    [kit?.bundle.slug, kitShellVariantIds?.join(',')],
    reviewsCopy.loadError,
  );

  if (isLoading || !product) {
    return <LoadingState message={productCopy.loading} />;
  }

  if (!kit) {
    return <NotFoundPage />;
  }

  const crossSellKit = bundleOffers?.find((offer) => offer.bundle.slug === crossSell.slug);

  const { bundle, price, currency, retailValue, description, contents, isAvailable } = kit;
  const images = KIT_GALLERY_IMAGES;
  // The shell product's own long-form description, admin-editable via
  // /admin/products (not the Miswak product's own description, which is
  // about the stick alone - see the shellProduct fetch above).
  const longDescription = shellProduct?.description;
  // The usage-guide PDF IS still genuinely about this kit too, the same
  // printed card that ships inside the box - reused from the Miswak
  // product's own media rather than re-uploaded per kit.
  const downloads = getDownloads(product);
  // One card per distinct product the kit contains (never per component row -
  // a kit could in principle list two variants of the same product).
  const includedProducts = bundle.items.filter(
    (component, index) => bundle.items.findIndex((candidate) => candidate.product?.slug === component.product?.slug) === index,
  );

  const priceForBlock = { amount: price, currency, compare_at_amount: retailValue, is_on_sale: retailValue !== null };

  async function handleAdd(): Promise<void> {
    setIsPending(true);
    setError(null);

    try {
      await addBundle(bundle.id, 1);
      openDrawer();
      trackFunnelAddToCart(price, currency);
    } catch (err) {
      setError(getErrorMessage(err, bundleCopy.addError));
    } finally {
      setIsPending(false);
    }
  }

  const seoTitle = `${bundle.name}${seo.productTitleSuffix}`;
  const seoDescription = contents.length > 0 ? `${bundle.name}: ${contents.join(', ')}.` : seo.productDescriptionFallback;
  const ogImage = images[0]?.url ?? getPrimaryImage(product)?.url ?? null;

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: bundle.name,
    description: seoDescription,
    image: images.map((image) => image.url),
    sku: bundle.slug,
    inLanguage: 'bg',
    offers: {
      '@type': 'Offer',
      priceCurrency: currency,
      price: price.toFixed(2),
      availability: isAvailable ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
      url: `${window.location.origin}/products/${bundle.slug}`,
    },
  };

  const breadcrumbItems = [
    { label: breadcrumbLabels.home, to: '/' },
    { label: bundle.name },
  ];

  return (
    <div className="container bamboo-page">
      <Seo title={seoTitle} description={seoDescription} ogImage={ogImage} ogType="product" jsonLd={[jsonLd, buildBreadcrumbJsonLd(breadcrumbItems)]} />

      <section className="bamboo-hero">
        <div className="row g-4 g-lg-5 align-items-lg-center">
          <div className="col-12 col-lg-6">
            <div className="bamboo-hero__gallery">
              <GalleryCarousel images={images} productName={bundle.name} />
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <p className="bamboo-hero__eyebrow">{eyebrow}</p>

            <h1 className="bamboo-hero__title">{bundle.name}</h1>

            {reviewSummary && reviewSummary.review_count > 0 && (
              <div className="bamboo-hero__rating">
                <StarRating rating={reviewSummary.average_rating} size="sm" />
                <span>{reviewsCopy.reviewCount(reviewSummary.review_count)}</span>
              </div>
            )}

            {description && <p className="bamboo-hero__lead">{description}</p>}

            {(contents.length > 0 || KIT_BONUS_ITEMS.length > 0) && (
              <ul className="bamboo-hero__contents">
                {contents.map((line) => (
                  <li key={line} className="bamboo-hero__contents-item">
                    {line}
                  </li>
                ))}
                {KIT_BONUS_ITEMS.length > 0 && (
                  <>
                    <li className="bamboo-hero__contents-label">🎁 {bundleCopy.bonusLabel} 🎁</li>
                    <li className="bamboo-hero__bonus-box-wrapper">
                      <ul className="bamboo-hero__bonus-box">
                        {KIT_BONUS_ITEMS.map((line) => (
                          <li key={line} className="bamboo-hero__contents-item bamboo-hero__contents-item--bonus">
                            {line}
                          </li>
                        ))}
                      </ul>
                    </li>
                  </>
                )}
              </ul>
            )}

            <div className="bamboo-hero__price">
              <span className={`badge badge-stock badge-stock--${isAvailable ? 'in' : 'out'}`}>
                {isAvailable ? stockCopy.inStock : stockCopy.outOfStock}
              </span>
              <PriceBlock price={priceForBlock} size="lg" />
            </div>

            <div className="bamboo-hero__cta">
              <button type="button" className="btn btn-primary btn-md" onClick={() => void handleAdd()} disabled={isPending || !isAvailable}>
                {isPending && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
                {isAvailable ? bundleCopy.addToCart(bundle.name) : bundleCopy.outOfStock}
              </button>
              <div aria-live="polite">{error && <div className="text-danger small mt-2">{error}</div>}</div>
            </div>

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

      <section className="bamboo-block bamboo-includes" aria-label="Какво точно включва">
        <header className="bamboo-block__header">
          <h2 className="bamboo-block__title">Какво точно получаваш</h2>
        </header>
        <div className="bamboo-includes__list">
          {includedProducts.map((component) => {
            const copy = component.product ? KIT_COMPONENT_COPY[component.product.slug] : undefined;
            const totalUnits = component.quantity * (component.pack_size ?? 1);

            return (
              <article key={component.product_variant_id} className="bamboo-includes__item">
                {component.product && (
                  <Link to={`/products/${component.product.slug}`} className="bamboo-includes__image-link">
                    {component.product.primary_image ? (
                      <img
                        src={component.product.primary_image.url}
                        alt={component.product.primary_image.alt_text ?? component.product.name}
                        className="bamboo-includes__image"
                        width={72}
                        height={72}
                        loading="lazy"
                      />
                    ) : (
                      <span className="bamboo-includes__image bamboo-includes__image--placeholder" aria-hidden="true" />
                    )}
                  </Link>
                )}
                <div className="bamboo-includes__body">
                  <h3 className="bamboo-includes__name">{component.product?.name ?? component.variant_name}</h3>
                  {copy && <p className="bamboo-includes__info">{copy.info} {copy.benefit}</p>}
                  {component.product && (
                    <Link to={`/products/${component.product.slug}`} className="bamboo-includes__link">
                      Виж продукта →
                    </Link>
                  )}
                </div>
                <div className="bamboo-includes__qty">
                  <span className="bamboo-includes__qty-number">{totalUnits}</span>
                  <span className="bamboo-includes__qty-unit">бр.</span>
                </div>
              </article>
            );
          })}
          {KIT_BONUS_ITEMS.map((bonus) => (
            <article key={bonus} className="bamboo-includes__item bamboo-includes__item--bonus">
              <span className="bamboo-includes__image bamboo-includes__image--bonus" aria-hidden="true">
                🎁
              </span>
              <div className="bamboo-includes__body">
                <h3 className="bamboo-includes__name">{bonus}</h3>
              </div>
              <div className="bamboo-includes__qty">
                <span className="bamboo-includes__bonus-badge">БОНУС</span>
              </div>
            </article>
          ))}
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

      {crossSellKit && (
        <section className="bamboo-block">
          <header className="bamboo-block__header">
            <h2 className="bamboo-block__title">{crossSell.heading}</h2>
          </header>
          <div className="d-flex align-items-center justify-content-between flex-wrap gap-3 p-3 border rounded-3">
            <span className="fw-semibold">{crossSellKit.bundle.name}</span>
            <Link to={`/products/${crossSell.slug}`} className="btn btn-outline-primary btn-sm">
              Виж комплекта
            </Link>
          </div>
        </section>
      )}

      {(longDescription || downloads.length > 0) && (
        <section className="bamboo-block">
          <header className="bamboo-block__header">
            <h2 className="bamboo-block__title">{productCopy.descriptionTitle}</h2>
          </header>
          {longDescription && <ProductDescription text={longDescription} />}
          <ProductDownloads downloads={downloads} />
        </section>
      )}

      <section className="bamboo-block bamboo-block--last">
        <ReviewsSection
          productSlug={bundle.slug}
          productName={bundle.name}
          productImageUrl={(shellProduct ? getPrimaryImage(shellProduct)?.url : undefined) ?? images[0]?.url}
          productVariantIds={kitShellVariantIds}
          writePrompt={writePrompt}
          openWizard={openReviewWizard}
          knownReviewerIdentity={knownReviewerIdentity}
        />
      </section>
    </div>
  );
}
