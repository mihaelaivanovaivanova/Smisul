import { WhyIcon } from '../FunnelIcons';
import type { FunnelWhyContent } from '../../../types/funnel';
import type { IconName } from '../../icons/Icon';

interface CoreBenefitsSectionProps {
  content: FunnelWhyContent;
}

// object-position per image so the wider 2:1 crop (see funnel.css) keeps
// each photo's actual subject in frame instead of a blind center-crop.
// Keyed by the card's own icon (a stable CMS-owned field) rather than
// array position — a previous version matched by index, which silently
// mispairs the photo the moment a card's position changes (e.g.
// reordering cards in the admin panel, or here). Same class of fragility
// already fixed once for ActualProductSection's icons; fixed at the root
// here too instead of patching around it.
const WHY_IMAGES: Partial<Record<IconName, { file: string; focus: string }>> = {
  clock: { file: '03-bag-pocket', focus: '50% 55%' }, // miswak stick + bag zipper — fits "always at hand"
  globe: { file: '05-seedling', focus: '50% 25%' }, // plant sprout — fits "naturally simple / plant-based"
  tooth: { file: '04-mouth-bite', focus: '50% 40%' }, // mouth/teeth — fits "no paste, used directly on teeth"
};
const FALLBACK_IMAGE = { file: '03-bag-pocket', focus: '50% 55%' };

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
    <section className="section funnel-hero-tone" id="core-benefits">
      <div className="container">
        <h2 className="section-title mb-4 text-center">
          {content.title.replace('Miswak?', '')}
          <span className="funnel-eyebrow-accent">Miswak?</span>
        </h2>
        <div className="row row-cols-1 row-cols-md-3 g-5 funnel-why-row">
          {content.cards.map((card) => {
            const image = WHY_IMAGES[card.icon] ?? FALLBACK_IMAGE;
            return (
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
                    src={`/funnel/v2/${image.file}.webp`}
                    srcSet={`/funnel/v2/${image.file}-800.webp 800w, /funnel/v2/${image.file}.webp 1536w`}
                    sizes="(min-width: 768px) 33vw, 100vw"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    style={{ objectPosition: image.focus }}
                  />
                </div>
              </div>
            );
          })}
        </div>

        <p className="funnel-why__closing section-lead lead text-center fw-semibold mt-5 mb-0">{content.closing}</p>
      </div>
    </section>
  );
}
