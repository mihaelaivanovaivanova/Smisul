import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { fetchProduct } from '../api/products';
import { fetchProductReviews, fetchReviewSummary } from '../api/reviews';
import { fetchPublicSettings } from '../api/settings';
import { useAsync } from '../hooks/useAsync';
import { useSettings } from '../hooks/useSettings';
import { trackFunnelAddToCart, trackFunnelViewContent } from '../services/analytics';
import { formatPrice, getGalleryImages, getPrimaryImage, getVariantPrice, getVideos } from '../services/productCatalog';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import ExitIntentModal from '../components/funnel/ExitIntentModal';
import StickyMobileBuyBar from '../components/funnel/StickyMobileBuyBar';
import StickyDesktopBuyBar from '../components/funnel/StickyDesktopBuyBar';
import { resolvePackageOffers } from '../services/funnelOffers';
import Seo from '../components/Seo';
import HeroSection from '../components/funnel/sections/HeroSection';
import UseCasesSection from '../components/funnel/sections/UseCasesSection';
import WhatIsMiswakSection from '../components/funnel/sections/WhatIsMiswakSection';
import CoreBenefitsSection from '../components/funnel/sections/CoreBenefitsSection';
import HowToUseSection from '../components/funnel/sections/HowToUseSection';
import ScienceSection from '../components/funnel/sections/ScienceSection';
import SkepticismHonestySection from '../components/funnel/sections/SkepticismHonestySection';
import PositioningStatementSection from '../components/funnel/sections/PositioningStatementSection';
import ComparisonSection from '../components/funnel/sections/ComparisonSection';
import ActualProductSection from '../components/funnel/sections/ActualProductSection';
import FunnelTestimonialsSection from '../components/funnel/sections/FunnelTestimonialsSection';
import NaturalEcoSection from '../components/funnel/sections/NaturalEcoSection';
import HistorySection from '../components/funnel/sections/HistorySection';
import BrandStatementSection from '../components/funnel/sections/BrandStatementSection';
import PricingSection from '../components/funnel/sections/PricingSection';
import DeliveryPaymentReturnsSection from '../components/funnel/sections/DeliveryPaymentReturnsSection';
import FaqSection from '../components/funnel/sections/FaqSection';
import NewsletterSection from '../components/funnel/sections/NewsletterSection';
import { funnelAssurance, funnelOffer, reviews as reviewsCopy, seo, states } from '../content/copy';

/**
 * The single-product "funnel mode" landing page — section architecture
 * per the 20-slot homepage spec (ai/context/06_Website_Specification.md):
 *
 *  1  Header                    -> Navbar.tsx (rendered by PublicLayout, not here)
 *  2  Hero                      -> HeroSection
 *  3  Use Cases                 -> UseCasesSection (funnelUseCases, fixed copy)
 *  4  What Is Miswak             -> WhatIsMiswakSection (funnel.intro)
 *  5  Core Benefits             -> CoreBenefitsSection (funnel.why)
 *  6  How To Use                -> HowToUseSection (funnelHowToUse, fixed copy + optional product video)
 *  7  Science                   -> ScienceSection (funnel.science)
 *  8  Skepticism / Honesty      -> SkepticismHonestySection (funnel.awareness)
 *  9  Positioning Statement     -> PositioningStatementSection (funnel.positioning)
 *  10 Comparison                -> ComparisonSection (funnel.comparison)
 *  11 Actual Product            -> ActualProductSection (funnel.features)
 *  12 Reviews                   -> FunnelTestimonialsSection (reviews API)
 *  13 Natural / Eco             -> NaturalEcoSection (funnel.natural_eco)
 *  14 Brand Statement           -> BrandStatementSection (funnel.final_cta title/paragraphs)
 *  15 Pricing                   -> PricingSection (funnel.checkout copy + package offers)
 *  16 Delivery/Payment/Returns  -> DeliveryPaymentReturnsSection (funnel.final_cta.trust_items + payment copy)
 *  17 History                   -> HistorySection (funnel.history)
 *  18 FAQ                       -> FaqSection (funnel.faq)
 *  19 Newsletter                -> NewsletterSection (lead capture)
 *  20 Footer                    -> Footer.tsx (rendered by PublicLayout, not here)
 *
 * History moved from its original spec position (right after Natural/
 * Eco) to just before FAQ, and was shortened to roughly one mobile
 * screen — supplementary context rather than a primary argument, so it
 * no longer needs to compete for space early in the page. Every other
 * section has since had its real copy filled in too (see each section
 * component's own doc comment for what changed and why) — this map no
 * longer reflects a single structural-only pass, just the current
 * render order. Sticky buy bars and the exit-intent modal are
 * persistent chrome, not one of the 20 scroll-order sections, so they
 * render after the ordered list.
 *
 * Content/copy comes from FunnelContentService (see backend
 * database/seeders/FunnelSeeder.php). Photography lives at
 * /funnel/v2/*.webp (cropped from a reference composite and converted
 * from the original PNGs — see git history for provenance; several are
 * still placeholder-quality and expected to be swapped for real
 * photography later, per an explicit choice made when building this
 * page).
 */

