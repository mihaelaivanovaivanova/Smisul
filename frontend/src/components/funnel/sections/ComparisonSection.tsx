import Icon from '../../icons/Icon';
import type { FunnelComparisonContent } from '../../../types/funnel';

interface ComparisonSectionProps {
  content: FunnelComparisonContent;
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
 * Section 10/20 — Comparison. Powered by "funnel.comparison", rebuilt as
 * a complementary comparison (Miswak vs. brush-for-specific-moments, not
 * an "us vs. them" checklist) — see FunnelSeeder.php's doc comment. Each
 * cell now carries its own value (✓ / ✕ / △ / free text) instead of a
 * row being uniformly ✓-for-Miswak / ✗-for-brush.
 *
 * Two renderings of the same `rows` data, one visible at a time via CSS
 * (matches the d-none/d-md-* pattern already used elsewhere on this
 * page):
 *  - md+: a real <table> — full semantics, scope="col"/"row".
 *  - below md: stacked cards, one per row, each value labeled with its
 *    own column name rather than relying on horizontal position. Avoids
 *    a cramped 3-column table (and any horizontal scroll) at 320-360px,
 *    per the explicit requirement.
 */
export default function ComparisonSection({ content }: ComparisonSectionProps) {
  const rows = Array.isArray(content?.rows) ? content.rows : [];

  if (rows.length === 0) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone funnel-divided-section funnel-comparison" id="comparison">
      <div className="container">
        <h2 className="section-title mb-4 text-center d-md-none">{content.title}</h2>

        <div className="funnel-comparison__wrap d-none d-md-block">
          <table className="funnel-comparison__table">
            <thead>
              <tr>
                <th scope="col" className="funnel-comparison__title-cell">
                  <h2 className="funnel-comparison__title">{content.title}</h2>
                </th>
                <th scope="col" className="funnel-comparison__miswak-col">
                  <span className="funnel-comparison__col-head">
                    <img src="/funnel/v2/compare-miswak.webp" alt="" width={635} height={320} loading="lazy" decoding="async" />
                    <span>{content.miswak_label}</span>
                  </span>
                </th>
                <th scope="col">
                  <span className="funnel-comparison__col-head">
                    <img src="/funnel/v2/compare-toothbrush.webp" alt="" width={1137} height={320} loading="lazy" decoding="async" />
                    <span>{content.brush_label}</span>
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

        <div className="funnel-comparison__cards d-md-none">
          {rows.map((row) => (
            <div className="funnel-comparison__card" key={row.label}>
              <p className="funnel-comparison__card-label">{row.label}</p>
              <div className="funnel-comparison__card-values">
                <div className="funnel-comparison__card-value">
                  <span className="funnel-comparison__card-value-name">{content.miswak_label}</span>
                  <ComparisonValue value={row.miswak_value} />
                </div>
                <div className="funnel-comparison__card-value">
                  <span className="funnel-comparison__card-value-name">{content.brush_label}</span>
                  <ComparisonValue value={row.brush_value} />
                </div>
              </div>
            </div>
          ))}
        </div>

        <p className="section-lead lead text-center fw-semibold mt-4 mb-0">{content.closing}</p>
      </div>
    </section>
  );
}
