import { brandTagline } from '../../../content/copy';
import type { FunnelFinalCtaContent } from '../../../types/funnel';

interface BrandStatementSectionProps {
  content: Pick<FunnelFinalCtaContent, 'title' | 'paragraphs'>;
  productName: string;
}

/**
 * Section 14/20 — Brand Statement. Powered by the title/paragraphs of the
 * existing "funnel.final_cta" content block — title unchanged, paragraphs
 * rewritten to 3 short lines (see FunnelSeeder.php's doc comment). All 3
 * show on mobile too (no funnel-mobile-optional hiding) — they're short
 * enough that truncating would drop the closing "Такъв е смисълът зад
 * с|мисъл." line, which is the whole point. Stays deliberately brief —
 * this is a brand statement, not an About Us page. The small tagline
 * below is fixed copy (content/copy.ts), not CMS content — it's the
 * permanent brand promise, not something meant to be edited per-request.
 *
 * className list (funnel-final-cta / funnel-checkout / funnel-checkout--
 * final) is kept exactly as the original combined block used — those
 * classes carry real CSS (background/padding/responsive photo handling)
 * that this pass intentionally does not touch. The "checkout" naming is
 * now misleading for this content, but renaming it is a CSS change, out
 * of scope for a structural-only pass.
 */
export default function BrandStatementSection({ content, productName }: BrandStatementSectionProps) {
  return (
    <section
      className="section funnel-hero-tone funnel-divided-section funnel-final-cta funnel-checkout funnel-checkout--final"
      id="brand-statement"
    >
      <div className="container">
        <div className="row g-5 align-items-center">
          <div className="col-12 col-lg-7">
            <h2 className="section-title">{content.title}</h2>
            {content.paragraphs.map((paragraph) => (
              <p className="section-lead lead" key={paragraph}>
                {paragraph}
              </p>
            ))}
            <p className="funnel-brand-statement__tagline mb-0">{brandTagline}</p>
          </div>
          <div className="col-12 col-lg-5 funnel-final-cta__photo-col">
            <div className="funnel-photo funnel-final-cta__image">
              <img
                src="/funnel/v2/09-hand-single-stick.webp"
                srcSet="/funnel/v2/09-hand-single-stick-800.webp 800w, /funnel/v2/09-hand-single-stick.webp 1324w"
                sizes="(min-width: 992px) 384px, 100vw"
                alt={productName}
                width={1324}
                height={1188}
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
