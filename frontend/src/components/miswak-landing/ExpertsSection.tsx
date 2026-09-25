import Icon from '../icons/Icon';
import { useCarouselScroll } from '../../hooks/useCarouselScroll';
import type { MiswakExpert } from '../../content/miswakLanding';

interface ExpertsSectionProps {
  experts: MiswakExpert[];
}

/**
 * "С подкрепата на експерти" — a carousel of real dentist/expert
 * endorsements (photo, name, credentials, quote), matching the reference's
 * expert-endorsement section. Renders nothing until miswakExperts.ts has
 * real entries (see its own doc comment) — no fabricated quotes/photos.
 */
export default function ExpertsSection({ experts }: ExpertsSectionProps) {
  const { trackRef, canScrollPrev, canScrollNext, activeIndex, scrollByCard, scrollToCard } = useCarouselScroll(
    '.miswak-expert-card',
    [experts],
  );

  if (experts.length === 0) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone" id="experts">
      <div className="container">
        <h2 className="section-title mb-4 text-center">
          С подкрепата на <span className="funnel-eyebrow-accent">експерти</span>
        </h2>

        <div className="miswak-carousel">
          <button
            type="button"
            className="miswak-carousel__arrow miswak-carousel__arrow--prev"
            onClick={() => scrollByCard(-1)}
            disabled={!canScrollPrev}
            aria-label="Предишен експерт"
          >
            <Icon name="chevron-left" />
          </button>

          <div className="miswak-carousel__track" ref={trackRef}>
            {experts.map((expert) => (
              <figure className="miswak-expert-card" key={expert.name}>
                <img src={expert.photo} alt="" className="miswak-expert-card__photo" loading="lazy" decoding="async" />
                <figcaption>
                  <h3 className="h6 mb-1">{expert.name}</h3>
                  <p className="miswak-expert-card__credentials">{expert.credentials}</p>
                  <blockquote className="miswak-expert-card__quote mb-0">{expert.quote}</blockquote>
                  {expert.link && (
                    <a href={expert.link} target="_blank" rel="noopener noreferrer" className="miswak-expert-card__link">
                      Виж повече
                    </a>
                  )}
                </figcaption>
              </figure>
            ))}
          </div>

          <button
            type="button"
            className="miswak-carousel__arrow miswak-carousel__arrow--next"
            onClick={() => scrollByCard(1)}
            disabled={!canScrollNext}
            aria-label="Следващ експерт"
          >
            <Icon name="chevron-right" />
          </button>
        </div>

        <div className="miswak-carousel__dots d-md-none" role="tablist" aria-label="Експерти">
          {experts.map((expert, index) => (
            <button
              key={expert.name}
              type="button"
              role="tab"
              className="miswak-carousel__dot"
              aria-selected={index === activeIndex}
              aria-label={`Експерт ${index + 1}`}
              onClick={() => scrollToCard(index)}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
