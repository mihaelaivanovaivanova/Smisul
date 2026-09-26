import Icon from '../icons/Icon';
import { miswakTrustBullets } from '../../content/miswakLanding';

/** Quick-scan benefit checklist shown between the description and the package selector. */
export default function TrustBullets() {
  return (
    <ul className="miswak-trust-bullets">
      {miswakTrustBullets.map((text) => (
        <li key={text} className="miswak-trust-bullets__item">
          <span className="miswak-trust-bullets__icon" aria-hidden="true">
            <Icon name="check" />
          </span>
          {text}
        </li>
      ))}
    </ul>
  );
}
