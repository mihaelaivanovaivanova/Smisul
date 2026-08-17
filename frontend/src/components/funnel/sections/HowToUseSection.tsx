import { useState } from 'react';
import { funnelHowToUse, funnelOffer } from '../../../content/copy';
import { formatPrice } from '../../../services/productCatalog';
import type { Media, Price } from '../../../types/product';

// A real frame extracted directly from the demo clip itself (not a generic
// product photo) — same 9:16 shot the visitor is about to see play, so
// there's no "wait, that's not what I clicked" mismatch, and it shares the
// clip's own aspect ratio (see .funnel-video__wrap in funnel.css).
const VIDEO_POSTER = '/funnel/v2/howtouse-video-poster.webp';

interface HowToUseSectionProps {
  /** Product's own video media, if any admin has uploaded one - the optional demo clip below the steps. */
  videos: Media[];
  /** The FAQ's usage answer's own PDF attachment - the CTA downloads this directly. Falls back to scrolling to that FAQ answer if no PDF is configured. */
  pdfUrl: string | undefined;
  /** The page's primary buy CTA label (same as the Hero button's). */
  ctaPrimaryLabel: string;
  /** The live cheapest package price - same source as the Hero CTA's own price line, never hardcoded. */
  fromPrice: Price | null | undefined;
}

/**
 * Section 6/20 — How To Use. Always renders (the steps are fixed
 * copy — see funnelHowToUse in content/copy.ts); the video clip below
 * them is optional and degrades to nothing when the product has none.
 *
 * Video handling: the <video> element itself isn't mounted until the
 * visitor clicks the poster's play button — stronger than a "lazy"
 * attribute, since zero video bytes are requested until that explicit
 * click. autoPlay only kicks in at that point, which is a direct result
 * of user action, not automatic playback on page load/scroll.
 *
 * CTA: downloads the FAQ usage answer's PDF attachment directly
 * (pdfUrl, resolved in FunnelLandingPage.tsx) rather than scrolling to
 * that FAQ item. Falls back to the old scroll-to-FAQ behavior via the
 * #usage-guide pseudo-anchor if no PDF is currently configured there.
 */
function StepCard({ step, index }: { step: { title: string; body: string }; index: number }) {
  return (
    <div className="funnel-usecase-card funnel-step-card">
      <div className="funnel-step-card__header">
        <span className="funnel-step-card__number" aria-hidden="true">
          {index + 1}
        </span>
        <h3 className="h6 mb-0">{step.title}</h3>
      </div>
      <p className="section-lead lead mb-0">{step.body}</p>
    </div>
  );
}

export default function HowToUseSection({ videos, pdfUrl, ctaPrimaryLabel, fromPrice }: HowToUseSectionProps) {
  const [isPlaying, setIsPlaying] = useState(false);
  const video = videos[0];
  const packagesFromLabel = fromPrice ? funnelOffer.packagesFrom(formatPrice(fromPrice.amount, fromPrice.currency)) : null;

  const guideButton = pdfUrl ? (
    <a href={pdfUrl} target="_blank" rel="noopener noreferrer" className="btn btn-outline-secondary w-100">
      {funnelHowToUse.cta}
    </a>
  ) : (
    <a href="#usage-guide" className="btn btn-outline-secondary w-100">
      {funnelHowToUse.cta}
    </a>
  );

  const buyButton = (
    <a href="#pricing" className="btn btn-primary funnel-hero__cta w-100">
      <span className="funnel-hero__cta-main">{ctaPrimaryLabel}</span>
      {packagesFromLabel && <span className="funnel-hero__cta-sub">{packagesFromLabel}</span>}
    </a>
  );

  const ctaButtons = (
    <div className="d-flex flex-column align-items-stretch gap-2 mx-auto" style={{ maxWidth: '20rem' }}>
      {guideButton}
      {buyButton}
    </div>
  );

  const videoBlock = video && (
    <div className="funnel-video__wrap">
      {isPlaying ? (
        // eslint-disable-next-line jsx-a11y/media-has-caption -- placeholder demo clip; real footage will carry captions
        <video
          controls
          autoPlay
          preload="metadata"
          src={video.url}
          poster={VIDEO_POSTER}
          aria-label={video.alt_text ?? undefined}
        />
      ) : (
        <button
          type="button"
          className="funnel-video__poster-button"
          onClick={() => setIsPlaying(true)}
          aria-label={funnelHowToUse.playVideoAria}
        >
          <img src={VIDEO_POSTER} alt="" loading="lazy" decoding="async" />
          <span className="funnel-video__play-icon" aria-hidden="true">
            ▶
          </span>
        </button>
      )}
    </div>
  );

  return (
    <section className="section funnel-hero-tone funnel-divided-section" id="how-to-use">
      <div className="container">
        <h2 className="section-title mb-2 text-center">{funnelHowToUse.title}</h2>
        <p className="funnel-howtouse__subtitle section-lead lead text-center mb-4">{funnelHowToUse.subtitle}</p>

        {video ? (
          // Mobile (<md): all 6 steps, then the video, then the CTA.
          // Tablet (md-lg): steps 1-3, video, steps 4-6, top to bottom —
          // same DOM order as below, just without the mobile reorder.
          // Desktop (lg+): 3 equal columns side by side, video centered.
          <div className="funnel-howtouse__layout funnel-howtouse__layout--with-video">
            <div className="funnel-howtouse__cards-col funnel-howtouse__cards-col--left">
              {funnelHowToUse.steps.slice(0, 3).map((step, index) => (
                <StepCard step={step} index={index} key={step.title} />
              ))}
            </div>
            <div className="funnel-howtouse__video-col">
              {videoBlock}
              <div className="text-center mt-4">{ctaButtons}</div>
            </div>
            <div className="funnel-howtouse__cards-col funnel-howtouse__cards-col--right">
              {funnelHowToUse.steps.slice(3).map((step, index) => (
                <StepCard step={step} index={index + 3} key={step.title} />
              ))}
            </div>
          </div>
        ) : (
          <>
            <div className="row row-cols-1 row-cols-sm-2 g-3 g-md-4 funnel-howtouse__cards">
              {funnelHowToUse.steps.map((step, index) => (
                <div className="col" key={step.title}>
                  <StepCard step={step} index={index} />
                </div>
              ))}
            </div>
            <div className="text-center mt-4">{ctaButtons}</div>
          </>
        )}
      </div>
    </section>
  );
}
