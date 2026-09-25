import { useEffect, useState } from 'react';
import Icon from '../icons/Icon';
import type { Media } from '../../types/product';
import { product as productCopy } from '../../content/copy';

interface ProductGalleryProps {
  /** Photos and videos — see getGalleryMedia/getGalleryMediaForVariant. */
  images: Media[];
  productName: string;
}

function isVideo(item: Media): boolean {
  return item.mime_type?.startsWith('video/') ?? false;
}

export default function ProductGallery({ images, productName }: ProductGalleryProps) {
  const [activeIndex, setActiveIndex] = useState(0);
  const active = images[activeIndex] ?? images[0];

  // Reset back to the first item whenever the gallery's own media set
  // changes (e.g. ProductPage swapping in a different variant's photos
  // for a different pack size) — compared by id list, not array identity,
  // since a fresh array is computed on every render regardless of whether
  // the media actually changed.
  const imagesKey = images.map((image) => image.id).join(',');
  useEffect(() => {
    setActiveIndex(0);
  }, [imagesKey]);

  // Holds the previously shown item on screen until the newly selected one
  // has actually finished loading, instead of swapping the source the
  // instant `active` changes. Without this, picking a photo the browser
  // hasn't fetched yet (a race against ProductPage's own preload effect,
  // slow network, cold cache, whatever) shows a blank white box for
  // however long that fetch takes rather than just briefly holding the old
  // photo. Videos skip the preload dance (the trick only works for
  // <img> via the Image() constructor) and swap in immediately — a
  // <video> shows its own loading state, which isn't the same jarring
  // blank flash a bare <img src> swap would be.
  const [displayedItem, setDisplayedItem] = useState<Media | undefined>(active);

  useEffect(() => {
    if (!active || active.id === displayedItem?.id) {
      return;
    }

    if (isVideo(active)) {
      setDisplayedItem(active);
      return;
    }

    let cancelled = false;
    const preloadImage = new Image();
    preloadImage.src = active.url;
    preloadImage.onload = () => {
      if (!cancelled) {
        setDisplayedItem(active);
      }
    };

    return () => {
      cancelled = true;
    };
    // Only re-run when the target item itself changes — displayedItem is
    // this effect's own output, not an input to react to.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [active?.id]);

  if (images.length === 0) {
    return (
      <div className="ratio ratio-1x1 gallery__placeholder d-flex align-items-center justify-content-center rounded-3">
        {productCopy.noImage}
      </div>
    );
  }

  const shown = displayedItem ?? active;

  return (
    <div>
      <div className="ratio ratio-1x1 gallery__main bg-white rounded-3 overflow-hidden mb-3">
        {isVideo(shown) ? (
          // eslint-disable-next-line jsx-a11y/media-has-caption -- product demo clips have no separate caption track
          <video
            key={shown.id}
            src={shown.url}
            controls
            playsInline
            preload="metadata"
            className="object-fit-cover"
            style={{ objectPosition: `${shown.focus_x * 100}% ${shown.focus_y * 100}%` }}
          />
        ) : (
          <img
            src={shown.url}
            alt={shown.alt_text ?? productName}
            className="object-fit-cover"
            style={{ objectPosition: `${shown.focus_x * 100}% ${shown.focus_y * 100}%` }}
          />
        )}
      </div>
      {images.length > 1 && (
        <div className="d-flex gap-2 flex-wrap">
          {images.map((image, index) => {
            const isActive = index === activeIndex;

            return (
              <button
                key={image.id}
                type="button"
                className={`gallery__thumb position-relative p-0 border rounded-2 overflow-hidden ${isActive ? 'gallery__thumb--active' : ''}`}
                style={{ width: 64, height: 64 }}
                onClick={() => setActiveIndex(index)}
                aria-label={productCopy.galleryThumbAria(index + 1, images.length)}
                aria-pressed={isActive}
              >
                {isVideo(image) ? (
                  <>
                    {/* eslint-disable-next-line jsx-a11y/media-has-caption -- thumbnail preview only, never plays audio */}
                    <video src={image.url} muted playsInline preload="metadata" className="w-100 h-100 object-fit-cover" />
                    <span className="gallery__thumb-play" aria-hidden="true">
                      <Icon name="play" />
                    </span>
                  </>
                ) : (
                  <img
                    src={image.url}
                    alt=""
                    className="w-100 h-100 object-fit-cover"
                    style={{ objectPosition: `${image.focus_x * 100}% ${image.focus_y * 100}%` }}
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
