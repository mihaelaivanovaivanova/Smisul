import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { useProduct } from '../hooks/useProduct';
import { useSettings } from '../hooks/useSettings';
import { useAsync } from '../hooks/useAsync';
import { fetchProductReviews, fetchReviewSummary } from '../api/reviews';
import { resolvePackageOffers } from '../services/funnelOffers';
import { formatPrice, getPrimaryImage, getVariantPrice } from '../services/productCatalog';
import { trackFunnelViewContent } from '../services/analytics';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import Seo from '../components/Seo';
import PurchasePanel from '../components/miswak-landing/PurchasePanel';
import AudienceClaimsSection from '../components/miswak-landing/AudienceClaimsSection';
import ExpertsSection from '../components/miswak-landing/ExpertsSection';
import UgcVideoSection from '../components/miswak-landing/UgcVideoSection';
import UgcGallerySection from '../components/miswak-landing/UgcGallerySection';
import EvidenceSection from '../components/miswak-landing/EvidenceSection';
import MythsSection from '../components/miswak-landing/MythsSection';
import ComparisonSection from '../components/funnel/sections/ComparisonSection';
import FaqSection from '../components/funnel/sections/FaqSection';
import FunnelTestimonialsSection from '../components/funnel/sections/FunnelTestimonialsSection';
import DeliveryPaymentReturnsSection from '../components/funnel/sections/DeliveryPaymentReturnsSection';
import StickyMobileBuyBar from '../components/funnel/StickyMobileBuyBar';
import StickyDesktopBuyBar from '../components/funnel/StickyDesktopBuyBar';
import ReviewsSection from '../components/reviews/ReviewsSection';
import NotFoundPage from './NotFoundPage';
import { miswakExperts, miswakMyths, miswakUgcPhotos, miswakUgcVideos } from '../content/miswakLanding';
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
 *  2 Delivery/payment/returns trust row                  -> DeliveryPaymentReturnsSection (reused)
 *  3 Curated review carousel                              -> FunnelTestimonialsSection (reused)
 *  4 UGC video carousel                                    -> UgcVideoSection (empty until real clips supplied)
 *  5 "Who it's for" claims (icon tabs)                    -> AudienceClaimsSection
 *  6 Comparison table                                      -> ComparisonSection (reused)
 *  7 Expert endorsements                                   -> ExpertsSection (empty until real quotes supplied)
 *  8 Evidence/citations                                    -> EvidenceSection
 *  9 UGC photo gallery                                     -> UgcGallerySection (empty until real photos supplied)
 * 10 Myths accordion                                       -> MythsSection
 * 11 Full reviews list                                     -> ReviewsSection (reused, real data)
 * 12 FAQ accordion                                         -> FaqSection (reused)
 *
 * Content for sections 1/2/5/6/8/12 is read live from useSettings()'s
 * funnelContent/funnelPackages (the same boot-time fetch "/" already uses)
 * rather than duplicated — see the approved plan.
 */
export default function MiswakLandingPage() {
  const { product, isLoading, error } = useProduct('miswak');
  const { funnelPackages, funnelContent, isLoading: settingsLoading } = useSettings();
  const location = useLocation();
  const [activeFaqIndex, setActiveFaqIndex] = useState<number | null>(null);
  const [showDesktopBar, setShowDesktopBar] = useState(false);
  const [showMobileBar, setShowMobileBar] = useState(false);

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

  const { comparison, science, faq, final_cta } = funnelContent;
  const packageOffers = resolvePackageOffers(product, funnelPackages);
  const fromPrice = packageOffers.length > 0
    ? packageOffers.reduce((min, offer) => (offer.price.amount < min.amount ? offer.price : min), packageOffers[0].price)
    : getVariantPrice(product.variants.find((variant) => variant.is_default) ?? product.variants[0]);
  const fromPriceLabel = fromPrice ? funnelOffer.fromPrice(formatPrice(fromPrice.amount, fromPrice.currency)) : null;
  const barImage = getPrimaryImage(product);

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

      <PurchasePanel product={product} offers={packageOffers} reviewSummary={reviewSummary} />

      <DeliveryPaymentReturnsSection trustItems={final_cta.trust_items} />

      <FunnelTestimonialsSection topReviews={topReviews} />

      <UgcVideoSection videos={miswakUgcVideos} />

      <AudienceClaimsSection content={science} />

      <ComparisonSection content={comparison} ctaPrimaryLabel="Избери пакет" fromPrice={fromPrice} />

      <ExpertsSection experts={miswakExperts} />

      <EvidenceSection safety={science.safety} />

      <UgcGallerySection photos={miswakUgcPhotos} />

      <MythsSection myths={miswakMyths} />

      <section className="section" id="reviews-full">
        <div className="container">
          <ReviewsSection productSlug={product.slug} />
        </div>
      </section>

      <FaqSection content={faq} activeFaqIndex={activeFaqIndex} onToggle={handleFaqToggle} />

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
