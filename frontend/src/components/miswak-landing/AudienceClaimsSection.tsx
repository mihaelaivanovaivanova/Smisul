import { useEffect, useState } from 'react';
import { useCarouselScroll } from '../../hooks/useCarouselScroll';
import { miswakAudienceClaims } from '../../content/miswakLanding';
import type { MiswakAudienceClaim } from '../../content/miswakLanding';
import type { FunnelScienceContent } from '../../types/funnel';

interface AudienceClaimsSectionProps {
  /** Only eyebrow/title/intro are read from this — the tabs/claims themselves come from miswakAudienceClaims, not content.cards (see that constant's own docblock for why this section no longer shares its claim data with the live funnel page's ScienceSection). */
  content: FunnelScienceContent;
}

/** Title/body/source — shared between the mobile slide and the desktop static panel below, so the two stay identical without duplicating the markup itself. */
function ClaimText({ claim }: { claim: MiswakAudienceClaim }) {
  if (!claim.title && !claim.body) {
    // Dead in practice today (every current claim has both) but stays as a
    // safety net for a future claim added without them, rather than
    // rendering an empty heading and paragraph.
    return <p className="section-lead lead mb-0 text-muted">Съдържанието за този раздел предстои.</p>;
  }

  return (
    <>
      <h3 className="miswak-audience-claim__title mb-3">{claim.title}</h3>
      <p className="section-lead lead mb-2">{claim.body}</p>
      {claim.sources.length > 0 && (
        <div className="d-flex flex-wrap gap-3">
          {claim.sources.map((source) => (
            <a
              key={source.url}
              href={source.url}
              target="_blank"
              rel="noopener noreferrer"
              className="funnel-science-card__source"
              aria-label={`${source.label}: ${claim.title}`}
            >
              {source.label}
            </a>
          ))}
        </div>
      )}
    </>
  );
}

/**
 * "Кой има полза" claims switcher — a pill-shaped icon tab bar (the active
 * tab a solid dark capsule, the rest plain outline icons sharing one
 * bordered pill container) rather than the separate-circle tabs this
 * section used before. Six tabs now instead of three, backed by
 * miswakAudienceClaims (see its own docblock for where each one's
 * title/body/image actually comes from).
 *
 * Mobile gets an actual swipeable slider (by request) — .miswak-audience-claim-track
 * below, using the same useCarouselScroll scroll-snap mechanics
 * GalleryCarousel.tsx already uses for the product photos, rather than a
 * simple swipe-gesture threshold, so it has the same native drag/momentum
 * feel. Desktop is untouched: a static two-column panel (image left, text
 * right) switched only by tapping a tab, no swiping — the two are
 * separate DOM blocks (d-md-none / d-none d-md-flex) rather than one
 * layout doing double duty, since desktop's side-by-side image+text
 * doesn't make sense as horizontally-swiped slide content the way mobile's
 * stacked image-then-text does.
 *
 * `activeIndex` is the single shared source of truth for the tab
 * highlight and the desktop panel; the mobile track's own scroll position
 * (via useCarouselScroll's activeIndex) feeds back into it so swiping
 * updates the active tab too, and tapping a tab both sets it directly
 * (instant on desktop, where the track is hidden and inert) and smooth-
 * scrolls the mobile track to match.
 */
export default function AudienceClaimsSection({ content }: AudienceClaimsSectionProps) {
  const [activeIndex, setActiveIndex] = useState(0);
  const { trackRef, activeIndex: swipeIndex, scrollToCard } = useCarouselScroll('.miswak-audience-claim-slide', []);

  useEffect(() => {
    setActiveIndex(swipeIndex);
  }, [swipeIndex]);

  if (miswakAudienceClaims.length === 0) {
    return null;
  }

  const active = miswakAudienceClaims[Math.min(activeIndex, miswakAudienceClaims.length - 1)];

  function handleTabClick(index: number) {
    setActiveIndex(index);
    scrollToCard(index);
  }

  return (
    <section className="section funnel-hero-tone" id="audience">
      <div className="container">
        <p className="section-eyebrow text-center d-block">{content.eyebrow}</p>
        <h2 className="section-title mb-3 text-center">{content.title}</h2>
        <p className="section-lead lead text-center mx-auto mb-5">{content.intro}</p>

        <div className="miswak-audience-tabs" role="tablist" aria-label="Ползи">
          {miswakAudienceClaims.map((claim, index) => (
            <button
              key={claim.icon}
              type="button"
              role="tab"
              aria-selected={index === activeIndex}
              aria-label={claim.title || `Полза ${index + 1}`}
              className={`miswak-audience-tabs__tab ${index === activeIndex ? 'is-active' : ''}`}
              onClick={() => handleTabClick(index)}
            >
              <img src={claim.icon} alt="" aria-hidden="true" />
            </button>
          ))}
        </div>

        <div className="miswak-audience-claim-track d-md-none" ref={trackRef}>
          {miswakAudienceClaims.map((claim) => (
            <div className="miswak-audience-claim-slide" key={claim.icon}>
              {claim.image && (
                <img
                  src={claim.image}
                  alt=""
                  className="miswak-audience-claim__image mb-4"
                  loading="lazy"
                  decoding="async"
                />
              )}
              <ClaimText claim={claim} />
            </div>
          ))}
        </div>

        <div className="miswak-audience-claim row g-4 align-items-start d-none d-md-flex">
          {active.image && (
            <div className="col-12 col-md-6">
              <img src={active.image} alt="" className="miswak-audience-claim__image" loading="lazy" decoding="async" />
            </div>
          )}
          <div className={active.image ? 'col-12 col-md-6 miswak-audience-claim__text' : 'col-12'}>
            <ClaimText claim={active} />
          </div>
        </div>
      </div>
    </section>
  );
}
