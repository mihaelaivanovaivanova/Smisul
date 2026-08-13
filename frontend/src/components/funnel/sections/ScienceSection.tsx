import type { FunnelScienceContent } from '../../../types/funnel';

interface ScienceSectionProps {
  content: FunnelScienceContent;
}

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
          {content.cards.map((card) => (
            <div className="col" key={card.title}>
              <div className="funnel-usecase-card h-100">
                <h3 className="h6 mb-2">{card.title}</h3>
                <p className="section-lead lead mb-0">{card.body}</p>
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

        <div className="funnel-science-safety">
          <h3 className="h6 mb-1">{content.safety.title}</h3>
          <p className="section-lead lead mb-0">{content.safety.body}</p>
        </div>
      </div>
    </section>
  );
}
