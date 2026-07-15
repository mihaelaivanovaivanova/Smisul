import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { fetchProduct } from '../api/products';
import { fetchProductReviews, fetchReviewSummary } from '../api/reviews';
import { fetchPublicSettings } from '../api/settings';
import { useAsync } from '../hooks/useAsync';
import { useSettings } from '../hooks/useSettings';
import { trackFunnelAddToCart, trackFunnelViewContent } from '../services/analytics';
import { formatPrice, getVariantPrice, getVideos } from '../services/productCatalog';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import AddToCartButton from '../components/product/AddToCartButton';
import DispatchPromise from '../components/funnel/DispatchPromise';
import ExitIntentModal from '../components/funnel/ExitIntentModal';
import LeadCaptureForm from '../components/funnel/LeadCaptureForm';
import PackageOffers from '../components/funnel/PackageOffers';
import { resolvePackageOffers } from '../services/funnelOffers';
import Icon from '../components/icons/Icon';
import type { IconName } from '../components/icons/Icon';
import Seo from '../components/Seo';
import StarRating from '../components/reviews/StarRating';
import {
  funnelAssurance,
  funnelLead,
  funnelOffer,
  funnelReviews,
  funnelVideo,
  reviews as reviewsCopy,
  seo,
  states,
} from '../content/copy';

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
    // No loading="lazy": rendered in the hero's trust row, above the fold.
    return (
      <span className="funnel-trust-item__icon funnel-trust-item__icon--photo">
        <img src={`/funnel/v2/${image}.webp`} alt="" />
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
        <img src={`/funnel/v2/${image}.webp`} alt="" loading="lazy" decoding="async" />
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
        <img src={`/funnel/v2/${image}.webp`} alt="" loading="lazy" decoding="async" />
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
 * the sections below match that seeder's FUNNEL_SECTIONS list one to
 * one. Photography lives at /funnel/v2/*.webp (cropped from a reference
 * composite and converted from the original PNGs — see git history for
 * provenance; several are still placeholder-quality and expected to be
 * swapped for real photography later, per an explicit choice made when
 * building this page).
 */

export default function FunnelLandingPage() {
  const { funnelProductSlug, funnelPackages, funnelContent, isLoading: settingsLoading } = useSettings();
  const location = useLocation();
  const navigate = useNavigate();
  const [activeFaqIndex, setActiveFaqIndex] = useState<number | null>(null);
  const [showDesktopBar, setShowDesktopBar] = useState(false);

  const { data: product, isLoading, error } = useAsync(
    () => (funnelProductSlug ? fetchProduct(funnelProductSlug) : Promise.resolve(null)),
    [funnelProductSlug],
    'Продуктът не можа да се зареди.',
  );

  // Social proof (hero rating line + testimonial cards) — deliberately not
  // part of the page's loading/error gates: a failed or empty reviews fetch
  // just hides those blocks rather than degrading the whole funnel.
  const { data: socialProof } = useAsync(
    () =>
      funnelProductSlug
        ? Promise.all([fetchReviewSummary(funnelProductSlug), fetchProductReviews(funnelProductSlug, 'helpful', 1)])
        : Promise.resolve(null),
    [funnelProductSlug],
    reviewsCopy.loadError,
  );
  const reviewSummary = socialProof && socialProof[0].review_count > 0 ? socialProof[0] : null;
  const topReviews = socialProof?.[1].data.slice(0, 3) ?? [];

  // Merchant settings (same-day dispatch cutoff) — non-gating like the
  // social proof: a failed fetch just hides the dispatch promise line.
  const { data: publicSettings } = useAsync(fetchPublicSettings, [], '');
  const dispatchCutoff = publicSettings?.same_day_dispatch_cutoff ?? null;

  // Navbar's section-anchor nav links here as "/#benefits" etc. — the
  // browser only auto-scrolls to a fragment on a real page load, not an
  // SPA route change, so this replicates that behavior once the page has
  // rendered. "#how-to-use" is a pseudo-anchor with no element of its own:
  // usage instructions live in the FAQ (together with the downloadable PDF
  // manual), so it opens that exact question and scrolls to the FAQ.
  useEffect(() => {
    if (!location.hash || settingsLoading || isLoading) {
      return;
    }

    if (location.hash === '#how-to-use') {
      const items = funnelContent?.faq.items ?? [];
      const usageIndex = items.findIndex((item) => /как се използва/i.test(item.question));
      setActiveFaqIndex(usageIndex === -1 ? 0 : usageIndex);
      document.getElementById('faq')?.scrollIntoView({ behavior: 'smooth' });
      return;
    }

    document.querySelector(location.hash)?.scrollIntoView({ behavior: 'smooth' });
  }, [location.hash, settingsLoading, isLoading, funnelContent]);

  // Scroll-reveal: photos, cards, and icon items below the hero rise into
  // view as the visitor reaches them, staggered within their section —
  // every scroll gets rewarded with a little motion. The hero is exempt
  // (nothing above the fold may start hidden), package cards too (they
  // carry their own hover/featured transforms), and reduced-motion users
  // see the page fully static.
  useEffect(() => {
    if (settingsLoading || isLoading || !product) {
      return;
    }
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }

    const targets = Array.from(
      document.querySelectorAll(
        [
          '.funnel-page section:not(.funnel-hero) .funnel-photo',
          '.funnel-why-card',
          '.funnel-video__wrap',
          '.funnel-comparison__wrap',
          '.funnel-stat-item',
          '.funnel-feature-item',
          '.funnel-step-item',
          '.funnel-review-card',
        ].join(', '),
      ),
    );

    // Stagger siblings within the same section: 0ms, 80ms, 160ms…
    const sectionCounters = new Map<Element, number>();
    for (const element of targets) {
      const section = element.closest('section') ?? document.body;
      const index = sectionCounters.get(section) ?? 0;
      sectionCounters.set(section, index + 1);
      (element as HTMLElement).style.setProperty('--reveal-delay', `${Math.min(index * 80, 400)}ms`);
      element.classList.add('funnel-reveal');
    }

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-revealed');
            observer.unobserve(entry.target);
          }
        }
      },
      // Slightly inset trigger line so elements reveal just after entering
      // the viewport, not the instant a single pixel shows.
      { rootMargin: '0px 0px -8% 0px' },
    );

    for (const element of targets) {
      observer.observe(element);
    }

    return () => observer.disconnect();
  }, [settingsLoading, isLoading, product]);

  // ViewContent: the funnel page rendered with its product — the top of
  // the ad-campaign event chain (ViewContent → AddToCart → InitiateCheckout
  // → Purchase). Keyed on the product id so it fires once per view, not on
  // every re-render.
  useEffect(() => {
    if (!product) {
      return;
    }

    const trackedVariant = product.variants.find((variant) => variant.is_default) ?? product.variants[0];
    const trackedPrice = trackedVariant ? getVariantPrice(trackedVariant) : undefined;

    if (trackedPrice) {
      trackFunnelViewContent(product.name, trackedPrice.amount, trackedPrice.currency);
    }
  }, [product]);

  // Desktop sticky buy bar: visible only between the hero scrolling out of
  // view (before that the hero's own CTA is on screen) and the #buy section
  // scrolling into view (where the bar would just duplicate the offer stack
  // right next to it).
  useEffect(() => {
    if (settingsLoading || isLoading) {
      return;
    }

    const hero = document.querySelector('.funnel-hero');
    const buy = document.getElementById('buy');

    if (!hero || !buy) {
      return;
    }

    let heroInView = true;
    let buyInView = false;

    const observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.target === hero) {
          heroInView = entry.isIntersecting;
        } else if (entry.target === buy) {
          buyInView = entry.isIntersecting;
        }
      }
      setShowDesktopBar(!heroInView && !buyInView);
    });

    observer.observe(hero);
    observer.observe(buy);

    return () => observer.disconnect();
  }, [settingsLoading, isLoading]);

  if (settingsLoading || isLoading) {
    return <LoadingState message={states.loadingDefault} />;
  }

  if (error || !funnelContent || !product) {
    return <ErrorState message={error ?? states.loadingDefault} />;
  }

  const { hero, intro, why, features, comparison, history, from_tree, awareness, final_cta, faq } = funnelContent;
  // A DB that predates the comparison section serves `[]` for it (see
  // FunnelContentService::all) — render only when real rows exist.
  const comparisonRows = Array.isArray(comparison?.rows) ? comparison.rows : [];
  // Two-column FAQ accordion — reading order fills the left column first.
  const faqColumnSize = Math.ceil(faq.items.length / 2);
  const defaultVariant = product.variants.find((variant) => variant.is_default) ?? product.variants[0] ?? null;
  const price = defaultVariant ? getVariantPrice(defaultVariant) : null;
  const packageOffers = resolvePackageOffers(product, funnelPackages);
  const productVideos = getVideos(product);
  // The sticky bar's "от 8.99 €" teaser — the cheapest package, or the
  // default variant's price when no packages are configured.
  const fromPrice = packageOffers.length > 0
    ? packageOffers.reduce((min, offer) => (offer.price.amount < min.amount ? offer.price : min), packageOffers[0].price)
    : price;

  // Product rich-result schema: price range across the packages, live
  // aggregate rating, and the top testimonials — eligible for star-rating
  // rich results in Google. FAQPage schema mirrors the on-page FAQ.
  const allPrices = product.variants
    .map((variant) => getVariantPrice(variant))
    .filter((variantPrice): variantPrice is NonNullable<typeof variantPrice> => variantPrice !== undefined);
  const productJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.short_description ?? undefined,
    image: [`${window.location.origin}/funnel/v2/og-image.jpg`],
    inLanguage: 'bg',
    ...(allPrices.length > 0 && {
      offers: {
        '@type': 'AggregateOffer',
        priceCurrency: allPrices[0].currency,
        lowPrice: Math.min(...allPrices.map((p) => p.amount)).toFixed(2),
        highPrice: Math.max(...allPrices.map((p) => p.amount)).toFixed(2),
        offerCount: allPrices.length,
        availability: product.variants.some((variant) => variant.inventory?.is_in_stock)
          ? 'https://schema.org/InStock'
          : 'https://schema.org/OutOfStock',
        url: `${window.location.origin}/`,
      },
    }),
    ...(reviewSummary && {
      aggregateRating: {
        '@type': 'AggregateRating',
        ratingValue: reviewSummary.average_rating,
        reviewCount: reviewSummary.review_count,
      },
      review: topReviews.map((review) => ({
        '@type': 'Review',
        name: review.title,
        reviewBody: review.body,
        datePublished: review.created_at.slice(0, 10),
        reviewRating: { '@type': 'Rating', ratingValue: review.rating },
        author: { '@type': 'Person', name: review.author_name },
      })),
    }),
  };
  const faqJsonLd =
    faq.items.length > 0
      ? {
          '@context': 'https://schema.org',
          '@type': 'FAQPage',
          mainEntity: faq.items.map((item) => ({
            '@type': 'Question',
            name: item.question,
            acceptedAnswer: { '@type': 'Answer', text: item.answer },
          })),
        }
      : null;

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
      <Seo
        title={seo.funnelTitle}
        description={seo.funnelDescription}
        ogImage="/funnel/v2/og-image.jpg"
        jsonLd={faqJsonLd ? [productJsonLd, faqJsonLd] : productJsonLd}
      />

      {/* ---- Hero ---- */}
      <section className="funnel-hero section">
        <div className="container">
          <div className="row align-items-start g-5">
            <div className="col-12 col-lg-5">
              {hero.eyebrow && <p className="section-eyebrow funnel-hero__eyebrow">{hero.eyebrow}</p>}
              <h1 className="funnel-hero__title mb-3">{hero.title}</h1>
              <p className="lead">{hero.body}</p>
              {reviewSummary && (
                <a href="#reviews" className="funnel-hero__rating">
                  <StarRating
                    rating={reviewSummary.average_rating}
                    ariaLabel={funnelReviews.average(reviewSummary.average_rating.toFixed(1))}
                  />
                  <strong>{funnelReviews.average(reviewSummary.average_rating.toFixed(1))}</strong>
                  <span className="funnel-hero__rating-count">
                    · {reviewsCopy.reviewCount(reviewSummary.review_count)}
                  </span>
                </a>
              )}
              <div className="d-flex flex-wrap align-items-center gap-2 mt-3 mb-4">
                <a href="#buy" className="btn btn-primary btn-lg">
                  {hero.cta_primary}
                </a>
                <a href="#why" className="btn btn-outline-secondary">
                  {hero.cta_secondary}
                </a>
              </div>
              <p className="funnel-assurance funnel-assurance--hero">
                <span className="funnel-assurance__item">
                  <Icon name="truck" /> {funnelAssurance.delivery}
                </span>
                <span className="funnel-assurance__item">
                  <Icon name="undo" /> {funnelAssurance.returns}
                </span>
                <DispatchPromise cutoff={dispatchCutoff} className="funnel-assurance__item" />
              </p>
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
                {/* The LCP element: eager + high priority, with intrinsic
                    dimensions so the browser reserves the space (no CLS). */}
                <img src="/funnel/v2/01-hero-sticks.webp" alt={product.name} width={1536} height={1024} fetchPriority="high" />
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
              {/* 16:10 rather than 4:3 — shallower sections keep the next
                  one peeking into the viewport (scroll momentum). */}
              <div className="funnel-photo" style={{ aspectRatio: '16 / 10' }}>
                <img src="/funnel/v2/02-desert-tree.webp" alt="Дървото Salvadora Persica в естествената си среда" loading="lazy" decoding="async" />
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
                    src={`/funnel/v2/${(whyImages[index] ?? whyImages[0]).file}.webp`}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    style={{ objectPosition: (whyImages[index] ?? whyImages[0]).focus }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---- Usage video ---- */}
      {/* Renders the funnel product's own video media (admin-managed via
          Products → media) — nothing renders until a video is uploaded. */}
      {productVideos.length > 0 && (
        <section className="section funnel-hero-tone funnel-divided-section funnel-video" id="video">
          <div className="container">
            <h2 className="section-title mb-4 text-center">{funnelVideo.title}</h2>
            <div className="funnel-video__wrap">
              {/* eslint-disable-next-line jsx-a11y/media-has-caption -- placeholder demo clip; real footage will carry captions */}
              <video controls preload="metadata" src={productVideos[0].url} aria-label={productVideos[0].alt_text ?? undefined} />
            </div>
          </div>
        </section>
      )}

      {/* ---- Features ---- */}
      {/* Deliberately ahead of the history/tradition sections: the concrete
          product argument (and the first mid-page CTA) reaches the visitor
          before the longer educational read, not after it. */}
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
                  <img src={`/funnel/v2/${featureIcons[index] ?? featureIcons[0]}.webp`} alt="" loading="lazy" decoding="async" />
                </span>
                <span className="funnel-feature-item__label">{item.label}</span>
              </div>
            ))}
          </div>

          {/* Mid-page CTA — the peak-interest moment right after the "what
              makes it special" argument, with the entry price as the teaser
              so the click is a conscious yes rather than a leap. */}
          <div className="text-center mt-5">
            <a href="#buy" className="btn btn-primary btn-lg">
              {hero.cta_primary}
              {fromPrice && ` — ${funnelOffer.fromPrice(formatPrice(fromPrice.amount, fromPrice.currency))}`}
            </a>
          </div>
        </div>
      </section>

      {/* ---- Comparison ("us vs. them" checklist) ---- */}
      {comparisonRows.length > 0 && (
        <section className="section funnel-hero-tone funnel-divided-section funnel-comparison" id="compare">
          <div className="container">
            <div className="funnel-comparison__wrap">
              <table className="funnel-comparison__table">
                <thead>
                  <tr>
                    {/* The section heading lives inside the table's own
                        top-left cell, matching the reference layout. */}
                    <th scope="col" className="funnel-comparison__title-cell">
                      <h2 className="funnel-comparison__title">{comparison.title}</h2>
                    </th>
                    <th scope="col" className="funnel-comparison__miswak-col">
                      <span className="funnel-comparison__col-head">
                        <img src="/funnel/v2/compare-miswak.webp" alt="" width={635} height={320} loading="lazy" decoding="async" />
                        <span>{comparison.miswak_label}</span>
                      </span>
                    </th>
                    <th scope="col">
                      <span className="funnel-comparison__col-head">
                        <img src="/funnel/v2/compare-toothbrush.webp" alt="" width={1137} height={320} loading="lazy" decoding="async" />
                        <span>{comparison.brush_label}</span>
                      </span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {comparisonRows.map((row) => (
                    <tr key={row.label}>
                      <th scope="row">{row.label}</th>
                      <td className="funnel-comparison__miswak-col">
                        <span className="funnel-comparison__mark funnel-comparison__mark--yes" role="img" aria-label="Да">
                          <Icon name="check" />
                        </span>
                      </td>
                      <td>
                        <span className="funnel-comparison__mark funnel-comparison__mark--no" role="img" aria-label="Не">
                          <Icon name="cross" />
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </section>
      )}

      {/* ---- History ---- */}
      <section className="section funnel-hero-tone funnel-divided-section">
        <div className="container">
          <div className="row g-5 align-items-center">
            <div className="col-12 col-lg-5">
              <div className="funnel-photo" style={{ aspectRatio: '16 / 10' }}>
                <img src="/funnel/v2/06-ancient-ruins.webp" alt="Историческа архитектура от район, свързан с традиционната употреба на Miswak" loading="lazy" decoding="async" />
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
                      <img src={`/funnel/v2/${fromTreeIcons[index] ?? fromTreeIcons[0]}.webp`} alt="" loading="lazy" decoding="async" />
                    </span>
                    <span className="funnel-step-item__label">{step.label}</span>
                  </div>
                ))}
              </div>
            </div>
            <div className="col-12 col-lg-7 funnel-from-tree__photo-col">
              <div className="funnel-photo" style={{ height: '92%', width: '100%' }}>
                <img src="/funnel/v2/07-basket.webp" alt="Кошница с необработени клонки Miswak" loading="lazy" decoding="async" />
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
                <img src="/funnel/v2/08-leaves-dark.webp" alt="" loading="lazy" decoding="async" />
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

      {/* ---- Testimonials (top approved reviews, straight from the reviews API) ---- */}
      {topReviews.length > 0 && (
        <section className="section funnel-hero-tone funnel-divided-section funnel-reviews" id="reviews">
          <div className="container">
            <h2 className="section-title mb-4 text-center">{funnelReviews.title}</h2>
            <div className="row row-cols-1 row-cols-lg-3 g-4">
              {topReviews.map((review) => (
                <div className="col" key={review.id}>
                  <figure className="funnel-review-card">
                    <StarRating rating={review.rating} />
                    <h3 className="h6 mb-0">{review.title}</h3>
                    <blockquote className="funnel-review-card__body mb-0">{review.body}</blockquote>
                    <figcaption className="funnel-review-card__author">
                      {review.author_name}
                      {review.verified_purchase && (
                        <span className="funnel-review-card__verified">
                          <Icon name="check-badge" /> {reviewsCopy.verifiedPurchase}
                        </span>
                      )}
                    </figcaption>
                  </figure>
                </div>
              ))}
            </div>
            {reviewSummary && reviewSummary.review_count > topReviews.length && (
              <div className="text-center mt-4">
                <Link to={`/products/${product.slug}`} className="funnel-reviews__see-all">
                  {funnelReviews.seeAll(reviewSummary.review_count)}
                </Link>
              </div>
            )}
          </div>
        </section>
      )}

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
            </div>
            <div className="col-12 col-lg-5 funnel-final-cta__photo-col">
              <div className="funnel-photo funnel-final-cta__image">
                <img src="/funnel/v2/09-hand-single-stick.webp" alt={product.name} width={1324} height={1188} loading="lazy" decoding="async" />
              </div>
            </div>
          </div>

          <div className="text-center">
            <DispatchPromise cutoff={dispatchCutoff} className="funnel-dispatch--buy" />
          </div>

          {packageOffers.length > 0 ? (
            <PackageOffers offers={packageOffers} />
          ) : (
            defaultVariant && (
              <div className="mb-3 d-flex justify-content-center">
                <AddToCartButton
                  key={defaultVariant.id}
                  productVariantId={defaultVariant.id}
                  inventory={defaultVariant.inventory}
                  label={final_cta.cta}
                  large
                  hideQuantity
                  // Same funnel-only momentum as PackageOffers: a "yes"
                  // goes straight to the cart page and its checkout CTA.
                  onAdded={() => {
                    if (price) {
                      trackFunnelAddToCart(price.amount, price.currency);
                    }
                    navigate('/cart');
                  }}
                />
              </div>
            )
          )}

          <div className="funnel-trust-row funnel-final-cta__trust mt-4">
            {final_cta.trust_items.map((item) => (
              <div className="funnel-trust-item" key={item.label}>
                <TrustIcon icon={item.icon} />
                <span className="funnel-trust-item__label">{item.label}</span>
              </div>
            ))}
          </div>

          {/* Official card brand marks (via iCard) — small, grayscale-calm. */}
          <div className="funnel-payment-logos" role="img" aria-label={funnelAssurance.paymentLogosAria}>
            <img src="/payments/visa.svg" alt="Visa" height={18} loading="lazy" />
            <img src="/payments/mastercard.svg" alt="Mastercard" height={30} loading="lazy" />
          </div>
        </div>
      </section>

      {/* ---- FAQ ---- */}
      {/* A true accordion: each answer expands directly under its own
          question. Two independent columns on desktop (reading order fills
          the left column first, like the old `columns: 3` layout did), so
          opening an answer only pushes down the questions in its column. */}
      <section className="section funnel-hero-tone funnel-divided-section funnel-faq" id="faq">
        <div className="container">
          <h2 className="section-title mb-4 text-center">{faq.title}</h2>
          <div className="funnel-faq-columns">
            {[faq.items.slice(0, faqColumnSize), faq.items.slice(faqColumnSize)].map((column, columnIndex) => (
              <div className="funnel-faq-column" key={column[0]?.question ?? columnIndex}>
                {column.map((item, itemIndex) => {
                  const index = columnIndex * faqColumnSize + itemIndex;
                  const isActive = activeFaqIndex === index;

                  return (
                    <div className="funnel-faq-item" key={item.question}>
                      <button
                        type="button"
                        className={`funnel-faq-toggle btn btn-outline-secondary ${isActive ? 'is-active' : ''}`}
                        aria-expanded={isActive}
                        aria-controls={`funnel-faq-answer-${index}`}
                        onClick={() => setActiveFaqIndex(isActive ? null : index)}
                      >
                        {item.question}
                        <span aria-hidden="true">{isActive ? '−' : '+'}</span>
                      </button>
                      {isActive && (
                        <div className="funnel-faq-answer" id={`funnel-faq-answer-${index}`}>
                          {item.answer}
                          {item.attachment_url && (
                            <a
                              href={item.attachment_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="funnel-faq-answer__attachment"
                            >
                              {item.attachment_label || 'Изтегли документ'}
                            </a>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---- Lead capture (visitors not buying today still become leads) ---- */}
      <section className="section section-tint funnel-divided-section funnel-lead">
        <div className="container">
          <div className="funnel-lead__inner text-center">
            <h2 className="section-title">{funnelLead.title}</h2>
            <p className="section-lead lead">{funnelLead.body}</p>
            <LeadCaptureForm />
          </div>
        </div>
      </section>

      {/* ---- Sticky mobile buy bar ---- */}
      <div className="funnel-sticky-bar d-md-none">
        <a href="#buy" className="btn btn-primary w-100">
          {hero.cta_primary}
        </a>
      </div>

      {/* ---- Exit-intent lead capture (desktop only, once per session) ---- */}
      <ExitIntentModal />

      {/* ---- Sticky desktop buy bar (see the IntersectionObserver above) ---- */}
      {showDesktopBar && (
        <div className="funnel-desktop-bar d-none d-md-block">
          <div className="container d-flex align-items-center justify-content-between gap-3">
            <div className="d-flex align-items-baseline gap-3">
              <strong className="funnel-desktop-bar__name">{product.name}</strong>
              {fromPrice && (
                <span className="funnel-desktop-bar__price">
                  {funnelOffer.fromPrice(formatPrice(fromPrice.amount, fromPrice.currency))}
                </span>
              )}
            </div>
            <a href="#buy" className="btn btn-primary">
              {hero.cta_primary}
            </a>
          </div>
        </div>
      )}
    </div>
  );
}
