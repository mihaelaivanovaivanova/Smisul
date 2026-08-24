import Icon from '../../icons/Icon';
import type { FunnelIntroContent } from '../../../types/funnel';

interface WhatIsMiswakSectionProps {
  content: FunnelIntroContent;
}

/**
 * Section 4/20 — What Is Miswak. Powered by the existing "funnel.intro"
 * content block: an explainer paragraph, then a checklist of the natural
 * substances the wood contains (benefits_title/benefits), a proper list
 * with the site's own check icon rather than raw "✓" text characters.
 *
 * Visual: a single static lifestyle photo (07-basket-800.webp, already
 * used by NaturalEcoSection) — replaces the earlier 3-step product-photo
 * sequence, which needed a still-missing "fibers close-up" asset.
 */
export default function WhatIsMiswakSection({ content }: WhatIsMiswakSectionProps) {
  return (
    <section className="section funnel-hero-tone" id="what-is-miswak">
      <div className="container">
        <div className="row g-5 align-items-center">
          <div className="col-12 col-lg-6">
            <h2 className="section-title">{content.title}</h2>
            {content.paragraphs.map((paragraph, index) => (
              <p className={`section-lead lead ${index > 0 ? 'funnel-mobile-optional' : ''}`} key={paragraph}>
                {paragraph}
              </p>
            ))}

            {content.benefits.length > 0 && (
              <div className="funnel-intro-benefits">
                <p className="funnel-intro-benefits__title">{content.benefits_title}</p>
                <ul className="funnel-intro-benefits__list">
                  {content.benefits.map((benefit) => (
                    <li key={benefit.label}>
                      <Icon name="check" className="funnel-intro-benefits__icon" />
                      <span>
                        <strong>{benefit.label}</strong> – {benefit.description}
                      </span>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
          <div className="col-12 col-lg-6">
            <div className="funnel-photo" style={{ aspectRatio: '4 / 3' }}>
              <img
                src="/funnel/v2/07-basket-800.webp"
                alt="Кошница с необработени клонки Miswak"
                loading="lazy"
                decoding="async"
              />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
