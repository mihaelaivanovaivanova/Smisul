import type { FunnelFeaturesContent } from '../../../types/funnel';

interface ActualProductSectionProps {
  content: FunnelFeaturesContent;
  ctaPrimaryLabel: string;
  fromPriceLabel: string | null;
}

/**
 * Section 11/20 — Actual Product / What You Receive. Powered by the
 * existing "funnel.features" content block ("what you actually get"
 * framing). Previously rendered right after the usage video/before the
 * comparison table; now sits after Comparison per the new section
 * order, still carrying its existing mid-page CTA to #pricing (renamed
 * from #buy).
 *
 * Each item's icon is now its own field (item.icon) rather than matched
 * to a fixed frontend array by array position — the old approach went
 * silently wrong the moment items were added/removed/reordered in the
 * CMS (as happened when the "Биоразградим"/"Без отпадък" items were
 * removed — see FunnelSeeder.php's doc comment on this block).
 */
export default function ActualProductSection({ content, ctaPrimaryLabel, fromPriceLabel }: ActualProductSectionProps) {
  return (
    <section className="section section-tint funnel-divided-section funnel-features" id="actual-product">
      <div className="container">
        <h2 className="section-title mb-5 text-center">
          {content.title.replace('специален?', '')}
          <span className="funnel-eyebrow-accent">специален?</span>
        </h2>
        <div className="funnel-feature-row">
          {content.items.map((item) => (
            <div className="funnel-feature-item" key={item.label}>
              <span className="funnel-feature-item__icon">
                <img src={`/funnel/v2/${item.icon}.webp`} alt="" loading="lazy" decoding="async" />
              </span>
              <span className="funnel-feature-item__label">{item.label}</span>
            </div>
          ))}
        </div>

        {/* Mid-page CTA — a conscious-yes nudge with the entry price as
            the teaser, same as before the section split. */}
        <div className="text-center mt-5">
          <a href="#pricing" className="btn btn-primary btn-lg">
            {ctaPrimaryLabel}
            {fromPriceLabel && ` — ${fromPriceLabel}`}
          </a>
        </div>
      </div>
    </section>
  );
}
