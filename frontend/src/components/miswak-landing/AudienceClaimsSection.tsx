import { useState } from 'react';
import Icon from '../icons/Icon';
import type { IconName } from '../icons/Icon';
import type { FunnelScienceContent } from '../../types/funnel';

interface AudienceClaimsSectionProps {
  content: FunnelScienceContent;
}

// Fixed, presentational icons for the tab switcher — one per science card,
// by position (funnel.science has no per-card icon field). Matches the
// reference's icon-only tab pattern (fitness/energy/mind/... glyphs with
// no visible label, the claim itself appears below).
const TAB_ICONS: IconName[] = ['tooth', 'sparkle', 'leaf'];

// Same source-citation screenshots ScienceSection.tsx uses, in card order —
// reused here as the claim's visual instead of inventing a chart we have no
// data for (see this section's own doc comment).
const CLAIM_IMAGES = ['/funnel/v2/science-source-1.png', '/funnel/v2/science-source-2.png', '/funnel/v2/science-source-3.png'];

/**
 * "Кой има полза" claims switcher — the reference shows one cited claim at
 * a time behind an icon-tab row, each paired with a supporting chart image.
 * We have no charts, so each tab pairs its claim with the same real
 * citation screenshot ScienceSection already shows for that card — real
 * evidence, not a fabricated graphic. Content is funnel.science's existing,
 * already-approved cards (see MiswakLandingPage), just restructured as
 * tabs instead of a 3-card grid.
 */
export default function AudienceClaimsSection({ content }: AudienceClaimsSectionProps) {
  const [activeIndex, setActiveIndex] = useState(0);
  const cards = content?.cards ?? [];

  if (cards.length === 0) {
    return null;
  }

  const active = cards[Math.min(activeIndex, cards.length - 1)];
  const activeImage = CLAIM_IMAGES[activeIndex];

  return (
    <section className="section funnel-hero-tone" id="audience">
      <div className="container">
        <p className="section-eyebrow text-center d-block">{content.eyebrow}</p>
        <h2 className="section-title mb-3 text-center">{content.title}</h2>
        <p className="section-lead lead text-center mx-auto mb-5">{content.intro}</p>

        <div className="miswak-audience-tabs" role="tablist" aria-label="Ползи">
          {cards.map((card, index) => (
            <button
              key={card.title}
              type="button"
              role="tab"
              aria-selected={index === activeIndex}
              aria-label={card.title}
              className={`miswak-audience-tabs__tab ${index === activeIndex ? 'is-active' : ''}`}
              onClick={() => setActiveIndex(index)}
            >
              <Icon name={TAB_ICONS[index % TAB_ICONS.length]} />
            </button>
          ))}
        </div>

        <div className="miswak-audience-claim row g-4 align-items-center">
          {activeImage && (
            <div className="col-12 col-md-6">
              <img src={activeImage} alt="" className="miswak-audience-claim__image" loading="lazy" decoding="async" />
            </div>
          )}
          <div className={activeImage ? 'col-12 col-md-6' : 'col-12'}>
            <h3 className="h4 mb-3">{active.title}</h3>
            <p className="section-lead lead mb-2">{active.body}</p>
            {active.source_url && (
              <a
                href={active.source_url}
                target="_blank"
                rel="noopener noreferrer"
                className="funnel-science-card__source"
                aria-label={`${active.source_label}: ${active.title}`}
              >
                {active.source_label}
              </a>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
