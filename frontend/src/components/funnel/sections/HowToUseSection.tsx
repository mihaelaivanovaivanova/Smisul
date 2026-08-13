import { useState } from 'react';
import { funnelHowToUse, funnelVideo } from '../../../content/copy';
import type { Media } from '../../../types/product';

interface HowToUseSectionProps {
  /** Product's own video media, if any admin has uploaded one — the optional demo clip below the steps. */
  videos: Media[];
  /** Poster/cover shown before the video is played — falls back gracefully if unavailable. */
  posterImage: Media | undefined;
  /** The FAQ's usage answer's own PDF attachment — the CTA downloads this directly. Falls back to scrolling to that FAQ answer if no PDF is configured. */
  pdfUrl: string | undefined;
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
export default function HowToUseSection({ videos, posterImage, pdfUrl }: HowToUseSectionProps) {
  const [isPlaying, setIsPlaying] = useState(false);
  const video = videos[0];

  return (
    <section className="section funnel-hero-tone funnel-divided-section" id="how-to-use">
      <div className="container">
        <h2 className="section-title mb-2 text-center">{funnelHowToUse.title}</h2>
        <p className="section-lead lead text-center mb-4">{funnelHowToUse.subtitle}</p>

        {/* 5 steps: row-cols-md-3 gives a balanced 3+2 layout — a 4-wide
            grid would leave the 5th card orphaned alone on its own row. */}
        <div className="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3 g-md-4">
          {funnelHowToUse.steps.map((step, index) => (
            <div className="col" key={step.title}>
              <div className="funnel-usecase-card funnel-step-card">
                <span className="funnel-step-card__number" aria-hidden="true">
                  {index + 1}
                </span>
                <h3 className="h6 mb-2">{step.title}</h3>
                <p className="section-lead lead mb-0">{step.body}</p>
              </div>
            </div>
          ))}
        </div>

        {video && (
          <div className="mt-5">
            <h3 className="h6 text-center mb-3">{funnelVideo.title}</h3>
            <div className="funnel-video__wrap">
              {isPlaying ? (
                // eslint-disable-next-line jsx-a11y/media-has-caption -- placeholder demo clip; real footage will carry captions
                <video
                  controls
                  autoPlay
                  preload="metadata"
                  src={video.url}
                  poster={posterImage?.url}
                  aria-label={video.alt_text ?? undefined}
                />
              ) : (
                <button
                  type="button"
                  className="funnel-video__poster-button"
                  onClick={() => setIsPlaying(true)}
                  aria-label={funnelHowToUse.playVideoAria}
                >
                  {posterImage && <img src={posterImage.url} alt="" loading="lazy" decoding="async" />}
                  <span className="funnel-video__play-icon" aria-hidden="true">
                    ▶
                  </span>
                </button>
              )}
            </div>
          </div>
        )}

        <div className="text-center mt-4">
          {pdfUrl ? (
            <a href={pdfUrl} target="_blank" rel="noopener noreferrer" className="btn btn-outline-secondary">
              {funnelHowToUse.cta}
            </a>
          ) : (
            <a href="#usage-guide" className="btn btn-outline-secondary">
              {funnelHowToUse.cta}
            </a>
          )}
        </div>
      </div>
    </section>
  );
}
