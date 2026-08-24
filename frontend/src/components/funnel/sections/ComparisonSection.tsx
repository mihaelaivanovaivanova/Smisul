import Icon from '../../icons/Icon';
import { formatPrice } from '../../../services/productCatalog';
import { funnelOffer } from '../../../content/copy';
import type { FunnelComparisonContent } from '../../../types/funnel';
import type { Price } from '../../../types/product';

interface ComparisonSectionProps {
  content: FunnelComparisonContent;
  ctaPrimaryLabel: string;
  /** The live cheapest package price - same source as the Hero CTA's own price line, never hardcoded. */
  fromPrice: Price | null | undefined;
}

/**
 * Renders a row's per-column value: "✓"/"✕"/"△" become marks with a
 * matching aria-label (so meaning survives for screen readers even
 * though the visible glyph is the same symbol used elsewhere on the
 * page), anything else (e.g. "Допълва", "обикновено ✕") renders as
 * plain text — already self-explanatory, no aria-label needed.
 */
function ComparisonValue({ value }: { value: string }) {
  if (value === '✓') {
    return (
      <span className="funnel-comparison__mark funnel-comparison__mark--yes" role="img" aria-label="Да">
        <Icon name="check" />
      </span>
    );
  }
  if (value === '✕') {
    return (
      <span className="funnel-comparison__mark funnel-comparison__mark--no" role="img" aria-label="Не">
        <Icon name="cross" />
      </span>
    );
  }
  if (value === '△') {
    return (
      <span className="funnel-comparison__mark funnel-comparison__mark--partial" role="img" aria-label="Отчасти">
        △
      </span>
    );
  }
  return <span className="funnel-comparison__mark funnel-comparison__mark--text">{value}</span>;
}

/**
 * IMPLEMENTATION NOTE (asset gap, not fixed here): compare-toothbrush.webp
 * is a saturated blue stock photo that conflicts with the site's warm
 * photographic language — flagged in an audit. Checked both fallback
 * options before touching anything: no warm-toned realistic toothbrush+
 * toothpaste photo exists anywhere in public/funnel/v2, and no matching
 * neutral icon exists in Icon.tsx's SVG set (its 'tooth' glyph is a
 * molar, not a toothbrush — wrong subject). Per instruction, not
 * fabricating a replacement stock asset either. Toned the existing photo
 * warmer via CSS (.funnel-comparison__toothbrush-img in funnel.css) as
 * an interim fix. If a real fix is wanted: a warm/neutral-toned
 * toothbrush + toothpaste product photo (or a custom line-icon in the
 * same style as the other funnel/v2 icon-*.webp assets) shot/cropped to
 * roughly the same ~3.5:1 letterbox as compare-miswak.webp.
 *
 * Section 10/20 — Comparison. Powered by "funnel.comparison", rebuilt as
 * a complementary comparison (Miswak vs. brush-for-specific-moments, not
 * an "us vs. them" checklist) — see FunnelSeeder.php's doc comment. Each
 * cell now carries its own value (✓ / ✕ / △ / free text) instead of a
 * row being uniformly ✓-for-Miswak / ✗-for-brush.
 *
 * One <table> at every breakpoint — same photos, title-cell and
 * highlight pill on mobile as on desktop. table-layout: fixed's
 * percentage column widths (see funnel.css) already scale the whole
 * thing down with the viewport, so nothing extra is needed to make it
 * responsive.
 */
export default function ComparisonSection({ content, ctaPrimaryLabel, fromPrice }: ComparisonSectionProps) {
  const rows = Array.isArray(content?.rows) ? content.rows : [];
  const packagesFromLabel = fromPrice ? funnelOffer.packagesFrom(formatPrice(fromPrice.amount, fromPrice.currency)) : null;

  if (rows.length === 0) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone funnel-comparison" id="comparison">
      <div className="container">
        <div className="funnel-comparison__wrap">
          {/* Decorative — the actual highlight is the .funnel-comparison__miswak-col
              cells below (real content, real semantics). This is purely the
              rounded pill that pops out above/below the table, matching the
              reference "featured column" treatment. Positioned via
              table-layout: fixed's guaranteed column widths (see funnel.css),
              so it needs table-layout: fixed to stay aligned with the real
              column — auto layout wouldn't guarantee the match. */}
          <div className="funnel-comparison__miswak-highlight" aria-hidden="true" />
          <table className="funnel-comparison__table">
            <thead>
              <tr>
                <th scope="col" className="funnel-comparison__title-cell">
                  <h2 className="funnel-comparison__title">{content.title}</h2>
                </th>
                <th scope="col" className="funnel-comparison__miswak-col">
                  <span className="funnel-comparison__col-head">
                    <img
                      src="/funnel/v2/compare-miswak.webp"
                      alt={content.miswak_label}
                      width={635}
                      height={320}
                      loading="lazy"
                      decoding="async"
                    />
                  </span>
                </th>
                <th scope="col">
                  <span className="funnel-comparison__col-head">
                    <img
                      src="/funnel/v2/compare-toothbrush.webp"
                      alt={content.brush_label}
                      width={1137}
                      height={320}
                      loading="lazy"
                      decoding="async"
                      className="funnel-comparison__toothbrush-img"
                    />
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.label}>
                  <th scope="row">{row.label}</th>
                  <td className="funnel-comparison__miswak-col">
                    <ComparisonValue value={row.miswak_value} />
                  </td>
                  <td>
                    <ComparisonValue value={row.brush_value} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="text-center mt-5">
          <a href="#pricing" className="btn btn-primary btn-lg funnel-hero__cta">
            <span className="funnel-hero__cta-main">{ctaPrimaryLabel}</span>
            {packagesFromLabel && <span className="funnel-hero__cta-sub">{packagesFromLabel}</span>}
          </a>
        </div>
      </div>
    </section>
  );
}
