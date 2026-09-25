import { useState } from 'react';
import type { MiswakMyth } from '../../content/miswakLanding';

interface MythsSectionProps {
  myths: MiswakMyth[];
}

/**
 * "Митове" accordion — same open/close interaction as the funnel page's
 * FaqSection (single active item, height-animated via a wrapper div), kept
 * as its own small component rather than importing FaqSection because the
 * data shape/heading differ and this section's state is independent of the
 * FAQ section's further down the page.
 */
export default function MythsSection({ myths }: MythsSectionProps) {
  const [activeIndex, setActiveIndex] = useState<number | null>(null);

  if (myths.length === 0) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone" id="myths">
      <div className="container">
        <h2 className="section-title mb-4 text-center">
          <span className="funnel-eyebrow-accent">Митове</span> за Miswak
        </h2>
        <div className="miswak-myths">
          {myths.map((myth, index) => {
            const isActive = activeIndex === index;

            return (
              <div className="funnel-faq-item" key={myth.question}>
                <button
                  type="button"
                  className={`funnel-faq-toggle btn btn-outline-secondary ${isActive ? 'is-active' : ''}`}
                  aria-expanded={isActive}
                  aria-controls={`miswak-myth-answer-${index}`}
                  onClick={() => setActiveIndex(isActive ? null : index)}
                >
                  {myth.question}
                  <span className="funnel-faq-toggle__indicator" aria-hidden="true">
                    +
                  </span>
                </button>
                <div
                  className={`funnel-faq-answer-wrap${isActive ? ' is-open' : ''}`}
                  id={`miswak-myth-answer-${index}`}
                  aria-hidden={!isActive}
                >
                  <div className="funnel-faq-answer">
                    <div className="funnel-faq-answer__inner">{myth.answer}</div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
