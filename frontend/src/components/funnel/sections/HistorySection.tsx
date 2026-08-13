import type { FunnelHistoryContent } from '../../../types/funnel';

interface HistorySectionProps {
  content: FunnelHistoryContent;
}

/**
 * Section 17/20 — History. Powered by "funnel.history", intentionally
 * short — supplementary context, not a primary argument, so it now sits
 * later in the page (just before FAQ — see FunnelLandingPage.tsx's
 * section map) and fits roughly one mobile screen: no photo, no stat
 * row, just a heading/subheading/one short paragraph.
 *
 * No "learn more about the history" link: the brief asked for one only
 * if a longer historical article already exists, and none does anywhere
 * in this codebase (no /history route, no article/blog system) — adding
 * one would mean inventing that page, which wasn't asked for. Wire a
 * real link in here once that resource exists.
 */
export default function HistorySection({ content }: HistorySectionProps) {
  return (
    <section className="section funnel-hero-tone funnel-divided-section" id="history">
      <div className="container">
        <div className="funnel-history__inner text-center">
          <h2 className="section-title mb-2">{content.title}</h2>
          <p className="funnel-history__subtitle">{content.subtitle}</p>
          <p className="section-lead lead mb-0">{content.body}</p>
        </div>
      </div>
    </section>
  );
}
