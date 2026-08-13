import type { FunnelIntroContent } from '../../../types/funnel';
import type { Media } from '../../../types/product';

interface WhatIsMiswakSectionProps {
  content: FunnelIntroContent;
  /** Real product photography — whole stick, then the prepared tip. Either can be undefined; the tile degrades to a plain frame if so. */
  wholeImage: Media | undefined;
  preparedTipImage: Media | undefined;
}

/**
 * Section 4/20 — What Is Miswak. Powered by the existing "funnel.intro"
 * content block, now a plain product explainer (previously the
 * tradition/history angle, which now lives solely in HistorySection —
 * see the doc comment on funnel.intro in FunnelSeeder.php).
 *
 * Visual is a 3-step sequence (whole stick -> prepared tip -> fibers
 * close-up), using real product photography for the first two steps
 * (product.media, seeded from actual product photos — not the
 * /funnel/v2 lifestyle set). No real "fibers close-up" product photo
 * exists yet, so step 3 is a clearly labeled placeholder rather than a
 * mismatched stand-in photo — flagged in the implementation report as a
 * missing asset, not silently faked.
 */
export default function WhatIsMiswakSection({ content, wholeImage, preparedTipImage }: WhatIsMiswakSectionProps) {
  return (
    <section className="section funnel-hero-tone funnel-divided-section" id="what-is-miswak">
      <div className="container">
        <div className="row g-5 align-items-center">
          <div className="col-12 col-lg-6">
            <h2 className="section-title">{content.title}</h2>
            {content.paragraphs.map((paragraph, index) => (
              <p className={`section-lead lead ${index > 0 ? 'funnel-mobile-optional' : ''}`} key={paragraph}>
                {paragraph}
              </p>
            ))}
          </div>
          <div className="col-12 col-lg-6">
            <div className="funnel-whatis-sequence">
              <div className="funnel-photo funnel-whatis-sequence__item">
                {wholeImage && <img src={wholeImage.url} alt={wholeImage.alt_text ?? content.title} loading="lazy" decoding="async" />}
              </div>
              <span className="funnel-whatis-sequence__arrow" aria-hidden="true">
                →
              </span>
              <div className="funnel-photo funnel-whatis-sequence__item">
                {preparedTipImage && (
                  <img src={preparedTipImage.url} alt={preparedTipImage.alt_text ?? content.title} loading="lazy" decoding="async" />
                )}
              </div>
              <span className="funnel-whatis-sequence__arrow" aria-hidden="true">
                →
              </span>
              {/* Placeholder: no real "fibers close-up" product photo exists
                  yet — see this component's doc comment. Reuses the same
                  placeholder-visual treatment as HomePage.tsx's brand-visual
                  slot rather than inventing a new one. Swap for a real
                  photo instead of leaving this in place. */}
              <div className="funnel-whatis-sequence__item placeholder-visual">
                <span className="placeholder-visual__label">Снимка на влакната отблизо — предстои</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
