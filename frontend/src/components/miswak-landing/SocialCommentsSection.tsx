import Icon from '../icons/Icon';
import { miswakSocialComments } from '../../content/miswakLanding';

const SUMMARY_ITEMS = ['По-чисти зъби', 'Избелващ ефект', 'Доволни от доставката', 'Интерес към естествения произход'];

/**
 * "Истински коментари. Истинско доверие." — a bento grid of real, unedited
 * screenshots of Facebook/Instagram comments on Miswak posts
 * (miswakSocialComments), placed right before the full reviews list by
 * request: reviews are collected through our own form, this section is the
 * unprompted stuff people said on social media before we ever asked. Each
 * card is the actual screenshot image, not the comment text retyped as
 * markup — the visible platform chrome (handle, timestamp, reply/like UI)
 * is what makes it verifiable as real, so recreating it as styled HTML
 * would undercut the point.
 *
 * Grid layout follows the user-supplied desktop/mobile reference mockups:
 * a 3-column bento on desktop (single column on mobile, same source order)
 * with a "what recurs most" summary card alongside the last two comments.
 */
export default function SocialCommentsSection() {
  if (miswakSocialComments.length === 0) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone miswak-social-comments" id="social-comments">
      <div className="container">
        <div className="miswak-social-comments__header">
          <h2 className="section-title mb-4">
            Истински коментари.
            <br />
            Истинско доверие.
          </h2>

          <div className="miswak-social-comments__pills">
            <span className="miswak-social-comments__pill">
              <Icon name="chat" /> Реални коментари
            </span>
            <span className="miswak-social-comments__pill">
              <Icon name="users" /> Социални мрежи
            </span>
            <span className="miswak-social-comments__pill">
              <Icon name="leaf" /> Без сценарий
            </span>
          </div>
        </div>

        <div className="miswak-social-comments__grid">
          {miswakSocialComments.map((comment, index) => (
            <figure className={`miswak-social-comments__card miswak-social-comments__card--${index + 1}`} key={comment.image}>
              <img src={comment.image} alt={comment.alt} loading="lazy" decoding="async" />
              <figcaption className="miswak-social-comments__tag">
                <Icon name={comment.tagIcon} />
                {comment.tagLabel}
              </figcaption>
            </figure>
          ))}

          <div className="miswak-social-comments__summary">
            <h3>Какво се повтаря най-често?</h3>
            <ul>
              {SUMMARY_ITEMS.map((item) => (
                <li key={item}>
                  <Icon name="check" />
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </section>
  );
}
