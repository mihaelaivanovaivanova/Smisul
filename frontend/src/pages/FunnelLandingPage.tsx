import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { fetchProduct } from '../api/products';
import { useAsync } from '../hooks/useAsync';
import { useSettings } from '../hooks/useSettings';
import { formatPrice, getVariantPrice } from '../services/productCatalog';
import { trackFunnelAddToCart } from '../services/analytics';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import PriceBlock from '../components/product/PriceBlock';
import StockStatus from '../components/product/StockStatus';
import AddToCartButton from '../components/product/AddToCartButton';
import Seo from '../components/Seo';
import { seo, states } from '../content/copy';

/**
 * The single-product "funnel mode" landing page, ported from
 * D:\Projects\miswak-website's homepage — same sections, images, and copy,
 * seeded as editable content via FunnelContentService (see backend
 * database/seeders/FunnelSeeder.php). Rendered at "/" instead of HomePage
 * when useSettings().funnelModeEnabled is true (see App.tsx).
 */
export default function FunnelLandingPage() {
  const { funnelProductSlug, funnelPackages, funnelContent, isLoading: settingsLoading } = useSettings();
  const location = useLocation();

  const { data: product, isLoading, error } = useAsync(
    () => (funnelProductSlug ? fetchProduct(funnelProductSlug) : Promise.resolve(null)),
    [funnelProductSlug],
    'Продуктът не можа да се зареди.',
  );

  // Navbar's section-anchor nav (Начало/Ползи/Как се използва/Продукти/FAQ)
  // links here as "/#benefits" etc. from other pages — the browser only
  // auto-scrolls to a fragment on a real page load, not an SPA route
  // change, so this replicates that behavior once the page has rendered.
  useEffect(() => {
    if (!location.hash || settingsLoading || isLoading) {
      return;
    }

    document.querySelector(location.hash)?.scrollIntoView({ behavior: 'smooth' });
  }, [location.hash, settingsLoading, isLoading]);

  if (settingsLoading || isLoading) {
    return <LoadingState message={states.loadingDefault} />;
  }

  if (error || !funnelContent || !product) {
    return <ErrorState message={error ?? states.loadingDefault} />;
  }

  const { hero, dark_band, problem, benefits, ingredients, ritual, how_to, packages_intro, labels, testimonials, faq, final_cta } =
    funnelContent;

  return (
    <div className="pb-5 pb-md-0">
      <Seo title={seo.funnelTitle} description={seo.funnelDescription} ogImage="/funnel/miswak-bundle.webp" />

      {/* ---- Hero ---- */}
      <section className="hero section">
        <div className="container hero__content py-5">
          <div className="row align-items-center g-5">
            <div className="col-12 col-lg-6">
              <span className="badge text-bg-light border mb-3">{hero.badge}</span>
              <h1 className="hero__title mb-3">{hero.title}</h1>
              <p className="lead">{hero.body}</p>
              <p className="fw-bold p-3 rounded-3 bg-white border d-inline-block">{hero.highlight}</p>
              <div className="d-flex flex-wrap gap-2 mt-3 mb-4">
                <a href="#packages" className="btn btn-primary btn-lg">
                  {hero.cta_primary}
                </a>
                <a href="#how" className="btn btn-outline-primary btn-lg">
                  {hero.cta_secondary}
                </a>
              </div>
              <div className="row row-cols-1 row-cols-sm-2 g-2">
                {hero.bullets.map((bullet) => (
                  <div className="col" key={bullet}>
                    <div className="border rounded-3 bg-white p-2 small fw-semibold">{bullet}</div>
                  </div>
                ))}
              </div>
            </div>
            <div className="col-12 col-lg-6">
              <img src="/funnel/hero-miswak-hand.webp" alt={product.name} className="img-fluid rounded-4 shadow" />
            </div>
          </div>
        </div>
      </section>

      {/* ---- Dark band ---- */}
      <section className="section section-tint">
        <div className="container">
          <span className="section-eyebrow">{dark_band.eyebrow}</span>
          <h2 className="section-title">{dark_band.title}</h2>
          {dark_band.paragraphs.map((paragraph) => (
            <p className="section-lead" key={paragraph}>
              {paragraph}
            </p>
          ))}
          <p className="fw-bold mb-0">{dark_band.highlight}</p>
        </div>
      </section>

      {/* ---- Problem / awareness ---- */}
      <section className="section section-surface">
        <div className="container">
          <div className="row g-5 align-items-center">
            <div className="col-12 col-lg-5">
              <img src="/funnel/miswak-closeup.jpg" alt={product.name} className="img-fluid rounded-4 shadow" />
            </div>
            <div className="col-12 col-lg-7">
              <span className="section-eyebrow">{problem.eyebrow}</span>
              <h2 className="section-title">{problem.title}</h2>
              <p className="section-lead">{problem.body}</p>
              <p className="fw-bold">{problem.emphasis}</p>
              <div className="row row-cols-1 row-cols-sm-2 g-2 mb-3">
                {problem.bullets.map((bullet) => (
                  <div className="col" key={bullet}>
                    <div className="border rounded-3 p-2 small fw-semibold">{bullet}</div>
                  </div>
                ))}
              </div>
              <a href="#packages" className="btn btn-primary">
                {problem.cta}
              </a>
            </div>
          </div>
        </div>
      </section>

      {/* ---- Benefits ---- */}
      <section className="section section-tint" id="benefits">
        <div className="container">
          <span className="section-eyebrow">{benefits.eyebrow}</span>
          <h2 className="section-title mb-4">{benefits.title}</h2>
          <div className="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
            {benefits.cards.map((card, index) => (
              <div className="col" key={card.title}>
                <div className="benefit-card">
                  <span className="icon-tile mb-3">{String(index + 1).padStart(2, '0')}</span>
                  <h3 className="h6 mb-2">{card.title}</h3>
                  <p className="text-muted small">{card.text}</p>
                  <p className="fw-semibold small mb-0">{card.emphasis}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---- Ingredients ---- */}
      <section className="section section-surface">
        <div className="container">
          <span className="section-eyebrow">{ingredients.eyebrow}</span>
          <h2 className="section-title mb-4">{ingredients.title}</h2>
          <div className="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mb-4">
            {ingredients.cards.map((card) => (
              <div className="col" key={card.title}>
                <div className="benefit-card">
                  <h3 className="h6 mb-2">{card.title}</h3>
                  <p className="text-muted small mb-0">{card.text}</p>
                </div>
              </div>
            ))}
          </div>
          <p className="fw-bold fs-5 text-center mb-0">{ingredients.closing_line}</p>
        </div>
      </section>

      {/* ---- Ritual ---- */}
      <section className="section section-tint">
        <div className="container">
          <div className="row g-5 align-items-center">
            <div className="col-12 col-lg-6">
              <span className="section-eyebrow">{ritual.eyebrow}</span>
              <h2 className="section-title">{ritual.title}</h2>
              {ritual.lines.map((line) => (
                <p className="fw-bold mb-1" key={line}>
                  {line}
                </p>
              ))}
              <a href="#packages" className="btn btn-primary mt-3">
                {ritual.cta}
              </a>
            </div>
            <div className="col-12 col-lg-6">
              <div className="row row-cols-1 row-cols-sm-2 g-3">
                {ritual.steps.map((step) => (
                  <div className="col" key={step.title}>
                    <div className="step-card">
                      <span className="step-card__index d-block mb-2">{step.title}</span>
                      <p className="h5 mb-0">{step.text}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---- How to use ---- */}
      <section className="section section-surface" id="how">
        <div className="container">
          <span className="section-eyebrow">{how_to.eyebrow}</span>
          <h2 className="section-title mb-4">{how_to.title}</h2>
          <div className="row row-cols-1 row-cols-sm-2 g-4">
            {how_to.steps.map((step, index) => (
              <div className="col" key={step.title}>
                <div className="step-card">
                  <span className="step-card__index d-block mb-2">{String(index + 1).padStart(2, '0')}</span>
                  <h3 className="h6 mb-2">{step.title}</h3>
                  <p className="text-muted small mb-0">{step.text}</p>
                </div>
              </div>
            ))}
          </div>
          <p className="text-muted small mt-3 mb-0">{how_to.note}</p>
        </div>
      </section>

      {/* ---- Packages ---- */}
      <section className="section section-tint" id="packages">
        <div className="container">
          <span className="section-eyebrow">{packages_intro.eyebrow}</span>
          <h2 className="section-title">{packages_intro.title}</h2>
          <p className="section-lead mb-5">{packages_intro.intro}</p>

          {funnelPackages.length === 0 && <p className="text-muted">{states.emptyDefaultTitle}</p>}

          <div className="row row-cols-1 row-cols-md-3 g-4">
            {funnelPackages.map((pkg) => {
              const variant = product.variants.find((candidate) => candidate.id === pkg.variant_id);

              if (!variant) {
                return null;
              }

              const price = getVariantPrice(variant);
              const savings = price?.compare_at_amount ? price.compare_at_amount - price.amount : 0;

              return (
                <div className="col" key={pkg.variant_id}>
                  <div className="card h-100 shadow-sm position-relative">
                    <span
                      className="badge position-absolute top-0 start-0 m-3 text-white"
                      style={{ backgroundColor: 'var(--color-terracotta)' }}
                    >
                      {pkg.badge}
                    </span>
                    <div className="ratio ratio-1x1 bg-white">
                      <img src="/funnel/miswak-bundle.webp" alt={product.name} className="object-fit-contain p-4" />
                    </div>
                    <div className="card-body d-flex flex-column">
                      <h3 className="h5">
                        {product.name} — {pkg.detail}
                      </h3>
                      {product.short_description && <p className="text-muted small">{product.short_description}</p>}
                      <div className="d-flex flex-wrap gap-2 mb-2">
                        <span className="badge text-bg-light border">{pkg.value_label}</span>
                        {savings > 0 && (
                          <span className="badge bg-success-subtle text-success-emphasis">
                            Спести {formatPrice(savings, price?.currency)}
                          </span>
                        )}
                      </div>
                      <div className="mb-2">
                        <PriceBlock price={price} size="lg" />
                      </div>
                      <div className="mb-3">
                        <StockStatus inventory={variant.inventory} />
                      </div>
                      <div className="mt-auto">
                        <AddToCartButton
                          key={variant.id}
                          productVariantId={variant.id}
                          inventory={variant.inventory}
                          label={pkg.button_text}
                          onAdded={() => price && trackFunnelAddToCart(price.amount, price.currency)}
                        />
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      {/* ---- "Read the labels" ---- */}
      <section className="section section-surface">
        <div className="container">
          <div className="row g-5 align-items-center">
            <div className="col-12 col-lg-7">
              <span className="section-eyebrow">{labels.eyebrow}</span>
              <h2 className="section-title">{labels.title}</h2>
              {labels.lines.map((line) => (
                <p className="fw-bold mb-1" key={line}>
                  {line}
                </p>
              ))}
              <p className="section-lead">{labels.body}</p>
              <a href="#packages" className="btn btn-primary mt-2">
                {labels.cta}
              </a>
            </div>
            <div className="col-12 col-lg-5">
              <img src="/funnel/miswak-bundle.webp" alt={product.name} className="img-fluid" />
            </div>
          </div>
        </div>
      </section>

      {/* ---- Testimonials ---- */}
      <section className="section section-tint">
        <div className="container">
          <span className="section-eyebrow">{testimonials.eyebrow}</span>
          <h2 className="section-title mb-4">{testimonials.title}</h2>
          <div className="row row-cols-1 row-cols-md-3 g-4">
            {testimonials.quotes.map((quote) => (
              <div className="col" key={quote.name}>
                <figure className="card h-100 p-4 mb-0">
                  <blockquote className="mb-3">&ldquo;{quote.quote}&rdquo;</blockquote>
                  <figcaption className="fw-bold text-muted">{quote.name}</figcaption>
                </figure>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---- FAQ ---- */}
      <section className="section section-surface" id="faq">
        <div className="container">
          <h2 className="section-title mb-4">{faq.title}</h2>
          <div className="accordion" id="funnel-faq-accordion" style={{ maxWidth: '48rem' }}>
            {faq.items.map((item, index) => {
              const headingId = `funnel-faq-heading-${index}`;
              const collapseId = `funnel-faq-collapse-${index}`;

              return (
                <div className="accordion-item" key={item.question}>
                  <h3 className="accordion-header" id={headingId}>
                    <button
                      className="accordion-button collapsed"
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
                    data-bs-parent="#funnel-faq-accordion"
                  >
                    <div className="accordion-body text-muted">{item.answer}</div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      {/* ---- Final CTA ---- */}
      <section className="section section-tint">
        <div className="container text-center">
          <span className="section-eyebrow">{final_cta.eyebrow}</span>
          <h2 className="section-title">{final_cta.title}</h2>
          {final_cta.lines.map((line) => (
            <p className="fw-bold mb-1" key={line}>
              {line}
            </p>
          ))}
          <p className="section-lead">{final_cta.body}</p>
          <p className="text-muted small">{final_cta.trust_line}</p>
          <a href="#packages" className="btn btn-primary btn-lg mt-2">
            {final_cta.cta}
          </a>
        </div>
      </section>

      {/* ---- Sticky mobile buy bar ---- */}
      <div
        className="d-md-none position-fixed bottom-0 start-0 end-0 bg-white border-top p-2"
        style={{ zIndex: 1030 }}
      >
        <a href="#packages" className="btn btn-primary w-100">
          {hero.cta_primary}
        </a>
      </div>
    </div>
  );
}
