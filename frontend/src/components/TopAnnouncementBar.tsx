import Icon from './icons/Icon';
import { fetchPublicSettings } from '../api/settings';
import { useAsync } from '../hooks/useAsync';
import { topBanner } from '../content/copy';

/**
 * Thin promo strip above the header, styled after benu.bg's top bar.
 * Admin-configurable (visibility + message) via the Shipping tab's Box Now
 * card — see ShippingSettingsPanel.tsx — since the message is currently
 * always a Box Now shipping claim. Defaults to shown with the hardcoded
 * copy.ts fallback while the setting is still loading, so it doesn't flash
 * in and out on every page load; explicit `false` hides it once loaded.
 */
export default function TopAnnouncementBar() {
  const { data: publicSettings } = useAsync(fetchPublicSettings, [], '');

  if (publicSettings?.box_now_banner_enabled === false) {
    return null;
  }

  return (
    <div className="top-announcement-bar">
      <div className="top-announcement-bar__track">
        <Icon name="truck" />
        <span>{publicSettings?.box_now_banner_message || topBanner.message}</span>
      </div>
    </div>
  );
}
