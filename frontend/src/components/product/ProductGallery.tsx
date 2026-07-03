import { useState } from 'react';
import type { Media } from '../../types/product';
import { product as productCopy } from '../../content/copy';

interface ProductGalleryProps {
  images: Media[];
  productName: string;
}

export default function ProductGallery({ images, productName }: ProductGalleryProps) {
  const [activeIndex, setActiveIndex] = useState(0);

  if (images.length === 0) {
    return (
      <div className="ratio ratio-1x1 gallery__placeholder d-flex align-items-center justify-content-center rounded-3">
        {productCopy.noImage}
      </div>
    );
  }

  const active = images[activeIndex] ?? images[0];

  return (
    <div>
      <div className="ratio ratio-1x1 gallery__main bg-white rounded-3 overflow-hidden mb-3">
        <img src={active.url} alt={active.alt_text ?? productName} className="object-fit-contain" />
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
