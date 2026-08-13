import type { FunnelAwarenessContent } from '../../../types/funnel';

interface SkepticismHonestySectionProps {
  content: FunnelAwarenessContent;
}

/**
 * Section 8/20 — Skepticism / Honesty. Powered by "funnel.awareness"
 * (now an honest founder-voice admission — "a stick for teeth? sounded
 * strange to us too" — rather than the old "why doesn't everyone know
 * about this" framing). Kept the dark-band treatment: strong contrast
 * reads as confident/visually strong without needing larger type — see
 * FunnelSeeder.php's doc comment on this block for why the body copy
 * uses the brief's safer fallback wording (no verified "we tried it"
 * claim exists in the project context).
 */
export default function SkepticismHonestySection({ content }: SkepticismHonestySectionProps) {
  return (
    <section className="funnel-awareness section funnel-divided-section funnel-divided-section--dark" id="skepticism-honesty">
      <div className="container">
        {/* No align-items-center: the photo column must stretch to the
            row's full height so the leaves can bleed over the section's
            own padding (see .funnel-awareness__image); the text column
            centers itself instead. */}
        <div className="row g-5">
          <div className="col-12 col-lg-4 d-none d-lg-block">
            <div className="funnel-photo funnel-awareness__image">
              <img
                src="/funnel/v2/08-leaves-dark.webp"
                srcSet="/funnel/v2/08-leaves-dark-800.webp 800w, /funnel/v2/08-leaves-dark.webp 1456w"
                sizes="(min-width: 992px) 60vw, 100vw"
                alt=""
                loading="lazy"
                decoding="async"
              />
            </div>
          </div>
          <div className="col-12 col-lg-8 align-self-center">
            <h2 className="section-title mb-2">{content.title}</h2>
            <p className="funnel-awareness__subtitle">{content.subtitle}</p>
            <p className="section-lead lead mb-0">{content.body}</p>
          </div>
        </div>
      </div>
    </section>
  );
}
