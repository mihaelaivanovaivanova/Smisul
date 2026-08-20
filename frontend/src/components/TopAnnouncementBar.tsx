import Icon from './icons/Icon';
import { topBanner } from '../content/copy';

/** Thin promo strip above the header, styled after benu.bg's top bar. */
export default function TopAnnouncementBar() {
  return (
    <div className="top-announcement-bar">
      <div className="top-announcement-bar__track">
        <Icon name="truck" />
        <span>{topBanner.message}</span>
      </div>
    </div>
  );
}
