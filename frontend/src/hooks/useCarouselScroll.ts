import { useEffect, useRef, useState } from 'react';

export interface CarouselScrollState {
  trackRef: React.RefObject<HTMLDivElement | null>;
  canScrollPrev: boolean;
  canScrollNext: boolean;
  activeIndex: number;
  scrollByCard: (direction: 1 | -1) => void;
  scrollToCard: (index: number) => void;
}

/**
 * getComputedStyle(track).columnGap is "normal" (not "0px") for a track
 * with no gap CSS property at all — parseFloat("normal") is NaN, which
 * silently breaks every step/activeIndex calculation below (NaN propagates
 * through the arithmetic and Math.round(x / NaN) is NaN, so `index ===
 * activeIndex` never matches again). Applying the `|| 0` fallback after
 * parseFloat (not by pre-guarding the input string) catches that case too,
 * not just an empty/missing value.
 */
function getTrackGap(track: HTMLElement): number {
  return parseFloat(getComputedStyle(track).columnGap) || 0;
}

/**
 * Scroll-snap carousel mechanics (prev/next arrow enabling, active dot
 * tracking) shared by every horizontally-scrolling card carousel on the
 * Miswak landing page (Experts/UgcVideo/UgcGallery sections) — extracted
 * from FunnelTestimonialsSection's originally-local implementation once a
 * third section needed the exact same logic. `cardSelector` picks the
 * first card in the track to measure its width/gap against.
 */
export function useCarouselScroll(cardSelector: string, deps: unknown[]): CarouselScrollState {
  const trackRef = useRef<HTMLDivElement>(null);
  const [canScrollPrev, setCanScrollPrev] = useState(false);
  const [canScrollNext, setCanScrollNext] = useState(false);
  const [activeIndex, setActiveIndex] = useState(0);

  useEffect(() => {
    const track = trackRef.current;
    if (!track) {
      return;
    }

    function updateScrollState() {
      if (!track) {
        return;
      }
      setCanScrollPrev(track.scrollLeft > 4);
      setCanScrollNext(track.scrollLeft + track.clientWidth < track.scrollWidth - 4);

      const card = track.querySelector<HTMLElement>(cardSelector);
      if (card) {
        const step = card.getBoundingClientRect().width + getTrackGap(track);
        setActiveIndex(step > 0 ? Math.round(track.scrollLeft / step) : 0);
      }
    }

    updateScrollState();
    track.addEventListener('scroll', updateScrollState, { passive: true });
    window.addEventListener('resize', updateScrollState);
    return () => {
      track.removeEventListener('scroll', updateScrollState);
      window.removeEventListener('resize', updateScrollState);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);

  function scrollByCard(direction: 1 | -1) {
    const track = trackRef.current;
    const card = track?.querySelector<HTMLElement>(cardSelector);
    if (!track || !card) {
      return;
    }
    track.scrollBy({ left: (card.getBoundingClientRect().width + getTrackGap(track)) * direction, behavior: 'smooth' });
  }

  function scrollToCard(index: number) {
    const track = trackRef.current;
    const card = track?.querySelector<HTMLElement>(cardSelector);
    if (!track || !card) {
      return;
    }
    track.scrollTo({ left: index * (card.getBoundingClientRect().width + getTrackGap(track)), behavior: 'smooth' });
  }

  return { trackRef, canScrollPrev, canScrollNext, activeIndex, scrollByCard, scrollToCard };
}
