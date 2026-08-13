import { WhyIcon } from '../FunnelIcons';
import type { FunnelWhyContent } from '../../../types/funnel';

interface CoreBenefitsSectionProps {
  content: FunnelWhyContent;
}

// object-position per image so the wider 2:1 crop (see funnel.css) keeps
// each photo's actual subject in frame instead of a blind center-crop.
// Order matches the 3 new cards (always-at-hand / naturally-simple /
// no-paste), reassigned from the old card order — see this file's doc
// comment.
const WHY_IMAGES = [
  { file: '03-bag-pocket', focus: '50% 55%' }, // miswak stick + bag zipper — fits "always at hand"
  { file: '05-seedling', focus: '50% 25%' }, // plant sprout — fits "naturally simple / plant-based"
  { file: '04-mouth-bite', focus: '50% 40%' }, // mouth/teeth — fits "no paste, used directly on teeth"
];

/**
 * Section 5/20 — Core Benefits. Powered by the existing "funnel.why"
 * content block, now exactly 3 cards by design (always at hand /
 * naturally simple / no paste) plus a closing statement.
 *
 * The old card 2 ("допълва ежедневната грижа" — complement, not
 * replacement) and card 3 (yearly-discarded-toothbrushes eco stat) were
 * replaced. Neither is exactly duplicated elsewhere:
 *  - the complement-not-replacement message still exists in the FAQ's
 *    "Замества ли четката и пастата?" answer, but that's collapsed by
 *    default — this is a real prominence reduction for brand-critical
 *    positioning copy, not a like-for-like removal. Flagged in the
 *    implementation report, not silently dropped.
 *  - the eco-impact stat (billions of toothbrushes discarded yearly) has
 *    no close duplicate anywhere else on the page; it's gone.
 * The 3 accompanying photos were reassigned (not re-shot) to better fit
 * the new captions — see WHY_IMAGES above.
 */
export default function CoreBenefitsSection({ content }: CoreBenefitsSectionProps) {
  return (
    <section className="section funnel-hero-tone funnel-divided-section" id="core-benefits">
      <div className="container">
        <h2 className="section-title mb-4 text-center">
          {content.title.replace('Miswak?', '')}
          <span className="funnel-eyebrow-accent">Miswak?</span>
        </h2>
        <div className="row row-cols-1 row-cols-md-3 g-5 funnel-why-row">
          {content.cards.map((card, index) => (
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
                  src={`/funnel/v2/${(WHY_IMAGES[index] ?? WHY_IMAGES[0]).file}.webp`}
                  srcSet={`/funnel/v2/${(WHY_IMAGES[index] ?? WHY_IMAGES[0]).file}-800.webp 800w, /funnel/v2/${(WHY_IMAGES[index] ?? WHY_IMAGES[0]).file}.webp 1536w`}
                  sizes="(min-width: 768px) 33vw, 100vw"
                  alt=""
                  loading="lazy"
                  decoding="async"
                  style={{ objectPosition: (WHY_IMAGES[index] ?? WHY_IMAGES[0]).focus }}
                />
              </div>
            </div>
          ))}
        </div>

        <p className="section-lead lead text-center fw-semibold mt-5 mb-0">{content.closing}</p>
      </div>
    </section>
  );
}
