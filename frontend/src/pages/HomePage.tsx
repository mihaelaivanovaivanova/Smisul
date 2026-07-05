import { useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchProduct, fetchProducts } from '../api/products';
import { fetchHomepageContent } from '../api/content';
import { useAsync } from '../hooks/useAsync';
import {
  getActivePromotion,
  getDefaultVariant,
  getPrimaryImage,
  getVariantPrice,
  sortVariantsByPackSize,
} from '../services/productCatalog';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import PriceBlock from '../components/product/PriceBlock';
import StockStatus from '../components/product/StockStatus';
import VariantPicker from '../components/product/VariantPicker';
import AddToCartButton from '../components/product/AddToCartButton';
import Seo from '../components/Seo';
import Icon from '../components/icons/Icon';
import { product as productCopy, seo, states } from '../content/copy';
import type { Product, ProductVariant } from '../types/product';

/** The admin's chosen product (by slug, resolved server-side), falling back to the newest published one when none is set. */
async function fetchFeaturedProduct(slug: string | null): Promise<Product | null> {
  if (slug) {
    return fetchProduct(slug);
  }

  const { data } = await fetchProducts({ sort: 'newest', per_page: 1 });
  return data[0] ?? null;
}

export default function HomePage() {
  const { data: content, isLoading: contentLoading, error: contentError } = useAsync(
    fetchHomepageContent,
    [],
    'Съдържанието не можа да се зареди.',
  );

  // undefined while homepage content is still loading, so we don't fetch a
  // product before knowing whether one was actually chosen.
  const featuredSlug = content ? content.featured.product_slug : undefined;

  const { data: featuredProduct, isLoading, error } = useAsync(
    () => (featuredSlug === undefined ? Promise.resolve(null) : fetchFeaturedProduct(featuredSlug)),
    [featuredSlug],
    'Продуктът не можа да се зареди.',
  );

  const [selectedVariant, setSelectedVariant] = useState<ProductVariant | null>(null);

  const variants = featuredProduct ? sortVariantsByPackSize(featuredProduct.variants) : [];
  const defaultVariant = featuredProduct ? getDefaultVariant(featuredProduct) : undefined;
  const activeVariant = selectedVariant ?? defaultVariant ?? variants[0];
  const price = activeVariant ? getVariantPrice(activeVariant) : undefined;
  const promotion = featuredProduct ? getActivePromotion(featuredProduct) : undefined;
  const image = featuredProduct ? getPrimaryImage(featuredProduct) : undefined;

  if (contentLoading) {
    return <LoadingState message={states.loadingDefault} />;
  }

  if (contentError || !content) {
    return <ErrorState message={contentError ?? states.loadingDefault} />;
  }

  const { hero, featured, benefits, usage, trust, delivery, bio, faq } = content;

  return (
    <div>
      <Seo title={seo.homeTitle} description={seo.homeDescription} />

      {/* ---- Hero ---- */}
      <section className="hero section">
        <div className="container hero__content text-center py-4">
          <span className="section-eyebrow hero__eyebrow">{hero.eyebrow}</span>
          <h1 className="hero__title mb-3">{hero.title}</h1>
          <p className="lead mb-4 mx-auto" style={{ maxWidth: '38rem' }}>
            {hero.subtitle}
          </p>
          <Link to="/search" className="btn btn-primary btn-lg">
            {hero.cta}
          </Link>
        </div>
      </section>

      {/* ---- Featured product ---- */}
      <section className="section section-surface">
        <div className="container">
          <span className="section-eyebrow">{featured.eyebrow}</span>
          <h2 className="section-title">{featured.title}</h2>
          <p className="section-lead mb-4">{featured.description}</p>

          {isLoading && <LoadingState message={states.loadingDefault} />}
          {!isLoading && error && <ErrorState message={error} />}
          {!isLoading && !error && !featuredProduct && <p className="text-muted">{states.emptyDefaultTitle}</p>}

          {!isLoading && !error && featuredProduct && (
            <div className="row g-4 align-items-center">
              <div className="col-12 col-md-5">
                <div className="ratio ratio-1x1 bg-white rounded-3 overflow-hidden border">
                  {image ? (
                    <img src={image.url} alt={image.alt_text ?? featuredProduct.name} className="object-fit-cover" />
                  ) : (
                    <div className="d-flex align-items-center justify-content-center text-muted small">
                      {productCopy.noImage}
                    </div>
                  )}
                </div>
              </div>
              <div className="col-12 col-md-7">
                <h3 className="h4 mb-2">{featuredProduct.name}</h3>
                {featuredProduct.short_description && (
                  <p className="text-muted">{featuredProduct.short_description}</p>
                )}

                <div className="mb-3">
                  <PriceBlock price={price} promotion={promotion} size="lg" />
                </div>

                <div className="mb-3">
                  <StockStatus inventory={activeVariant?.inventory} />
                </div>

                <VariantPicker
                  variants={variants}
                  selectedId={activeVariant?.id ?? 0}
                  onSelect={setSelectedVariant}
                />

                {activeVariant && (
                  <div className="mt-3">
                    <AddToCartButton
                      key={activeVariant.id}
                      productVariantId={activeVariant.id}
                      inventory={activeVariant.inventory}
                    />
                  </div>
                )}

                <Link to={`/products/${featuredProduct.slug}`} className="btn btn-outline-primary mt-2">
                  {featured.cta}
                </Link>
              </div>
            </div>
          )}
        </div>
      </section>

      {/* ---- Benefits ---- */}
      <section className="section section-tint">
        <div className="container">
          <h2 className="section-title">{benefits.title}</h2>
          <p className="section-lead mb-5">{benefits.lead}</p>
          <div className="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
            {benefits.items.map((item) => (
              <div className="col" key={item.title}>
                <div className="benefit-card">
                  <span className="icon-tile mb-3">
                    <Icon name={item.icon} />
                  </span>
                  <h3 className="h6 mb-2">{item.title}</h3>
                  <p className="text-muted small mb-0">{item.text}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---- Natural / bio positioning ---- */}
      <section className="section section-surface">
        <div className="container">
          <div className="row g-5 align-items-center">
            <div className="col-12 col-lg-6">
              <span className="section-eyebrow">{bio.eyebrow}</span>
              <h2 className="section-title">{bio.title}</h2>
              <p className="section-lead">{bio.text}</p>
            </div>
            <div className="col-12 col-lg-6">
              <div className="ratio ratio-4x3 placeholder-visual">
                <span className="placeholder-visual__label">Място за визия на бранда</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---- Usage ---- */}
      <section className="section section-tint">
        <div className="container">
          <h2 className="section-title">{usage.title}</h2>
          <div className="row row-cols-1 row-cols-md-3 g-4 mt-2">
            {usage.steps.map((step, index) => (
              <div className="col" key={step.title}>
                <div className="step-card">
                  <span className="step-card__index d-block mb-2">{String(index + 1).padStart(2, '0')}</span>
                  <h3 className="h6 mb-2">{step.title}</h3>
                  <p className="text-muted small mb-0">{step.text}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---- Trust + delivery ---- */}
      <section className="section section-surface">
        <div className="container">
          <div className="row g-5">
            <div className="col-12 col-lg-6">
              <h2 className="section-title">{trust.title}</h2>
              <ul className="trust-list">
                {trust.items.map((item) => (
                  <li key={item.text}>
                    <span className="icon-tile icon-tile--muted">
                      <Icon name={item.icon} />
                    </span>
                    <span className="pt-1">{item.text}</span>
                  </li>
                ))}
              </ul>
            </div>
            <div className="col-12 col-lg-6">
              <h2 className="section-title">{delivery.title}</h2>
              <div className="d-flex gap-3">
                <span className="icon-tile flex-shrink-0">
                  <Icon name={delivery.icon} />
                </span>
                <p className="text-muted mb-0">{delivery.text}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---- FAQ ---- */}
      <section className="section section-tint">
        <div className="container">
          <h2 className="section-title mb-4">{faq.title}</h2>
          <div className="accordion" id="faq-accordion" style={{ maxWidth: '48rem' }}>
            {faq.items.map((item, index) => {
              const headingId = `faq-heading-${index}`;
              const collapseId = `faq-collapse-${index}`;

              return (
                <div className="accordion-item" key={item.question}>
                  <h3 className="accordion-header" id={headingId}>
                    <button
                      className="accordion-button collapsed faq-question"
                      type="button"
                      data-bs-toggle="collapse"
                      data-bs-target={`#${collapseId}`}
                      aria-expanded="false"
                      aria-controls={collapseId}
                    >
                      {item.question}
                    </button>
                  </h3>
                  <div
                    id={collapseId}
                    className="accordion-collapse collapse"
                    aria-labelledby={headingId}
                    data-bs-parent="#faq-accordion"
                  >
                    <div className="accordion-body text-muted">{item.answer}</div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </section>
    </div>
  );
}
