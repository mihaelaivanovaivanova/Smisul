import { useEffect, useState } from 'react';
import { useLocation, useSearchParams } from 'react-router-dom';
import { useProduct } from '../hooks/useProduct';
import { useSettings } from '../hooks/useSettings';
import { useAsync } from '../hooks/useAsync';
import { fetchOrderReviewIdentity } from '../api/checkout';
import { fetchProductReviews, fetchReviewSummary } from '../api/reviews';
import { fetchPublicSettings } from '../api/settings';
import { resolvePackageOffers } from '../services/funnelOffers';
import { formatPrice, getPrimaryImage, getVariantPrice, getVideos } from '../services/productCatalog';
import { trackFunnelViewContent } from '../services/analytics';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import Seo from '../components/Seo';
import PurchasePanel from '../components/miswak-landing/PurchasePanel';
import AudienceClaimsSection from '../components/miswak-landing/AudienceClaimsSection';
import ExpertsSection from '../components/miswak-landing/ExpertsSection';
import ExpertCardsSection from '../components/miswak-landing/ExpertCardsSection';
import UgcVideoSection from '../components/miswak-landing/UgcVideoSection';
import UgcGallerySection from '../components/miswak-landing/UgcGallerySection';
import MediaMentionSection from '../components/miswak-landing/MediaMentionSection';
import SocialCommentsSection from '../components/miswak-landing/SocialCommentsSection';
import ComparisonSection from '../components/funnel/sections/ComparisonSection';
import WhatIsMiswakSection from '../components/funnel/sections/WhatIsMiswakSection';
import HowToUseAccordion from '../components/miswak-landing/HowToUseAccordion';
import FaqSection from '../components/funnel/sections/FaqSection';
import FunnelTestimonialsSection from '../components/funnel/sections/FunnelTestimonialsSection';
import DeliveryPaymentReturnsSection from '../components/funnel/sections/DeliveryPaymentReturnsSection';
import StickyMobileBuyBar from '../components/funnel/StickyMobileBuyBar';
import StickyDesktopBuyBar from '../components/funnel/StickyDesktopBuyBar';
import BoxNowBadge from '../components/funnel/BoxNowBadge';
import ReviewsSection from '../components/reviews/ReviewsSection';
import NotFoundPage from './NotFoundPage';
import { miswakExperts, miswakUgcPhotos, miswakUgcVideos } from '../content/miswakLanding';
import { breadcrumbLabels, funnelOffer, reviews as reviewsCopy, seo, states } from '../content/copy';
import { buildBreadcrumbJsonLd } from '../services/structuredData';

/**
 * Redesigned Miswak product page, structured after
 * https://juun.bg/products/creatine-gummies (mobile-first) while keeping
 * Smisul's own palette — see the approved plan for the full section-by-
 * section mapping. Lives at /products/miswak only (see ProductPage.tsx's
 * guard clause); the live "/" funnel page (FunnelLandingPage.tsx) is
 * untouched.
 *
 * Section order:
 *  1 Purchase panel (gallery + package radio selector)   -> PurchasePanel
 *  2 Delivery/payment/returns trust row                  -> DeliveryPaymentReturnsSection (reused; mobile only —
 *                                                            desktop gets the same content inside PurchasePanel's
 *                                                            own right column instead, after the buy button)
 *  3 Curated review carousel                              -> FunnelTestimonialsSection (reused)
 *  4 UGC video carousel                                    -> UgcVideoSection (empty until real clips supplied)
 *  5 "Who it's for" claims (icon tabs)                    -> AudienceClaimsSection
 *  6 "Експертите и Miswak" citation-card slider            -> ExpertCardsSection (4 pre-designed card images,
 *                                                            cropped from user-supplied graphics — placed right
 *                                                            before the comparison table, by request)
 *  7 Comparison table                                      -> ComparisonSection (reused)
 *  8 What Is Miswak (ingredient infographic)               -> WhatIsMiswakSection (reused; placed right after the
 *                                                            comparison table's own buy button, by request)
 *  9 How To Use (peel/soften/clean steps + demo video)     -> HowToUseAccordion (Miswak-only accordion
 *                                                            timeline, replacing the shared HowToUseSection
 *                                                            entirely on this page) — placed right after
 *                                                            WhatIsMiswakSection, by request
 * 10 Expert endorsements                                   -> ExpertsSection (empty until real quotes supplied)
 * 11 UGC photo gallery                                     -> UgcGallerySection (empty until real photos supplied)
 * 12 Real social-media comments                            -> SocialCommentsSection (bento grid of real Facebook/
 *                                                            Instagram comment screenshots — placed right before
 *                                                            the reviews list, by request)
 * 13 Full reviews list                                     -> ReviewsSection (reused, real data)
 * 14 FAQ accordion                                         -> FaqSection (reused)
 *
 * Content for sections 1/2/5/7/8/9/10/13 is read live from useSettings()'s
 * funnelContent/funnelPackages (the same boot-time fetch "/" already uses)
 * rather than duplicated — see the approved plan.
 */
