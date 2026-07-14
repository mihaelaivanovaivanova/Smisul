import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { fetchProduct } from '../api/products';
import { useAsync } from '../hooks/useAsync';
import { useSettings } from '../hooks/useSettings';
import { trackFunnelAddToCart } from '../services/analytics';
import { getVariantPrice } from '../services/productCatalog';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import AddToCartButton from '../components/product/AddToCartButton';
import Icon from '../components/icons/Icon';
import type { IconName } from '../components/icons/Icon';
import Seo from '../components/Seo';
import { seo, states } from '../content/copy';

/**
 * Trust-item icons that have a real cropped photo/icon match (see
 * public/funnel/v2) render that instead of the hand-drawn Icon.tsx SVG —
 * closer to the reference layout. Icon names with no match (e.g. the
 * final CTA's truck/lock/check-badge/undo) fall back to Icon.tsx.
 */
const TRUST_ICON_IMAGES: Partial<Record<IconName, string>> = {
  leaf: 'icon-natural-100-circle',
  recycle: 'icon-biodegradable-circle',
  'no-plastic': 'icon-no-plastic-circle',
  envelope: 'icon-easy-carry-circle',
};

function TrustIcon({ icon }: { icon: IconName }) {
  const image = TRUST_ICON_IMAGES[icon];

  if (image) {
    return (
      <span className="funnel-trust-item__icon funnel-trust-item__icon--photo">
        <img src={`/funnel/v2/${image}.png`} alt="" />
      </span>
    );
  }

  return (
    <span className="funnel-trust-item__icon">
      <Icon name={icon} />
    </span>
  );
}

/**
 * Same idea as TRUST_ICON_IMAGES, for the "why" section's cards — real
 * cropped, background-removed icon art instead of the hand-drawn Icon.tsx
 * SVG for the 3 icons that have a photo match.
 */
const WHY_ICON_IMAGES: Partial<Record<IconName, string>> = {
  clock: 'icon-why-clock',
  tooth: 'icon-why-tooth',
  globe: 'icon-why-globe',
};

function WhyIcon({ icon }: { icon: IconName }) {
  const image = WHY_ICON_IMAGES[icon];

  if (image) {
    return (
      <span className="funnel-why-card__icon funnel-why-card__icon--photo">
        <img src={`/funnel/v2/${image}.png`} alt="" />
      </span>
    );
  }

  return (
    <span className="funnel-why-card__icon">
      <Icon name={icon} />
    </span>
  );
}

/**
 * Same idea again, for the history section's 4 stat icons.
 */
const STAT_ICON_IMAGES: Partial<Record<IconName, string>> = {
  hourglass: 'icon-stat-hourglass',
  leaf: 'icon-stat-leaf',
  users: 'icon-stat-users',
  'check-badge': 'icon-stat-research',
};

function StatIcon({ icon }: { icon: IconName }) {
  const image = STAT_ICON_IMAGES[icon];

  if (image) {
    return (
      <span className="funnel-stat-item__icon funnel-stat-item__icon--photo">
        <img src={`/funnel/v2/${image}.png`} alt="" />
      </span>
    );
  }

  return (
    <span className="funnel-stat-item__icon">
      <Icon name={icon} />
    </span>
  );
}

/**
 * The single-product "funnel mode" landing page. Content/copy comes from
 * FunnelContentService (see backend database/seeders/FunnelSeeder.php);
 * the 9 sections below match that seeder's FUNNEL_SECTIONS list one to
 * one. Photography lives at /funnel/v2/*.png (cropped from a reference
 * composite — see git history for provenance; several are still
 * placeholder-quality and expected to be swapped for real photography
 * later, per an explicit choice made when building this page).
 */

