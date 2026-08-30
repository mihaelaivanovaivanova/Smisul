import { useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { formatPrice } from '../../../services/productCatalog';
import { funnelAssurance, funnelOffer } from '../../../content/copy';
import Icon from '../../icons/Icon';
import { TrustIcon } from '../FunnelIcons';
import DispatchPromise from '../DispatchPromise';
import type { FunnelHeroContent } from '../../../types/funnel';
import type { Price } from '../../../types/product';

interface HeroSectionProps {
  content: Pick<FunnelHeroContent, 'eyebrow' | 'title' | 'body' | 'cta_primary' | 'trust_items'>;
  productName: string;
  /** The live cheapest package price — never hardcoded; renders nothing if unavailable. */
  fromPrice: Price | null | undefined;
  /** Same-day dispatch cutoff (Settings → General) — see DispatchPromise.tsx; renders nothing outside the window. */
  dispatchCutoff: string | null | undefined;
}

/**
 * "АМА САМО ПОНЯКОГА?" is the title's punchline clause — by request, it
 * crash-lands into place (its own accent color/weight, a hard drop +
 * squash-settle, and a synced screen-shake — see the
 * .funnel-hero__title-crash* rules in funnel.css) while the rest of the H1
 * renders normally, present from first paint. Plain substring match, not a
 * content-schema change — title is a single CMS string everywhere else on
 * this page, so this only special-cases rendering, not storage. Falls back
 * to the plain string if a future content edit removes the phrase, rather
 * than rendering nothing.
 */
const HERO_TITLE_EMPHASIS = 'АМА САМО ПОНЯКОГА?';

function renderHeroTitle(title: string): ReactNode {
  const index = title.indexOf(HERO_TITLE_EMPHASIS);
  if (index === -1) {
    return title;
  }

  const before = title.slice(0, index);
  const after = title.slice(index + HERO_TITLE_EMPHASIS.length);

  return (
    <>
      {before}
      <span className="funnel-hero__title-crash-wrap">
        <span className="funnel-hero__title-emphasis">{HERO_TITLE_EMPHASIS}</span>
      </span>
      {after}
    </>
  );
}

/**
 * Section 2/20 — Hero. Carries the page's only H1 and the page's one
 * primary CTA (cta_secondary exists in the CMS content but is
 * deliberately not rendered — "no competing CTA above fold").
 *
 * Below the CTA: a compact delivery/returns line (reusing the same
 * .funnel-hero__benefits treatment the product-attribute row used to
 * have), the same dispatch-cutoff promise shown in PricingSection, and
 * the CMS-editable trust_items badge row via TrustIcon/.funnel-trust-row
 * — previously seeded but never rendered anywhere on the page.
 */
export default function HeroSection({ content, productName, fromPrice, dispatchCutoff }: HeroSectionProps) {
  const packagesFromLabel = fromPrice ? funnelOffer.packagesFrom(formatPrice(fromPrice.amount, fromPrice.currency)) : null;

  const introRef = useRef<HTMLDivElement>(null);
  const [imageHeight, setImageHeight] = useState<number | null>(null);

  /**
   * By request, the photo's bottom edge lines up with the bottom of the
   * trust-icon row (the last thing in the left column) rather than the
   * column stretching past it. Desktop-only (min-width: 992px, matching
   * the column breakpoint below); on a stacked mobile layout the photo
   * falls back to its own aspect ratio (see .funnel-photo img in
   * funnel.css), so imageHeight stays null there. Measured via
   * ResizeObserver (not a one-off read) since the intro block's height
   * shifts with viewport width, font loading, and CMS copy length.
   */
  useLayoutEffect(() => {
    const introEl = introRef.current;
    if (!introEl) return;

    const desktopQuery = window.matchMedia('(min-width: 992px)');
    const updateHeight = () => setImageHeight(desktopQuery.matches ? introEl.offsetHeight : null);

    updateHeight();
    const resizeObserver = new ResizeObserver(updateHeight);
    resizeObserver.observe(introEl);
    desktopQuery.addEventListener('change', updateHeight);

    return () => {
      resizeObserver.disconnect();
      desktopQuery.removeEventListener('change', updateHeight);
    };
  }, []);

  return (
    <section className="funnel-hero section">
      <div className="container">
        <div className="row g-5">
          <div className="col-12 col-lg-6">
            <div ref={introRef}>
              {content.eyebrow && <p className="section-eyebrow funnel-hero__eyebrow">{content.eyebrow}</p>}
              <h1 className="funnel-hero__title mb-3">{renderHeroTitle(content.title)}</h1>
              <p className="lead">{content.body}</p>

              <div className="mt-3 mb-2 text-center">
                <a href="#pricing" className="btn btn-primary btn-lg funnel-hero__cta">
                  <span className="funnel-hero__cta-main">{content.cta_primary}</span>
                  {packagesFromLabel && <span className="funnel-hero__cta-sub">{packagesFromLabel}</span>}
                </a>
              </div>

              <ul className="funnel-hero__benefits funnel-hero__benefits--centered mb-2">
                <li>
                  <Icon name="truck" className="funnel-hero__benefit-icon" />
                  {funnelAssurance.delivery}
                </li>
                <li>
                  <Icon name="undo" className="funnel-hero__benefit-icon" />
                  {funnelAssurance.returns}
                </li>
              </ul>

              <div className="mb-3 text-center">
                <DispatchPromise cutoff={dispatchCutoff} />
              </div>

              <div className="funnel-trust-row">
                {content.trust_items.map((item) => (
                  <div className="funnel-trust-item" key={item.label}>
                    <TrustIcon icon={item.icon} />
                    <span className="funnel-trust-item__label">{item.label}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
          <div className="col-12 col-lg-6">
            <div className="funnel-photo funnel-hero__image" style={imageHeight ? { height: `${imageHeight}px` } : undefined}>
              {/* The LCP element: eager + high priority, with intrinsic
                  dimensions so the browser reserves the space (no CLS).
                  Phones pick the 800w variant via srcset. */}
              <img
                src="/funnel/v2/01-hero-sticks.webp"
                srcSet="/funnel/v2/01-hero-sticks-800.webp 800w, /funnel/v2/01-hero-sticks.webp 1536w"
                sizes="(min-width: 992px) 50vw, 100vw"
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
