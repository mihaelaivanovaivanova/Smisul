import { useEffect } from 'react';
import Icon from '../icons/Icon';
import { useCarouselScroll } from '../../hooks/useCarouselScroll';
import { product as productCopy } from '../../content/copy';
import type { Media } from '../../types/product';

interface GalleryCarouselProps {
  /** Photos and videos — see getGalleryMediaForVariant. */
  images: Media[];
  productName: string;
}

function isVideo(item: Media): boolean {
  return item.mime_type?.startsWith('video/') ?? false;
}

/**
 * The purchase panel's gallery, as an actual swipeable carousel — every
 * photo/video gets its own full-width slide in a scroll-snap track
 * (swipe on touch, arrow buttons on desktop), rather than
 * ProductGallery.tsx's single static image swapped via thumbnail clicks.
 * Kept as its own component (not a ProductGallery.tsx rewrite) since that
 * component is shared with the generic ProductPage.tsx, which keeps its
 * existing behavior — this page's redesign is scoped to its own
 * components per the approved plan.
 *
 * Mobile shows just the swipeable image and a dot row under it (by
 * request — no thumbnail strip crowding the screen); the thumbnail strip
 * from the original gallery (useful for jumping straight to a specific
 * photo) is md+ only, alongside the arrows.
 */
export default function GalleryCarousel({ images, productName }: GalleryCarouselProps) {
  const imagesKey = images.map((item) => item.id).join(',');
  const { trackRef, canScrollPrev, canScrollNext, activeIndex, scrollByCard, scrollToCard } = useCarouselScroll(
    '.miswak-gallery-slide',
    [imagesKey],
  );

  // Jump back to the first slide whenever the media set itself changes
  // (e.g. PurchasePanel swapping in a different pack size's own photo) —
  // without this, switching packages would leave the carousel scrolled to
  // wherever the shopper last swiped to on the previous set.
  useEffect(() => {
    trackRef.current?.scrollTo({ left: 0 });
    // trackRef is a stable ref object — only imagesKey should trigger this.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [imagesKey]);

  if (images.length === 0) {
    return (
      <div className="ratio ratio-1x1 gallery__placeholder d-flex align-items-center justify-content-center rounded-3">
        {productCopy.noImage}
      </div>
    );
  }

  return (
    <div>
      <div className="miswak-gallery-carousel">
        <div className="miswak-gallery-carousel__track" ref={trackRef}>
          {images.map((item) => (
            <div className="miswak-gallery-slide ratio ratio-1x1 bg-white" key={item.id}>
              {isVideo(item) ? (
                // eslint-disable-next-line jsx-a11y/media-has-caption -- product demo clips have no separate caption track
                <video
                  src={item.url}
                  controls
                  playsInline
                  preload="metadata"
                  className="object-fit-cover"
                  style={{ objectPosition: `${item.focus_x * 100}% ${item.focus_y * 100}%` }}
                />
              ) : (
                <img
                  src={item.url}
                  alt={item.alt_text ?? productName}
                  loading="lazy"
                  className="object-fit-cover"
                  style={{ objectPosition: `${item.focus_x * 100}% ${item.focus_y * 100}%` }}
                />
              )}
            </div>
          ))}
        </div>

        {images.length > 1 && (
          <>
            <button
              type="button"
              className="miswak-gallery-carousel__arrow miswak-gallery-carousel__arrow--prev"
              onClick={() => scrollByCard(-1)}
              disabled={!canScrollPrev}
              aria-label="Предишна снимка"
            >
              <Icon name="chevron-left" />
            </button>
            <button
              type="button"
              className="miswak-gallery-carousel__arrow miswak-gallery-carousel__arrow--next"
              onClick={() => scrollByCard(1)}
              disabled={!canScrollNext}
              aria-label="Следваща снимка"
            >
              <Icon name="chevron-right" />
            </button>
          </>
        )}
      </div>

      {images.length > 1 && (
        <div className="miswak-carousel__dots d-md-none" role="tablist" aria-label={productName}>
          {images.map((item, index) => (
            <button
              key={item.id}
              type="button"
              role="tab"
              className="miswak-carousel__dot"
              aria-selected={index === activeIndex}
              aria-label={productCopy.galleryThumbAria(index + 1, images.length)}
              onClick={() => scrollToCard(index)}
            />
          ))}
        </div>
      )}

      {images.length > 1 && (
        <div className="d-none d-md-flex gap-2 flex-wrap mt-2">
          {images.map((item, index) => {
            const isActive = index === activeIndex;

            return (
              <button
                key={item.id}
                type="button"
                className={`gallery__thumb position-relative p-0 border rounded-2 overflow-hidden ${isActive ? 'gallery__thumb--active' : ''}`}
                style={{ width: 64, height: 64 }}
                onClick={() => scrollToCard(index)}
                aria-label={productCopy.galleryThumbAria(index + 1, images.length)}
                aria-pressed={isActive}
              >
                {isVideo(item) ? (
                  <>
                    {/* eslint-disable-next-line jsx-a11y/media-has-caption -- thumbnail preview only, never plays audio */}
                    <video src={item.url} muted playsInline preload="metadata" className="w-100 h-100 object-fit-cover" />
                    <span className="gallery__thumb-play" aria-hidden="true">
                      <Icon name="play" />
                    </span>
                  </>
                ) : (
                  <img
                    src={item.url}
                    alt=""
                    className="w-100 h-100 object-fit-cover"
                    style={{ objectPosition: `${item.focus_x * 100}% ${item.focus_y * 100}%` }}
                  />
                )}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