export default function FunnelLandingPage() {
  const { funnelProductSlug, funnelContent, isLoading: settingsLoading } = useSettings();
  const location = useLocation();
  const [activeFaqIndex, setActiveFaqIndex] = useState<number | null>(null);

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

  const { hero, intro, why, history, features, from_tree, awareness, final_cta, faq } = funnelContent;
  const defaultVariant = product.variants.find((variant) => variant.is_default) ?? product.variants[0] ?? null;
  const price = defaultVariant ? getVariantPrice(defaultVariant) : null;

  // object-position per image so the wider 2:1 crop (see funnel.css) keeps
  // each photo's actual subject in frame instead of a blind center-crop.
  const whyImages = [
    { file: '03-bag-pocket', focus: '50% 55%' }, // miswak stick + bag zipper detail
    { file: '04-mouth-bite', focus: '50% 40%' }, // mouth/teeth, avoid cropping into the chin
    { file: '05-seedling', focus: '50% 25%' }, // sprout tip near the top of the frame
  ];
  const featureIcons = [
    'icon-feature-natural-100',
    'icon-feature-biodegradable',
    'icon-feature-no-plastic',
    'icon-feature-easy-carry',
    'icon-feature-travel',
    'icon-feature-no-batteries',
    'icon-feature-no-waste',
    'icon-feature-natural-daily',
  ];
  const fromTreeIcons = [
    'icon-sustainable-harvest-circle',
    'icon-hand-selected-circle',
    'icon-gentle-processing-circle',
  ];

  return (
    <div className="funnel-page pb-5 pb-md-0">
      <Seo title={seo.funnelTitle} description={seo.funnelDescription} ogImage="/funnel/v2/01-hero-sticks.png" />

      {/* ---- Hero ---- */}
      <section className="funnel-hero section">
        <div className="container">
          <div className="row align-items-start g-5">
            <div className="col-12 col-lg-5">
              <h1 className="funnel-hero__title mb-3">{hero.title}</h1>
              <p className="lead">{hero.body}</p>
              <div className="d-flex flex-wrap align-items-center gap-2 mt-3 mb-4">
                <a href="#buy" className="btn btn-primary btn-lg">
                  {hero.cta_primary}
                </a>
                <a href="#why" className="btn btn-outline-secondary">
                  {hero.cta_secondary}
                </a>
              </div>
              <div className="funnel-trust-row">
                {hero.trust_items.map((item) => (
                  <div className="funnel-trust-item" key={item.label}>
                    <TrustIcon icon={item.icon} />
                    <span className="funnel-trust-item__label">{item.label}</span>
                  </div>
                ))}
              </div>
            </div>
            <div className="col-12 col-lg-7">
              <div className="funnel-photo funnel-hero__image">
                <img src="/funnel/v2/01-hero-sticks.png" alt={product.name} />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---- Intro: "Не винаги новото е по-доброто" ---- */}
      <section className="section funnel-hero-tone funnel-divided-section" id="benefits">
        <div className="container">
          <div className="row g-5 align-items-center">
            <div className="col-12 col-lg-6">
              <div className="funnel-photo" style={{ aspectRatio: '4 / 3' }}>
                <img src="/funnel/v2/02-desert-tree.png" alt="Дървото Salvadora Persica в естествената си среда" />
              </div>
            </div>
            <div className="col-12 col-lg-6">
              <h2 className="section-title">{intro.title}</h2>
              {intro.paragraphs.map((paragraph) => (
                <p className="section-lead lead" key={paragraph}>
                  {paragraph}
                </p>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ---- Why Miswak ---- */}
      <section className="section funnel-hero-tone funnel-divided-section" id="why">
        <div className="container">
          <h2 className="section-title mb-4 text-center">
            {why.title.replace('Miswak?', '')}
            <span className="funnel-eyebrow-accent">Miswak?</span>
          </h2>
          <div className="row row-cols-1 row-cols-md-3 g-5 funnel-why-row">
            {why.cards.map((card, index) => (
              <div className="col" key={card.title}>
                <div className="funnel-why-card">
                  <WhyIcon icon={card.icon} />
                  <div>
                    <h3 className="h6 mb-2">{card.title}</h3>
                    <p className="section-lead lead mb-0">{card.text}</p>
                  </div>
                </div>
                <div className="funnel-photo funnel-why-card__photo">
                  <img
                    src={`/funnel/v2/${(whyImages[index] ?? whyImages[0]).file}.png`}
                    alt=""
                    style={{ objectPosition: (whyImages[index] ?? whyImages[0]).focus }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---- History ---- */}
      <section className="section funnel-hero-tone funnel-divided-section">
        <div className="container">
          <div className="row g-5 align-items-center">
            <div className="col-12 col-lg-5">
              <div className="funnel-photo" style={{ aspectRatio: '4 / 3' }}>
                <img src="/funnel/v2/06-ancient-ruins.png" alt="Историческа архитектура от район, свързан с традиционната употреба на Miswak" />
              </div>
            </div>
            <div className="col-12 col-lg-7 funnel-history__text">
              <h2 className="section-title">
                Повече от <span className="funnel-eyebrow-accent">7000 години</span> история
              </h2>
              {history.paragraphs.map((paragraph) => (
                <p className="section-lead lead" key={paragraph}>
                  {paragraph}
                </p>
              ))}
              <div className="funnel-stat-row mt-4">
                {history.stats.map((stat) => (
                  <div className="funnel-stat-item" key={stat.label}>
                    <StatIcon icon={stat.icon} />
                    <span className="funnel-stat-item__label">{stat.label}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---- Features ---- */}
      <section className="section section-tint funnel-divided-section funnel-features">
        <div className="container">
          <h2 className="section-title mb-5 text-center">
            {features.title.replace('специален?', '')}
            <span className="funnel-eyebrow-accent">специален?</span>
          </h2>
          <div className="funnel-feature-row">
            {features.items.map((item, index) => (
              <div className="funnel-feature-item" key={item.label}>
                <span className="funnel-feature-item__icon">
                  <img src={`/funnel/v2/${featureIcons[index] ?? featureIcons[0]}.png`} alt="" />
                </span>
                <span className="funnel-feature-item__label">{item.label}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---- From tree to you ---- */}
      <section className="section funnel-hero-tone funnel-divided-section funnel-from-tree" id="how">
        <div className="container">
          <div className="row g-5">
            <div className="col-12 col-lg-5">
              <h2 className="section-title">{from_tree.title}</h2>
              {from_tree.paragraphs.map((paragraph) => (
                <p className="section-lead lead" key={paragraph}>
                  {paragraph.startsWith('✓ ') ? (
                    <>
                      <span className="funnel-tick">✓</span>
                      {paragraph.slice(1)}
                    </>
                  ) : (
                    paragraph
                  )}
                </p>
              ))}
              <div className="funnel-step-row mt-4">
                {from_tree.steps.map((step, index) => (
                  <div className="funnel-step-item" key={step.label}>
                    <span className="funnel-step-item__icon">
                      <img src={`/funnel/v2/${fromTreeIcons[index] ?? fromTreeIcons[0]}.png`} alt="" />
                    </span>
                    <span className="funnel-step-item__label">{step.label}</span>
                  </div>
                ))}
              </div>
            </div>
            <div className="col-12 col-lg-7 funnel-from-tree__photo-col">
              <div className="funnel-photo" style={{ height: '92%', width: '100%' }}>
                <img src="/funnel/v2/07-basket.png" alt="Кошница с необработени клонки Miswak" />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---- Awareness (dark band) ---- */}
      <section className="funnel-awareness section funnel-divided-section funnel-divided-section--dark">
        <div className="container">
          <div className="row g-5 align-items-center">
            <div className="col-12 col-lg-4 d-none d-lg-block">
              <div className="funnel-photo funnel-awareness__image">
                <img src="/funnel/v2/08-leaves-dark.png" alt="" />
              </div>
            </div>
            <div className="col-12 col-lg-8">
              <h2 className="section-title">{awareness.title}</h2>
              {awareness.paragraphs.map((paragraph) => (
                <p className="section-lead lead" key={paragraph}>
                  {paragraph}
                </p>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ---- Final CTA / buy ---- */}
      <section className="section funnel-hero-tone funnel-divided-section funnel-final-cta" id="buy">
        <div className="container">
          <div className="row g-5 align-items-center">
            <div className="col-12 col-lg-7">
              <h2 className="section-title">{final_cta.title}</h2>
              {final_cta.paragraphs.map((paragraph) => (
                <p className="section-lead lead" key={paragraph}>
                  {paragraph}
                </p>
              ))}

              {defaultVariant && (
                <div className="mb-3 d-flex justify-content-center">
                  <AddToCartButton
                    key={defaultVariant.id}
                    productVariantId={defaultVariant.id}
                    inventory={defaultVariant.inventory}
                    label={final_cta.cta}
                    large
                    hideQuantity
                    onAdded={() => price && trackFunnelAddToCart(price.amount, price.currency)}
                  />
                </div>
              )}

              <div className="funnel-trust-row mt-4">
                {final_cta.trust_items.map((item) => (
                  <div className="funnel-trust-item" key={item.label}>
                    <TrustIcon icon={item.icon} />
                    <span className="funnel-trust-item__label">{item.label}</span>
                  </div>
                ))}
              </div>
            </div>
            <div className="col-12 col-lg-5 funnel-final-cta__photo-col">
              <div className="funnel-photo funnel-final-cta__image">
                <img src="/funnel/v2/09-hand-single-stick.png" alt={product.name} />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---- FAQ ---- */}
      <section className="section funnel-hero-tone funnel-divided-section funnel-faq" id="faq">
        <div className="container">
          <h2 className="section-title mb-4 text-center">{faq.title}</h2>
          <div className="funnel-faq-row">
            {faq.items.map((item, index) => {
              const isActive = activeFaqIndex === index;

              return (
                <button
                  key={item.question}
                  type="button"
                  className={`funnel-faq-toggle btn btn-outline-secondary ${isActive ? 'is-active' : ''}`}
                  aria-expanded={isActive}
                  onClick={() => setActiveFaqIndex(isActive ? null : index)}
                >
                  {item.question}
                  <span aria-hidden="true">{isActive ? '−' : '+'}</span>
                </button>
              );
            })}
          </div>
          {activeFaqIndex !== null && faq.items[activeFaqIndex] && (
            <div className="funnel-faq-answer">
              {faq.items[activeFaqIndex].answer}
              {faq.items[activeFaqIndex].attachment_url && (
                <a
                  href={faq.items[activeFaqIndex].attachment_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="funnel-faq-answer__attachment"
                >
                  {faq.items[activeFaqIndex].attachment_label || 'Изтегли документ'}
                </a>
              )}
            </div>
          )}
        </div>
      </section>

      {/* ---- Sticky mobile buy bar ---- */}
      <div className="funnel-sticky-bar d-md-none">
        <a href="#buy" className="btn btn-primary w-100">
          {hero.cta_primary}
        </a>
      </div>
    </div>
  );
}
