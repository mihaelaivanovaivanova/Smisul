import { useEffect, useState } from 'react';
import type { Media } from '../../types/product';
import { product as productCopy } from '../../content/copy';

interface ProductGalleryProps {
  images: Media[];
  productName: string;
}

export default function ProductGallery({ images, productName }: ProductGalleryProps) {
  const [activeIndex, setActiveIndex] = useState(0);
  const active = images[activeIndex] ?? images[0];

  // Reset back to the first photo whenever the gallery's own image set
  // changes (e.g. ProductPage swapping in a different variant's photos
  // for a different pack size) — compared by id list, not array identity,
  // since a fresh array is computed on every render regardless of whether
  // the images actually changed.
  const imagesKey = images.map((image) => image.id).join(',');
  useEffect(() => {
    setActiveIndex(0);
  }, [imagesKey]);

  // Holds the previously shown photo on screen until the newly selected
  // one has actually finished loading, instead of swapping the <img> src
  // the instant `active` changes. Without this, picking a photo the
  // browser hasn't fetched yet (a race against ProductPage's own preload
  // effect, slow network, cold cache, whatever) shows a blank white box
  // for however long that fetch takes rather than just briefly holding
  // the old photo.
  const [displayedUrl, setDisplayedUrl] = useState(active?.url);

  useEffect(() => {
    if (!active || active.url === displayedUrl) {
      return;
    }

    let cancelled = false;
    const preloadImage = new Image();
    preloadImage.src = active.url;
    preloadImage.onload = () => {
      if (!cancelled) {
        setDisplayedUrl(active.url);
      }
    };

    return () => {
      cancelled = true;
    };
    // Only re-run when the target URL itself changes — displayedUrl is
    // this effect's own output, not an input to react to.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [active?.url]);

  if (images.length === 0) {
    return (
      <div className="ratio ratio-1x1 gallery__placeholder d-flex align-items-center justify-content-center rounded-3">
        {productCopy.noImage}
      </div>
    );
  }

  return (
    <div>
      <div className="ratio ratio-1x1 gallery__main bg-white rounded-3 overflow-hidden mb-3">
        <img src={displayedUrl ?? active.url} alt={active.alt_text ?? productName} className="object-fit-cover" />
      </div>
      {images.length > 1 && (
        <div className="d-flex gap-2 flex-wrap">
          {images.map((image, index) => {
            const isActive = index === activeIndex;

            return (
              <button
                key={image.id}
                type="button"
                className={`gallery__thumb p-0 border rounded-2 overflow-hidden ${isActive ? 'gallery__thumb--active' : ''}`}
                style={{ width: 64, height: 64 }}
                onClick={() => setActiveIndex(index)}
                aria-label={productCopy.galleryThumbAria(index + 1, images.length)}
                aria-pressed={isActive}
              >
                <img src={image.url} alt="" className="w-100 h-100 object-fit-cover" />
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
