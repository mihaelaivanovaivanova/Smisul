import { useState } from 'react';
import type { FunnelIntroContent } from '../../../types/funnel';

interface WhatIsMiswakSectionProps {
  content: FunnelIntroContent;
}

/**
 * Percentage-based geometry (relative to the image wrapper) for the 6
 * ingredient callout boxes baked into 08-benefits-infographic.webp — left
 * column top/middle/bottom, then right column top/middle/bottom. Paired
 * positionally with content.benefits (see render below), not by any
 * stored key — if that image is ever replaced or re-cropped, or the
 * admin reorders/changes the count of benefits, these need re-tuning
 * against the new image alongside it.
 *
 * Measured directly against the source image's own pixel grid (900x1125
 * native), not eyeballed against the rendered page — the two columns
 * turned out not to mirror each other exactly (the right column is
 * narrower and its own boxes aren't flush with the same left inset the
 * left column's are), and the top row is a few px taller than the middle
 * and bottom rows. left/width/top/height are each given explicitly per
 * spot rather than derived from a shared left/right + width, since
 * assuming symmetry was exactly what caused the previous mismatch.
 */
const HOTSPOTS: { top: number; height: number; left: number; width: number; side: 'left' | 'right' }[] = [
  { top: 29, height: 12.3, left: 2.2, width: 33.1, side: 'left' },
  { top: 44.2, height: 12.1, left: 2.2, width: 31.7, side: 'left' },
  { top: 58.7, height: 12.1, left: 2.7, width: 28.9, side: 'left' },
  { top: 30.5, height: 12.3, left: 66.3, width: 31.4, side: 'right' },
  { top: 45.5, height: 11.8, left: 67, width: 30.7, side: 'right' },
  { top: 59, height: 12.3, left: 65.9, width: 31.8, side: 'right' },
];

/**
 * Section 4/20 — What Is Miswak. Powered by the existing "funnel.intro"
 * content block: a common centered header (title + paragraph), then a
 * single infographic (08-benefits-infographic.webp) that already labels
 * each natural substance directly on the stick — no separate checklist
 * repeating the same information as plain text.
 *
 * Desktop only (≥992px, by request — hover has no real equivalent on
 * touch, and a phone-width image has no room beside it for the bubbles
 * below anyway): each labeled box in the image is also a real, focusable
 * hotspot. Hovering/focusing one reveals content.benefits' matching
 * description as a bubble outside the picture, on the same side as the
 * box and level with it — left boxes open a bubble to the picture's
 * left, right boxes to its right. The image itself already conveys the
 * ingredient list, so this is presented as a discoverable bonus, not the
 * only way to read the content (see the image's own alt text).
 */
export default function WhatIsMiswakSection({ content }: WhatIsMiswakSectionProps) {
  const [activeIndex, setActiveIndex] = useState<number | null>(null);
  const hotspotBenefits = content.benefits.slice(0, HOTSPOTS.length);

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
        <div className="row justify-content-center mt-2">
          <div className="col-12 col-lg-7 col-xl-6">
            <div className="funnel-intro__image-wrap">
              <div className="funnel-photo funnel-intro__image" style={{ aspectRatio: '900 / 1125' }}>
                <picture>
                  {/* Mobile/tablet get a variant with the benefit descriptions
                      already baked into the image — the hover hotspots below
                      are desktop-only, so under lg there'd otherwise be no
                      way to read them at all. */}
                  <source media="(max-width: 991.98px)" srcSet="/funnel/v2/08-benefits-infographic-mobile.webp" />
                  <img
                    src="/funnel/v2/08-benefits-infographic.webp"
                    alt="Miswak пръчица с обозначени естествени съставки: влакна, силициев диоксид, калций и калий, флуориди, етерични масла, растителни антиоксиданти"
                    width={900}
                    height={1125}
                    loading="lazy"
                    decoding="async"
                  />
                </picture>
              </div>
              {hotspotBenefits.map((benefit, index) => {
                const spot = HOTSPOTS[index];
                const isActive = activeIndex === index;
                const tooltipId = `funnel-intro-tooltip-${index}`;

                return (
                  <div
                    className="funnel-intro__hotspot-row d-none d-lg-block"
                    style={{ top: `${spot.top}%`, height: `${spot.height}%`, left: `${spot.left}%`, width: `${spot.width}%` }}
                    key={benefit.label}
                  >
                    <button
                      type="button"
                      className={`funnel-intro__hotspot funnel-intro__hotspot--${spot.side}`}
                      onMouseEnter={() => setActiveIndex(index)}
                      onMouseLeave={() => setActiveIndex(null)}
                      onFocus={() => setActiveIndex(index)}
                      onBlur={() => setActiveIndex(null)}
                      aria-expanded={isActive}
                      aria-describedby={tooltipId}
                    >
                      <span className="visually-hidden">{benefit.label}</span>
                    </button>
                    {isActive && (
                      <div
                        className={`funnel-intro__tooltip funnel-intro__tooltip--${spot.side}`}
                        id={tooltipId}
                        role="tooltip"
                      >
                        <strong>{benefit.label}</strong>
                        <span>{benefit.description}</span>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
