import type { FunnelPositioningContent } from '../../../types/funnel';

interface PositioningStatementSectionProps {
  content: FunnelPositioningContent;
}

/**
 * Section 9/20 — Positioning Statement (Miswak as a complement to normal
 * oral hygiene, not a replacement — see the brand charter's product
 * positioning order). Powered by "funnel.positioning", immediately after
 * Skepticism/Honesty. Previously this message only existed inside the
 * old Core Benefits card 2 (removed when Core Benefits was rebuilt) and
 * the FAQ's "Замества ли четката и пастата?" answer — this is its first
 * standalone, always-visible home.
 *
 * Visually strong but not oversized: bold heading-family text at
 * --fs-lg, same weight/scale as SkepticismHonestySection's subtitle
 * immediately above it, not a full hero-scale statement.
 */
export default function PositioningStatementSection({ content }: PositioningStatementSectionProps) {
  return (
    <section className="section funnel-hero-tone funnel-divided-section" id="positioning-statement">
      <div className="container">
        <div className="funnel-positioning-statement text-center">
          <h2 className="funnel-positioning-statement__title">{content.title}</h2>
          <p className="section-lead lead mb-0">{content.body}</p>
        </div>
      </div>
    </section>
  );
}
