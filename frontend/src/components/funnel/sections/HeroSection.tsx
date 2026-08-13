import { formatPrice } from '../../../services/productCatalog';
import { funnelHeroBenefits, funnelOffer } from '../../../content/copy';
import type { FunnelHeroContent } from '../../../types/funnel';
import type { Price } from '../../../types/product';

interface HeroSectionProps {
  content: Pick<FunnelHeroContent, 'eyebrow' | 'title' | 'body' | 'cta_primary'>;
  productName: string;
  /** The live cheapest package price — never hardcoded; renders nothing if unavailable. */
  fromPrice: Price | null | undefined;
  /**
   * The configured delivery promise (funnelAssurance.delivery) — passed in
   * rather than imported directly so the fine print only ever shows the
   * one already-vetted shipping claim used elsewhere on the page (see
   * FunnelLandingPage.tsx), never a second, possibly-drifted copy of it.
   * Falsy/empty hides the delivery half of the line entirely.
   */
  deliveryPromise: string;
}

/**
 * Section 2/20 — Hero. Carries the page's only H1 and the page's one
 * primary CTA (no secondary button — see requirement: "no competing CTA
 * above fold"). Deliberately compact: no star-rating line, no dispatch
 * countdown, no returns claim up here — those still live further down
 * (FunnelTestimonialsSection, PricingSection, DeliveryPaymentReturnsSection)
 * so the fold stays short and single-purpose.
 */
export default function HeroSection({ content, productName, fromPrice, deliveryPromise }: HeroSectionProps) {
  const packagesFromLabel = fromPrice ? funnelOffer.packagesFrom(formatPrice(fromPrice.amount, fromPrice.currency)) : null;

  return (
    <section className="funnel-hero section">
      <div className="container">
        <div className="row align-items-start g-5">
          <div className="col-12 col-lg-5">
            {content.eyebrow && <p className="section-eyebrow funnel-hero__eyebrow">{content.eyebrow}</p>}
            <h1 className="funnel-hero__title mb-3">{content.title}</h1>
            <p className="lead">{content.body}</p>

            <div className="mt-3 mb-2">
              <a href="#pricing" className="btn btn-primary btn-lg">
                {content.cta_primary}
              </a>
            </div>

            {(packagesFromLabel || deliveryPromise) && (
              <p className="funnel-assurance funnel-assurance--hero">
                {packagesFromLabel}
                {packagesFromLabel && deliveryPromise ? ' • ' : null}
                {deliveryPromise}
              </p>
            )}

            <ul className="funnel-hero__benefits">
              {funnelHeroBenefits.map((item) => (
                <li key={item.label}>
                  <span aria-hidden="true">{item.emoji}</span> {item.label}
                </li>
              ))}
            </ul>
          </div>
          <div className="col-12 col-lg-7">
            <div className="funnel-photo funnel-hero__image">
              {/* The LCP element: eager + high priority, with intrinsic
                  dimensions so the browser reserves the space (no CLS).
                  Phones pick the 800w variant via srcset. */}
              <img
                src="/funnel/v2/01-hero-sticks.webp"
                srcSet="/funnel/v2/01-hero-sticks-800.webp 800w, /funnel/v2/01-hero-sticks.webp 1536w"
                sizes="(min-width: 992px) 58vw, 100vw"
                alt={productName}
                width={1536}
                height={1024}
                fetchPriority="high"
              />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
