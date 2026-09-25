import Icon from '../icons/Icon';
import { useCarouselScroll } from '../../hooks/useCarouselScroll';
import type { MiswakUgcVideo } from '../../content/miswakLanding';

interface UgcVideoSectionProps {
  videos: MiswakUgcVideo[];
}

/**
 * Vertical UGC video carousel — real customers using the product on
 * camera, matching the reference's video-testimonial section. Renders
 * nothing until miswakUgcVideos.ts has real clips (see its own doc
 * comment) — no stock/staged substitute footage.
 */
export default function UgcVideoSection({ videos }: UgcVideoSectionProps) {
  const { trackRef, canScrollPrev, canScrollNext, activeIndex, scrollByCard, scrollToCard } = useCarouselScroll(
    '.miswak-video-card',
    [videos],
  );

  if (videos.length === 0) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone" id="ugc-videos">
      <div className="container">
        <div className="miswak-carousel">
          <button
            type="button"
            className="miswak-carousel__arrow miswak-carousel__arrow--prev"
            onClick={() => scrollByCard(-1)}
            disabled={!canScrollPrev}
            aria-label="Предишно видео"
          >
            <Icon name="chevron-left" />
          </button>

          <div className="miswak-carousel__track" ref={trackRef}>
            {videos.map((video) => (
              <figure className="miswak-video-card" key={video.video_url}>
                {/* eslint-disable-next-line jsx-a11y/media-has-caption -- UGC clips have no separate caption track */}
                <video
                  className="miswak-video-card__media"
                  src={video.video_url}
                  poster={video.poster}
                  controls
                  playsInline
                  preload="none"
                />
                {video.caption && <figcaption className="miswak-video-card__caption">{video.caption}</figcaption>}
              </figure>
            ))}
          </div>

          <button
            type="button"
            className="miswak-carousel__arrow miswak-carousel__arrow--next"
            onClick={() => scrollByCard(1)}
            disabled={!canScrollNext}
            aria-label="Следващо видео"
          >
            <Icon name="chevron-right" />
          </button>
        </div>

        <div className="miswak-carousel__dots d-md-none" role="tablist" aria-label="Видеа от клиенти">
          {videos.map((video, index) => (
            <button
              key={video.video_url}
              type="button"
              role="tab"
              className="miswak-carousel__dot"
              aria-selected={index === activeIndex}
              aria-label={`Видео ${index + 1}`}
              onClick={() => scrollToCard(index)}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
