import { useEffect, useRef, useState } from 'react';
import Icon from '../icons/Icon';
import { formatPrice } from '../../services/productCatalog';
import { funnelHowToUse, funnelOffer } from '../../content/copy';
import type { Media, Price } from '../../types/product';

// Same poster HowToUseSection.tsx uses — a real frame from the demo clip
// itself, not a generic product photo.
const VIDEO_POSTER = '/funnel/v2/howtouse-video-poster.webp';

interface HowToUseAccordionProps {
  videos: Media[];
  pdfUrl: string | undefined;
  ctaPrimaryLabel: string;
  fromPrice: Price | null | undefined;
}

/**
 * "Обели. Размекни. Почисти." presentation for the Miswak redesign, by
 * request — a numbered accordion timeline (one step's body open at a
 * time, connected by a vertical line) instead of HowToUseSection's flat
 * always-expanded card list. Replaces that shared component entirely on
 * this page, at every breakpoint (an earlier version only did this on
 * mobile and kept HowToUseSection for desktop/tablet; now both use this
 * one, per request, so it's a standalone component/DOM block rather than
 * a new mode bolted onto that shared one — HowToUseSection is also used
 * by the live "/" funnel page, and this whole layout is Miswak-only).
 * Reuses funnelHowToUse's copy, the product video, and the same CTA
 * buttons verbatim.
 *
 * Mobile (default): title/subtitle/divider, then the accordion, then the
 * video, then the CTA buttons — all stacked in that order.
 * Desktop (lg+, matching this page's other two-column sections): the
 * title/subtitle/divider stay centered on top, then the accordion and the
 * video sit side by side (accordion left, video right, accordion nudged
 * further right via its own margin-left so it sits closer to the row's
 * center instead of flush against its left edge), then the CTA buttons
 * below — centered against the whole accordion+video row (not just the
 * video's own column) since they're a sibling of that row, not nested
 * inside its video column. See
 * .miswak-howtouse__layout's lg+ rule.
 *
 * Collapse animation is the same grid-rows 0fr->1fr technique
 * FaqSection/.funnel-faq-answer-wrap already uses, just under its own
 * miswak-howtouse-accordion__* classNames so it can't affect that
 * component's CSS.
 *
 * Each step also opens on hover, desktop only (by request) — the
 * `window.matchMedia('(hover: hover)')` check keeps this from firing on
 * touch devices, which can dispatch a simulated hover right before a tap
 * and would otherwise fight with the click toggle below it. Click still
 * works everywhere (including desktop, where clicking after hovering is a
 * no-op since it's already open) — hover is additive, not a replacement.
 *
 * The hover-open is debounced (150ms via hoverTimeoutRef, cleared on
 * mouseleave) rather than firing the instant the cursor enters an item.
 * Without that delay, moving the mouse down toward a later step drags it
 * through every earlier step on the way there, popping each one open the
 * moment the cursor crosses it — and since opening one grows its height
 * (the grid-rows animation), that push shifts every step below it,
 * which can carry the intended target out from under a cursor that's
 * still travelling toward its original position, so the wrong step (or
 * none) ends up open. A short pause before committing to "open" means a
 * quick pass-through never triggers it, only stopping on a step does.
 */
export default function HowToUseAccordion({ videos, pdfUrl, ctaPrimaryLabel, fromPrice }: HowToUseAccordionProps) {
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const hoverTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => clearHoverTimeout, []);

  function clearHoverTimeout() {
    if (hoverTimeoutRef.current !== null) {
      clearTimeout(hoverTimeoutRef.current);
      hoverTimeoutRef.current = null;
    }
  }

  function handleItemMouseEnter(index: number) {
    if (!window.matchMedia('(hover: hover)').matches) {
      return;
    }
    clearHoverTimeout();
    hoverTimeoutRef.current = setTimeout(() => setOpenIndex(index), 150);
  }
  const video = videos[0];
  const packagesFromLabel = fromPrice ? funnelOffer.packagesFrom(formatPrice(fromPrice.amount, fromPrice.currency)) : null;

  return (
    <section className="section funnel-hero-tone" id="how-to-use">
      <div className="container">
        <h2 className="section-title mb-2 text-center">{funnelHowToUse.title}</h2>
        <div className="miswak-howtouse__divider" aria-hidden="true">
          <Icon name="leaf" />
        </div>

        <div className="miswak-howtouse__layout">
          <div className="miswak-howtouse-accordion">
            <div className="miswak-howtouse-accordion__line" aria-hidden="true" />
            {funnelHowToUse.steps.map((step, index) => {
              const isOpen = openIndex === index;
              const panelId = `miswak-howtouse-panel-${index}`;

              return (
                <div
                  className={`miswak-howtouse-accordion__item ${isOpen ? 'is-active' : ''}`}
                  key={step.title}
                  onMouseEnter={() => handleItemMouseEnter(index)}
                  onMouseLeave={clearHoverTimeout}
                >
                  <button
                    type="button"
                    className="miswak-howtouse-accordion__toggle"
                    aria-expanded={isOpen}
                    aria-controls={panelId}
                    onClick={() => setOpenIndex(isOpen ? null : index)}
                  >
                    <span className="miswak-howtouse-accordion__number" aria-hidden="true">
                      {index + 1}
                    </span>
                    <span className="miswak-howtouse-accordion__label">{step.title}</span>
                    <Icon name="chevron-right" className="miswak-howtouse-accordion__chevron" />
                  </button>
                  {/* Always mounted so open/close can animate height — see
                      FaqSection's own identical comment on this pattern. */}
                  <div
                    className={`miswak-howtouse-accordion__body-wrap ${isOpen ? 'is-open' : ''}`}
                    id={panelId}
                    aria-hidden={!isOpen}
                  >
                    <div className="miswak-howtouse-accordion__body">
                      <div className="miswak-howtouse-accordion__body-inner">{step.body}</div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          <div className="miswak-howtouse__side">
            {video && (
              <div className="miswak-howtouse__video">
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
              </div>
            )}
          </div>
        </div>

        {/* Sibling of .miswak-howtouse__layout, not nested inside
            .miswak-howtouse__side — centering it against the full
            accordion+video row (via the container, which shares the same
            center point the max-width:60rem row is itself centered
            within) instead of just the narrower video column, by
            request. Mobile is unaffected either way: it was already the
            last stacked element there, and still is. */}
        <div className="text-center mt-4">
          <div className="d-flex flex-column align-items-stretch gap-2 mx-auto" style={{ maxWidth: '26rem' }}>
            {pdfUrl ? (
              <a href={pdfUrl} target="_blank" rel="noopener noreferrer" className="btn btn-outline-secondary w-100">
                {funnelHowToUse.cta}
              </a>
            ) : (
              <a href="#usage-guide" className="btn btn-outline-secondary w-100">
                {funnelHowToUse.cta}
              </a>
            )}
            <a href="#pricing" className="btn btn-primary funnel-hero__cta w-100">
              <span className="funnel-hero__cta-main">{ctaPrimaryLabel}</span>
              {packagesFromLabel && <span className="funnel-hero__cta-sub">{packagesFromLabel}</span>}
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}
