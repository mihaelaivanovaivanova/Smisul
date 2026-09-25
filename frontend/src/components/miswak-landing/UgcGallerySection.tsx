import Icon from '../icons/Icon';
import { useCarouselScroll } from '../../hooks/useCarouselScroll';
import type { MiswakUgcPhoto } from '../../content/miswakLanding';

interface UgcGallerySectionProps {
  photos: MiswakUgcPhoto[];
}

/**
 * "Нашите клиенти" — a carousel of real customer photos, matching the
 * reference's Instagram-Story-style gallery. Renders nothing until
 * miswakUgcPhotos.ts has real entries (see its own doc comment).
 */
export default function UgcGallerySection({ photos }: UgcGallerySectionProps) {
  const { trackRef, canScrollPrev, canScrollNext, activeIndex, scrollByCard, scrollToCard } = useCarouselScroll(
    '.miswak-photo-card',
    [photos],
  );

  if (photos.length === 0) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone" id="ugc-gallery">
      <div className="container">
        <h2 className="section-title mb-4 text-center">
          Нашите клиенти <span aria-hidden="true">❤️</span>
        </h2>

        <div className="miswak-carousel">
          <button
            type="button"
            className="miswak-carousel__arrow miswak-carousel__arrow--prev"
            onClick={() => scrollByCard(-1)}
            disabled={!canScrollPrev}
            aria-label="Предишна снимка"
          >
            <Icon name="chevron-left" />
          </button>

          <div className="miswak-carousel__track" ref={trackRef}>
            {photos.map((photo) => (
              <figure className="miswak-photo-card" key={photo.image}>
                <img src={photo.image} alt={photo.caption ?? ''} loading="lazy" decoding="async" />
                {photo.caption && <figcaption className="miswak-photo-card__caption">{photo.caption}</figcaption>}
              </figure>
            ))}
          </div>

          <button
            type="button"
            className="miswak-carousel__arrow miswak-carousel__arrow--next"
            onClick={() => scrollByCard(1)}
            disabled={!canScrollNext}
            aria-label="Следваща снимка"
          >
            <Icon name="chevron-right" />
          </button>
        </div>

        <div className="miswak-carousel__dots d-md-none" role="tablist" aria-label="Снимки от клиенти">
          {photos.map((photo, index) => (
            <button
              key={photo.image}
              type="button"
              role="tab"
              className="miswak-carousel__dot"
              aria-selected={index === activeIndex}
              aria-label={`Снимка ${index + 1}`}
              onClick={() => scrollToCard(index)}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
