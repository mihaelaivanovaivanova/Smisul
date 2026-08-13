import type { FunnelNaturalEcoContent } from '../../../types/funnel';

interface NaturalEcoSectionProps {
  content: FunnelNaturalEcoContent;
}

/**
 * Section 13/20 — Natural / Eco. Powered by "funnel.natural_eco".
 * Deliberately avoids absolute environmental claims (see
 * FunnelSeeder.php's doc comment on this block) — the copy stays to
 * concrete, verifiable facts about the stick itself (no plastic handle,
 * no paste needed) plus a brand-philosophy statement that doesn't need
 * the same evidentiary bar since it isn't a factual claim.
 *
 * Two-column photo+text layout, matching WhatIsMiswakSection/
 * HistorySection's established pattern — reuses 07-basket.webp (a real
 * product photo, unused since ScienceSection dropped its own photo
 * column in an earlier pass) rather than a new asset, preserving the
 * existing botanical visual identity.
 */
export default function NaturalEcoSection({ content }: NaturalEcoSectionProps) {
  return (
    <section className="section funnel-hero-tone funnel-divided-section" id="natural-eco">
      <div className="container">
        <div className="row g-5 align-items-center">
          <div className="col-12 col-lg-6">
            <p className="section-eyebrow">{content.eyebrow}</p>
            <h2 className="section-title">{content.title}</h2>
            {content.paragraphs.map((paragraph, index) => (
              <p className={`section-lead lead ${index > 0 ? 'funnel-mobile-optional' : ''}`} key={paragraph}>
                {paragraph}
              </p>
            ))}
          </div>
          <div className="col-12 col-lg-6">
            <div className="funnel-photo" style={{ aspectRatio: '16 / 10' }}>
              <img
                src="/funnel/v2/07-basket.webp"
                srcSet="/funnel/v2/07-basket-800.webp 800w, /funnel/v2/07-basket.webp 1537w"
                sizes="(min-width: 992px) 50vw, 100vw"
                alt="Кошница с необработени клонки Miswak"
                loading="lazy"
                decoding="async"
              />
            </div>
          </div>
        </div>

        <p className="funnel-natural-eco__statement">{content.brand_statement}</p>
      </div>
    </section>
  );
}