interface ReviewPromptState {
  reviewPrompt?: { orderId: number; productVariantId: number };
}

export default function MiswakLandingPage() {
  const { product, isLoading, error } = useProduct('miswak');
  const { funnelPackages, funnelContent, isLoading: settingsLoading } = useSettings();
  // Same-day dispatch countdown + the BOX NOW badge toggle - admin-configured,
  // not part of useSettings()'s own funnelContent/funnelPackages, so fetched
  // the same way FunnelLandingPage.tsx fetches it for the live "/" page.
  const { data: publicSettings } = useAsync(fetchPublicSettings, [], '');
  const dispatchCutoff = publicSettings?.same_day_dispatch_cutoff ?? null;
  const freeShippingThreshold = publicSettings?.free_shipping_threshold ?? 0;
  const location = useLocation();
  const [searchParams] = useSearchParams();
  const [activeFaqIndex, setActiveFaqIndex] = useState<number | null>(null);
  const [showDesktopBar, setShowDesktopBar] = useState(false);
  const [showMobileBar, setShowMobileBar] = useState(false);

  // Ported from ProductPage.tsx's ProductPageDefault, which this page
  // replaced at /products/miswak (and now "/" too) - without this, the
  // order confirmation page's "write a review" link and the 30-day
  // reminder email's review-confirmation link would silently land here and
  // do nothing, since this is the only real product on the storefront.
  const writePrompt = (location.state as ReviewPromptState | null)?.reviewPrompt;
  const wantsReviewWizard = searchParams.get('write_review') === '1';
  const reviewOrderId = searchParams.get('order_id');
  const reviewExpires = searchParams.get('expires');
  const reviewSignature = searchParams.get('signature');
  const [knownReviewerIdentity, setKnownReviewerIdentity] = useState<
    { email: string; displayName: string; orderId: number; expires: string; signature: string } | undefined
  >(undefined);
  const [reviewerIdentityResolved, setReviewerIdentityResolved] = useState(reviewOrderId === null);

  useEffect(() => {
    if (reviewOrderId === null || reviewExpires === null || reviewSignature === null) {
      return;
    }

    let cancelled = false;
    fetchOrderReviewIdentity(Number(reviewOrderId), { expires: reviewExpires, signature: reviewSignature })
      .then((identity) => {
        if (!cancelled) {
          setKnownReviewerIdentity({
            email: identity.email,
            displayName: identity.display_name,
            orderId: Number(reviewOrderId),
            expires: reviewExpires,
            signature: reviewSignature,
          });
        }
      })
      .catch(() => {
        // Expired or tampered link, or the order no longer exists - fine,
        // just fall back below.
      })
      .finally(() => {
        if (!cancelled) {
          setReviewerIdentityResolved(true);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [reviewOrderId, reviewExpires, reviewSignature]);

  const openReviewWizard = wantsReviewWizard && reviewerIdentityResolved;

  const { data: socialProof } = useAsync(
    () =>
      product
        ? Promise.all([fetchReviewSummary(product.slug), fetchProductReviews(product.slug, 'helpful', 1)])
        : Promise.resolve(null),
    [product?.slug],
    reviewsCopy.loadError,
  );
  const reviewSummary = socialProof && socialProof[0].review_count > 0 ? socialProof[0] : null;
  const topReviews = socialProof?.[1].data ?? [];

  useEffect(() => {
    if (!location.hash || isLoading || settingsLoading) {
      return;
    }
    document.querySelector(location.hash)?.scrollIntoView({ behavior: 'smooth' });
  }, [location.hash, isLoading, settingsLoading]);

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

  // Sticky bars: hidden while #pricing (the purchase panel, which carries
  // its own CTA) is in view, visible through the informational sections,
  // hidden again from #faq onward — same simplified rule for both bars,
  // unlike the live funnel page's separate desktop one-way reveal /
  // mobile three-zone toggle, since this page has no early duplicate
  // pricing instance to account for.
  useEffect(() => {
    if (isLoading || settingsLoading) {
      return;
    }

    const pricing = document.getElementById('pricing');
    const faq = document.getElementById('faq');
    if (!pricing || !faq) {
      return;
    }

    let pricingVisible = true;
    let faqReached = false;
    const update = () => {
      const visible = !pricingVisible && !faqReached;
      setShowDesktopBar(visible);
      setShowMobileBar(visible);
    };

    const pricingObserver = new IntersectionObserver(([entry]) => {
      pricingVisible = entry.isIntersecting;
      update();
    });
    const faqObserver = new IntersectionObserver(([entry]) => {
      faqReached = entry.boundingClientRect.top <= 0;
      update();
    });

    pricingObserver.observe(pricing);
    faqObserver.observe(faq);

    return () => {
      pricingObserver.disconnect();
      faqObserver.disconnect();
    };
  }, [isLoading, settingsLoading]);

  useEffect(() => {
    document.body.classList.add('has-funnel-buy-bar');
    return () => document.body.classList.remove('has-funnel-buy-bar');
  }, []);

  if (isLoading || settingsLoading) {
    return <LoadingState message={states.loadingDefault} />;
  }

  if (error || !product) {
    return <NotFoundPage />;
  }

  if (!funnelContent) {
    return <ErrorState message={states.loadingDefault} />;
  }

  const { comparison, science, faq, final_cta, intro } = funnelContent;
  const packageOffers = resolvePackageOffers(product, funnelPackages);
  const fromPrice = packageOffers.length > 0
    ? packageOffers.reduce((min, offer) => (offer.price.amount < min.amount ? offer.price : min), packageOffers[0].price)
    : getVariantPrice(product.variants.find((variant) => variant.is_default) ?? product.variants[0]);
  const fromPriceLabel = fromPrice ? funnelOffer.fromPrice(formatPrice(fromPrice.amount, fromPrice.currency)) : null;
  const barImage = getPrimaryImage(product);
  const productVideos = getVideos(product);
  // Same resolution FunnelLandingPage.tsx uses for its own HowToUseSection's
  // CTA (HowToUseAccordion's guide button here) — links straight to the
  // FAQ's usage answer's own PDF attachment rather than scrolling to it, so
  // it stays correct whenever an admin edits that FAQ item's attachment via
  // the CMS.
  const usageGuidePdfUrl = faq.items.find((item) => /как се използва/i.test(item.question))?.attachment_url || undefined;

  function handleFaqToggle(index: number) {
    setActiveFaqIndex(activeFaqIndex === index ? null : index);
  }

  const breadcrumbItems = [
    { label: breadcrumbLabels.home, to: '/' },
    { label: product.name },
  ];

  return (
    <div className="funnel-page">
      <Seo
        title={product.seo?.meta_title ?? `${product.name}${seo.productTitleSuffix}`}
        description={product.seo?.meta_description ?? product.short_description ?? seo.productDescriptionFallback}
        ogImage={product.seo?.og_image_url ?? getPrimaryImage(product)?.url ?? null}
        ogType="product"
        jsonLd={[buildBreadcrumbJsonLd(breadcrumbItems)]}
      />

      <PurchasePanel
        product={product}
        offers={packageOffers}
        reviewSummary={reviewSummary}
        trustItems={final_cta.trust_items}
        dispatchCutoff={dispatchCutoff}
        freeShippingThreshold={freeShippingThreshold}
      />

      {/* Desktop gets this same content inside PurchasePanel's own right
          column instead (see its trustItems prop) so the sticky gallery
          stays pinned through it too — this copy is mobile-only there. */}
      <DeliveryPaymentReturnsSection trustItems={final_cta.trust_items} className="d-lg-none" />

      <FunnelTestimonialsSection topReviews={topReviews} />

      <MediaMentionSection />

      <UgcVideoSection videos={miswakUgcVideos} />

      <AudienceClaimsSection content={science} />

      <ExpertCardsSection />

      <ComparisonSection content={comparison} ctaPrimaryLabel="Избери пакет" fromPrice={fromPrice} />

      <WhatIsMiswakSection content={intro} />

      <HowToUseAccordion
        videos={productVideos}
        pdfUrl={usageGuidePdfUrl}
        ctaPrimaryLabel="Избери пакет"
        fromPrice={fromPrice}
      />

      <ExpertsSection experts={miswakExperts} />

      <UgcGallerySection photos={miswakUgcPhotos} />

      <SocialCommentsSection />

      <section className="section" id="reviews-full">
        <div className="container">
          <ReviewsSection
            productSlug={product.slug}
            productName={product.name}
            productImageUrl={barImage?.url}
            writePrompt={writePrompt}
            openWizard={openReviewWizard}
            knownReviewerIdentity={knownReviewerIdentity}
          />
        </div>
      </section>

      <FaqSection content={faq} activeFaqIndex={activeFaqIndex} onToggle={handleFaqToggle} />

      {/* Persistent chrome, same as FunnelLandingPage.tsx - defaults to
          shown while publicSettings is still loading; explicit `false`
          hides it once the setting has actually loaded. */}
      {publicSettings?.box_now_badge_enabled !== false && <BoxNowBadge />}
      <StickyMobileBuyBar visible={showMobileBar} fromPriceLabel={fromPriceLabel} />
      <StickyDesktopBuyBar
        visible={showDesktopBar}
        barImage={barImage}
        productName={product.name}
        fromPriceLabel={fromPriceLabel}
        reviewSummary={reviewSummary}
        ctaLabel="Поръчай"
      />
    </div>
  );
}
