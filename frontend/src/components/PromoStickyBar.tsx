import { useEffect, useState } from 'react';
import { fetchPublicSettings } from '../api/settings';
import { useAsync } from '../hooks/useAsync';
import { promoBanner } from '../content/copy';

/**
 * Site-wide sticky promo strip, mounted in PublicLayout.tsx right under
 * <Navbar /> - unlike TopAnnouncementBar (which sits above the header and
 * just scrolls away with the page), this one is `position: sticky` and
 * stays stuck directly beneath the navbar while scrolling, so the promo
 * code stays visible everywhere on the public storefront.
 *
 * `stickyTop` is measured from the live navbar height (not hardcoded,
 * same technique as PurchasePanel.tsx's own gallery-sticky offset) so it
 * sticks flush underneath it rather than guessing a pixel offset that
 * could drift if the navbar's own height ever changes (e.g. mobile vs
 * desktop nav height, or TopAnnouncementBar wrapping to two lines).
 *
 * Admin-configurable (visibility + message) via Settings -> General - see
 * SettingsSeeder.php's general.promo_banner_* rows. Same "opt-out, not
 * opt-in" default-shown pattern as TopAnnouncementBar: only an explicit
 * `false` hides it, so it doesn't flash in and out while settings load.
 */
export default function PromoStickyBar() {
  const { data: publicSettings } = useAsync(fetchPublicSettings, [], '');
  const [stickyTop, setStickyTop] = useState(0);

  useEffect(() => {
    function syncStickyTop() {
      const navbar = document.querySelector('.navbar-frosted');
      setStickyTop(navbar ? navbar.getBoundingClientRect().height : 0);
    }

    syncStickyTop();
    window.addEventListener('resize', syncStickyTop);
    return () => window.removeEventListener('resize', syncStickyTop);
  }, []);

  if (publicSettings?.promo_banner_enabled === false) {
    return null;
  }

  return (
    <div className="promo-sticky-bar" style={{ top: stickyTop }}>
      <span>{publicSettings?.promo_banner_message || promoBanner.message}</span>
    </div>
  );
}
