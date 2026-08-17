import type { FunnelScienceContent } from '../../../types/funnel';

interface ScienceSectionProps {
  content: FunnelScienceContent;
}

// Torn-clipping screenshots of each card's own source study (headline/
// excerpt), in card order — supplied directly rather than fetched, so
// they're a fixed, positional 1:1:1 match to content.cards, not CMS data.
// PNG, not WebP: the background removal (flood-filled fully transparent,
// see the source photos in public/funnel/v2) leaves a hard 0/255 alpha
// edge, and lossy WebP re-encoding was producing visible color-bleed
// ringing right at that edge — PNG's lossless encoding doesn't.
const SCIENCE_CARD_IMAGES = [
  '/funnel/v2/science-source-1.png',
  '/funnel/v2/science-source-2.png',
  '/funnel/v2/science-source-3.png',
];

/**
 * Section 7/20 — Science. Powered by "funnel.science" (renamed from the
 * old "funnel.from_tree" composition/sourcing content, which didn't
 * actually cover clinical evidence — see FunnelSeeder.php's doc comment
 * on this block for the two verified PubMed references behind it).
 *
 * Every claim is the supplied wording verbatim — nothing paraphrased or
 * strengthened. No badges, seals, invented credentials, or "clinically
 * proven" graphics: just the claim, its own hedged language, and a real
 * link to the source. Each card's source link carries its own aria-label
 * (card title + link label) since all 3 cards otherwise share identical
 * visible link text.
 */
export default function ScienceSection({ content }: ScienceSectionProps) {
  return (
    <section className="section funnel-hero-tone funnel-divided-section" id="science">
      <div className="container">
        <div className="funnel-science-intro text-center">
          <p className="section-eyebrow">{content.eyebrow}</p>
          <h2 className="section-title mb-3">{content.title}</h2>
          <p className="section-lead lead">{content.intro}</p>
        </div>

        <div className="row row-cols-1 row-cols-md-3 g-3 g-md-4">
          {content.cards.map((card, index) => (
            <div className="col" key={card.title}>
              <div className="funnel-usecase-card funnel-usecase-card--hover h-100">
                <h3 className="funnel-science-card__title mb-2">{card.title}</h3>
                <p className="section-lead lead mb-0">{card.body}</p>
                {SCIENCE_CARD_IMAGES[index] && (
                  <img
                    src={SCIENCE_CARD_IMAGES[index]}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    className="funnel-science-card__image"
                  />
                )}
                {card.source_url && (
                  <a
                    href={card.source_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="funnel-science-card__source"
                    aria-label={`${card.source_label}: ${card.title}`}
                  >
                    {card.source_label}
                  </a>
                )}
              </div>
            </div>
          ))}
        </div>

        <div className="funnel-science-callout">
          <p className="funnel-science-callout__stat">{content.callout.stat}</p>
          <p className="section-lead lead mb-0">{content.callout.body}</p>
        </div>
      </div>
    </section>
  );
}