export default function FunnelLandingPage() {
  const { funnelProductSlug, funnelPackages, funnelContent, isLoading: settingsLoading } = useSettings();
  const location = useLocation();
  const navigate = useNavigate();
  const [activeFaqIndex, setActiveFaqIndex] = useState<number | null>(null);
  const [showDesktopBar, setShowDesktopBar] = useState(false);
  const [showMobileBar, setShowMobileBar] = useState(false);

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

  // Navbar's section-anchor nav links here as "/#core-benefits" etc. — the
  // browser only auto-scrolls to a fragment on a real page load, not an
  // SPA route change, so this replicates that behavior once the page has
  // rendered. "#usage-guide" is a pseudo-anchor with no element of its
  // own — HowToUseSection's "Виж пълните инструкции" CTA uses it to jump
  // straight to the FAQ's existing, more detailed usage answer (which
  // carries the downloadable PDF manual) rather than duplicating that
  // content in a new instructions page/modal.
  useEffect(() => {
    if (!location.hash || settingsLoading || isLoading) {
      return;
    }

    if (location.hash === '#usage-guide') {
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
  // see the page fully static. Selectors are class-based, so this is
  // unaffected by which section component renders each element.
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
          '.funnel-usecase-card',
          '.funnel-why-card',
          '.funnel-video__wrap',
          '.funnel-comparison__wrap',
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

  // Desktop sticky buy bar: a one-way reveal, not a toggle — once the hero
  // scrolls out of view (before that its own CTA is already on screen) the
  // bar appears and stays up for the rest of the page, including over the
  // pricing/testimonials/FAQ sections and through the footer. It used to
  // also hide again whenever the pricing section scrolled into view, which
  // read as the bar randomly disappearing while scrolling. The body class
  // below reserves a bar-height strip under the footer so the fixed bar
  // never covers footer content at full scroll.
  useEffect(() => {
    if (settingsLoading || isLoading) {
      return;
    }

    const hero = document.querySelector('.funnel-hero');

    if (!hero) {
      return;
    }

    const observer = new IntersectionObserver(([entry]) => {
      setShowDesktopBar(!entry.isIntersecting);
    });

    observer.observe(hero);

    return () => observer.disconnect();
  }, [settingsLoading, isLoading]);

  // Mobile sticky CTA: a three-zone toggle, unlike the desktop bar's
  // one-way reveal. Hidden over the hero (its own primary CTA is already
  // on screen), visible through the informational sections, hidden again
  // once the pricing cards themselves are in view (they carry their own
  // CTAs), visible again for the short delivery/history stretch right
  // after pricing, then hidden for good from FAQ onward so it never sits
  // over the FAQ's accordion controls, the newsletter form, or footer
  // links/the cookie banner below that. faqReached is directional (based
  // on the FAQ section's boundingClientRect.top, not just isIntersecting)
  // so scrolling back up above FAQ un-hides the bar again.
  useEffect(() => {
    if (settingsLoading || isLoading) {
      return;
    }

    const hero = document.querySelector('.funnel-hero');
    const pricing = document.getElementById('pricing');
    const faq = document.getElementById('faq');

    if (!hero || !pricing || !faq) {
      return;
    }

    let heroVisible = true;
    let pricingVisible = false;
    let faqReached = false;

    const update = () => setShowMobileBar(!heroVisible && !pricingVisible && !faqReached);

    const heroObserver = new IntersectionObserver(([entry]) => {
      heroVisible = entry.isIntersecting;
      update();
    });
    const pricingObserver = new IntersectionObserver(([entry]) => {
      pricingVisible = entry.isIntersecting;
      update();
    });
    const faqObserver = new IntersectionObserver(([entry]) => {
      faqReached = entry.boundingClientRect.top <= 0;
      update();
    });

    heroObserver.observe(hero);
    pricingObserver.observe(pricing);
    faqObserver.observe(faq);

    return () => {
      heroObserver.disconnect();
      pricingObserver.disconnect();
      faqObserver.disconnect();
    };
  }, [settingsLoading, isLoading]);

  // Reserve the buy bar's height below the footer while the funnel page is
  // mounted (md+ only, see body.has-funnel-buy-bar in funnel.css).
  useEffect(() => {
    document.body.classList.add('has-funnel-buy-bar');
    return () => document.body.classList.remove('has-funnel-buy-bar');
  }, []);

  if (settingsLoading || isLoading) {
    return <LoadingState message={states.loadingDefault} />;
  }

  if (error || !funnelContent || !product) {
    return <ErrorState message={error ?? states.loadingDefault} />;
  }

  const { hero, intro, why, features, comparison, history, natural_eco, science, awareness, positioning, final_cta, faq } =
    funnelContent;
  const defaultVariant = product.variants.find((variant) => variant.is_default) ?? product.variants[0] ?? null;
  const price = defaultVariant ? getVariantPrice(defaultVariant) : null;
  const packageOffers = resolvePackageOffers(product, funnelPackages);
  const productVideos = getVideos(product);
  // HowToUseSection's CTA — links straight to the FAQ's usage answer's own
  // PDF attachment rather than scrolling to it, so it stays correct
  // whenever an admin edits that FAQ item's attachment via the CMS.
  const usageGuidePdfUrl = faq.items.find((item) => /как се използва/i.test(item.question))?.attachment_url || undefined;
  // The sticky bar's "от X €" teaser and the mid-page CTA's price nudge —
  // the cheapest package, or the default variant's price when no packages
  // are configured. Always the live computed minimum, never hardcoded.
  const fromPrice = packageOffers.length > 0
    ? packageOffers.reduce((min, offer) => (offer.price.amount < min.amount ? offer.price : min), packageOffers[0].price)
    : price;
  const fromPriceLabel = fromPrice ? funnelOffer.fromPrice(formatPrice(fromPrice.amount, fromPrice.currency)) : null;
  // Both sticky bars' product thumbnail — same social-proof-adjacent
  // treatment as juun.bg's sticky add-to-cart bar (thumbnail + rating next
  // to the CTA), adapted to our own color/spacing tokens.
  const barImage = getPrimaryImage(product);
  // WhatIsMiswakSection's 3-step visual — real product photography, sorted
  // primary-first. Seeded order is [whole stick in hand, prepared-tip
  // close-up, bundle] (see FunnelSeeder.php), so index 0/1 are exactly the
  // two steps that have a real photo today.
  const galleryImages = getGalleryImages(product);

  function handleFallbackAddToCart() {
    if (price) {
      trackFunnelAddToCart(price.amount, price.currency);
    }
    navigate('/cart');
  }

  function handleFaqToggle(index: number) {
    setActiveFaqIndex(activeFaqIndex === index ? null : index);
  }

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

  return (
    // Bottom clearance for the fixed buy bars comes from
    // body.has-funnel-buy-bar (see funnel.css), not page padding.
    <div className="funnel-page">
      <Seo
        title={seo.funnelTitle}
        description={seo.funnelDescription}
        ogImage="/funnel/v2/og-image.jpg"
        jsonLd={faqJsonLd ? [productJsonLd, faqJsonLd] : productJsonLd}
      />

      {/* 1 Header -> Navbar.tsx, rendered by PublicLayout */}
      {/* 2 Hero — carries the page's only H1 */}
      <HeroSection
        content={hero}
        productName={product.name}
        fromPrice={fromPrice}
        deliveryPromise={funnelAssurance.delivery}
      />
      {/* 3 Use Cases */}
      <UseCasesSection />
      {/* 4 What Is Miswak */}
      <WhatIsMiswakSection content={intro} wholeImage={galleryImages[0]} preparedTipImage={galleryImages[1]} />
      {/* 5 Core Benefits */}
      <CoreBenefitsSection content={why} />
      {/* 6 How To Use */}
      <HowToUseSection videos={productVideos} posterImage={barImage} pdfUrl={usageGuidePdfUrl} />
      {/* 7 Science */}
      <ScienceSection content={science} />
      {/* 8 Skepticism / Honesty */}
      <SkepticismHonestySection content={awareness} />
      {/* 9 Positioning Statement */}
      <PositioningStatementSection content={positioning} />
      {/* 10 Comparison */}
      <ComparisonSection content={comparison} />
      {/* 11 Actual Product / What You Receive */}
      <ActualProductSection content={features} ctaPrimaryLabel={hero.cta_primary} fromPriceLabel={fromPriceLabel} />
      {/* 12 Reviews */}
      <FunnelTestimonialsSection topReviews={topReviews} reviewSummary={reviewSummary} productSlug={product.slug} />
      {/* 13 Natural / Eco */}
      <NaturalEcoSection content={natural_eco} />
      {/* 14 Brand Statement */}
      <BrandStatementSection content={final_cta} productName={product.name} />
      {/* 15 Pricing */}
      <PricingSection
        packageOffers={packageOffers}
        defaultVariant={defaultVariant}
        fallbackCtaLabel={final_cta.cta}
        dispatchCutoff={dispatchCutoff}
        onAdded={handleFallbackAddToCart}
      />
      {/* 16 Delivery / Payment / Returns */}
      <DeliveryPaymentReturnsSection trustItems={final_cta.trust_items} />
      {/* 17 History — moved here from right after Natural/Eco, shortened
          to roughly one mobile screen: supplementary context, not a
          primary argument, so it no longer needs early placement. */}
      <HistorySection content={history} />
      {/* 18 FAQ */}
      <FaqSection content={faq} activeFaqIndex={activeFaqIndex} onToggle={handleFaqToggle} />
      {/* 19 Newsletter */}
      <NewsletterSection />
      {/* 20 Footer -> Footer.tsx, rendered by PublicLayout */}

      {/* Persistent chrome — not part of the 20-section scroll order */}
      <StickyMobileBuyBar visible={showMobileBar} fromPriceLabel={fromPriceLabel} />
      <ExitIntentModal />
      <StickyDesktopBuyBar
        visible={showDesktopBar}
        barImage={barImage}
        productName={product.name}
        fromPriceLabel={fromPriceLabel}
        reviewSummary={reviewSummary}
        ctaLabel={hero.cta_primary}
      />
    </div>
  );
}
