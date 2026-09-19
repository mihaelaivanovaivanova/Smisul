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
 * Visual: a single infographic (08-benefits-infographic.webp) labeling
 * the same substances the checklist covers directly on the stick itself
 * — kept at its own native portrait ratio (4:5) rather than cropped into
 * a landscape box, since cropping would cut off the callout labels.
 *
 * Title/paragraph sit in their own full-width row above a second row
 * (checklist left, image right) rather than both living in the left
 * column next to the image — by request, so the header reads as common
 * to the whole section instead of belonging to just the text side. On
 * mobile this still stacks in the original title → text → list → image
 * order, since Bootstrap's columns collapse to one below lg regardless
 * of which row they're in.
 */
export default function WhatIsMiswakSection({ content }: WhatIsMiswakSectionProps) {
  return (
    <section className="section funnel-hero-tone" id="what-is-miswak">
      <div className="container">
        <div className="row">
          <div className="col-12 text-center">
            <h2 className="section-title">{content.title}</h2>
            {content.paragraphs.map((paragraph, index) => (
              <p
                className={`section-lead lead funnel-intro__lead ${index > 0 ? 'funnel-mobile-optional' : ''}`}
                key={paragraph}
              >
                {paragraph}
              </p>
            ))}
          </div>
        </div>
        <div className="row g-5 align-items-center mt-2">
          <div className="col-12 col-lg-6">
            {content.benefits.length > 0 && (
              <div className="funnel-intro-benefits">
                {content.benefits_title && <p className="funnel-intro-benefits__title">{content.benefits_title}</p>}
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
            <div className="funnel-photo funnel-intro__image" style={{ aspectRatio: '900 / 1125' }}>
              <img
                src="/funnel/v2/08-benefits-infographic.webp"
                alt="Miswak пръчица с обозначени естествени съставки: влакна, силициев диоксид, калций и калий, флуориди, етерични масла, растителни антиоксиданти"
                width={900}
                height={1125}
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
