import Icon from '../icons/Icon';
import { useCarouselScroll } from '../../hooks/useCarouselScroll';
import { miswakExpertCards } from '../../content/miswakLanding';

/**
 * "Експертите и Miswak" — a slider of the 4 expert/citation card graphics
 * (miswakExpertCards), placed right before the comparison table, by
 * request. Each card is a single pre-designed image (not rebuilt as
 * markup — see MiswakExpertCard's own docblock), so the only interactive
 * piece added here is __link: an absolutely-positioned, otherwise
 * invisible anchor sized and placed to sit exactly over the card's own
 * "Виж изследването" button (measured against the card's actual pixel
 * dimensions — see .miswak-expert-card-slide__link's own comment for the
 * numbers), since the button drawn into the image has no real href of
 * its own.
 *
 * Reuses the same generic .miswak-carousel track/arrows/dots CSS and
 * useCarouselScroll mechanics as ExpertsSection/UgcVideoSection/
 * UgcGallerySection — a separate component from ExpertsSection rather
 * than a mode bolted onto it, since that one renders structured
 * name/credentials/quote fields as markup, not a single finished card
 * image with an overlaid link.
 */
export default function ExpertCardsSection() {
  const { trackRef, canScrollPrev, canScrollNext, activeIndex, scrollByCard, scrollToCard } = useCarouselScroll(
    '.miswak-expert-card-slide',
    [],
  );

  if (miswakExpertCards.length === 0) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone" id="experts-and-miswak">
      <div className="container">
        <h2 className="section-title mb-4 text-center">Експертите и Miswak</h2>

        <div className="miswak-carousel">
          <button
            type="button"
            className="miswak-carousel__arrow miswak-carousel__arrow--prev"
            onClick={() => scrollByCard(-1)}
            disabled={!canScrollPrev}
            aria-label="Предишна карта"
          >
            <Icon name="chevron-left" />
          </button>

          <div className="miswak-carousel__track" ref={trackRef}>
            {miswakExpertCards.map((card) => (
              <div className="miswak-expert-card-slide" key={card.sourceUrl}>
                <img
                  src={card.image}
                  alt={card.alt}
                  className="miswak-expert-card-slide__image"
                  loading="lazy"
                  decoding="async"
                />
                <a
                  href={card.sourceUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="miswak-expert-card-slide__link"
                  aria-label={`Виж научния източник — ${card.alt}`}
                />
              </div>
            ))}
          </div>

          <button
            type="button"
            className="miswak-carousel__arrow miswak-carousel__arrow--next"
            onClick={() => scrollByCard(1)}
            disabled={!canScrollNext}
            aria-label="Следваща карта"
          >
            <Icon name="chevron-right" />
          </button>
        </div>

        <div className="miswak-carousel__dots d-md-none" role="tablist" aria-label="Карти">
          {miswakExpertCards.map((card, index) => (
            <button
              key={card.sourceUrl}
              type="button"
              role="tab"
              className="miswak-carousel__dot"
              aria-selected={index === activeIndex}
              aria-label={`Карта ${index + 1}`}
              onClick={() => scrollToCard(index)}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
