const BNT_ARTICLE_URL = 'https://bnt.bg/bg/a/kakvo-predstavlyava-va-lshebnata-pra-chka-ot-rastenieto-misvak';

// Smisul's own product photo rather than a frame grabbed from BNT's
// broadcast — that would carry their channel logo/show branding, not
// something to host as a thumbnail on our own page.
const THUMBNAIL = '/funnel/v2/miswak-bnt-thumbnail.png';

/**
 * A real BNT ("Здравето отблизо") segment about Miswak — links out to BNT's
 * own article page rather than embedding their video: no <video>/<iframe>,
 * no direct p.bnt.bg MP4 URL, and nothing downloaded or rehosted here. The
 * whole preview area (thumbnail + play icon) is one semantic <a> so a
 * click anywhere on it, not just the small play icon, opens the original
 * report in a new tab.
 */
export default function MediaMentionSection() {
  return (
    <section className="section funnel-hero-tone" id="media-mention">
      <div className="container">
        <p className="section-eyebrow text-center d-block">НЕ САМО НИЕ ГОВОРИМ ЗА MISWAK</p>
        <h2 className="section-title mb-2 text-center">Miswak по БНТ</h2>
        <p className="section-lead lead text-center mx-auto mb-4" style={{ maxWidth: '36rem' }}>
          Още през 2015 г. „Здравето отблизо“ по БНТ посвещава материал на мисвак и традиционната му употреба за
          орална хигиена.
        </p>

        <div className="miswak-media-mention__wrap mx-auto">
          <a
            href={BNT_ARTICLE_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="miswak-media-mention__play-button"
            aria-label="Гледай репортажа за мисвак в сайта на БНТ"
          >
            <img src={THUMBNAIL} alt="" className="miswak-media-mention__thumbnail" loading="lazy" decoding="async" />
            <span className="miswak-media-mention__play-icon" aria-hidden="true">
              ▶
            </span>
          </a>
        </div>

        <p className="miswak-media-mention__disclaimer mx-auto">
          Външен редакционен материал. БНТ не е свързана със С | Мисъл и материалът не представлява препоръка на
          конкретния ни продукт.
        </p>

        <p className="miswak-media-mention__link text-center">
          <a href={BNT_ARTICLE_URL} target="_blank" rel="noopener noreferrer">
            Гледай репортажа на БНТ →
          </a>
        </p>
      </div>
    </section>
  );
}
