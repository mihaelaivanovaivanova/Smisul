import { useState } from 'react';
import { Link } from 'react-router-dom';
import Logo from './Logo';
import ContactModal from './ContactModal';
import Icon from './icons/Icon';
import { fetchLegalDocuments } from '../api/legal';
import { fetchPublicSettings } from '../api/settings';
import { useAsync } from '../hooks/useAsync';
import { useCookieConsent } from '../hooks/useCookieConsent';
import { useSettings } from '../hooks/useSettings';
import { footer, nav, siteName } from '../content/copy';

export default function Footer() {
  const year = new Date().getFullYear();
  const { data: legalDocuments } = useAsync(fetchLegalDocuments, [], '');
  const { data: publicSettings } = useAsync(fetchPublicSettings, [], '');
  const { openPreferencesModal } = useCookieConsent();
  const { funnelModeEnabled } = useSettings();
  const [isContactModalOpen, setIsContactModalOpen] = useState(false);

  // Legal merchant identity — the whole column appears only once the
  // admin has filled at least one of the fields (Settings → General).
  const hasMerchantInfo =
    publicSettings &&
    Boolean(
      publicSettings.company_name ??
        publicSettings.company_id ??
        publicSettings.contact_address ??
        publicSettings.support_phone ??
        publicSettings.store_email,
    );

  // Social profiles — each icon renders only when its URL is configured
  // (Settings → General).
  const socials = [
    { icon: 'instagram' as const, label: 'Instagram', url: publicSettings?.social_instagram },
    { icon: 'facebook' as const, label: 'Facebook', url: publicSettings?.social_facebook },
    { icon: 'tiktok' as const, label: 'TikTok', url: publicSettings?.social_tiktok },
  ].filter((social) => Boolean(social.url));

  return (
    <footer className="footer-dark mt-auto py-4">
      <div className="container">
        <div className="d-flex flex-column flex-md-row justify-content-between gap-4">
          <Logo tagline light />

          <nav aria-label={footer.companyHeading}>
            <h2 className="h6 mb-2">{footer.companyHeading}</h2>
            <ul className="list-unstyled d-flex flex-column gap-1 small mb-0">
              <li>
                <Link className="text-decoration-none text-muted" to="/">
                  {nav.home}
                </Link>
              </li>
              {!funnelModeEnabled && (
                <li>
                  <Link className="text-decoration-none text-muted" to="/search">
                    {nav.browseProducts}
                  </Link>
                </li>
              )}
              <li>
                <Link className="text-decoration-none text-muted" to="/about">
                  {footer.about}
                </Link>
              </li>
              <li>
                <button
                  type="button"
                  className="border-0 bg-transparent p-0 text-decoration-none text-muted"
                  onClick={() => setIsContactModalOpen(true)}
                >
                  {footer.contact}
                </button>
              </li>
            </ul>
          </nav>

          {hasMerchantInfo && publicSettings && (
            <div>
              <h2 className="h6 mb-2">{footer.merchantHeading}</h2>
              <ul className="list-unstyled d-flex flex-column gap-1 small mb-0 text-muted">
                {publicSettings.company_name && <li>{publicSettings.company_name}</li>}
                {publicSettings.company_id && (
                  <li>
                    {footer.companyIdLabel}: {publicSettings.company_id}
                  </li>
                )}
                {publicSettings.contact_address && <li>{publicSettings.contact_address}</li>}
                {publicSettings.support_phone && (
                  <li>
                    <a className="text-decoration-none text-muted" href={`tel:${publicSettings.support_phone.replace(/\s+/g, '')}`}>
                      {publicSettings.support_phone}
                    </a>
                  </li>
                )}
                {publicSettings.store_email && (
                  <li>
                    <a className="text-decoration-none text-muted" href={`mailto:${publicSettings.store_email}`}>
                      {publicSettings.store_email}
                    </a>
                  </li>
                )}
              </ul>
            </div>
          )}

          <nav aria-label={footer.legalHeading}>
            <h2 className="h6 mb-2">{footer.legalHeading}</h2>
            <ul className="list-unstyled d-flex flex-column gap-1 small mb-0">
              {legalDocuments?.map((document) => (
                <li key={document.slug}>
                  <Link className="text-decoration-none text-muted" to={`/legal/${document.slug}`}>
                    {document.title}
                  </Link>
                </li>
              ))}
              <li>
                <button
                  type="button"
                  className="border-0 bg-transparent p-0 text-decoration-none text-muted"
                  onClick={openPreferencesModal}
                >
                  {footer.cookieSettings}
                </button>
              </li>
            </ul>
          </nav>
        </div>

        <hr className="my-3" style={{ borderColor: 'rgba(255, 255, 255, 0.18)' }} />

        <div className="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
          <span className="text-muted small">
            &copy; {year} {siteName}. Всички права запазени.
          </span>
          <span className="d-inline-flex align-items-center gap-4">
            {socials.length > 0 && (
              <span className="d-inline-flex align-items-center gap-2">
                {socials.map((social) => (
                  <a
                    key={social.icon}
                    className="footer-social"
                    href={social.url as string}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={social.label}
                  >
                    <Icon name={social.icon} />
                  </a>
                ))}
              </span>
            )}
            <span className="footer-payment-logos d-inline-flex align-items-center gap-2">
              <img src="/payments/visa.svg" alt="Visa" height={14} loading="lazy" />
              <img src="/payments/mastercard.svg" alt="Mastercard" height={22} loading="lazy" />
            </span>
          </span>
        </div>
      </div>

      <ContactModal show={isContactModalOpen} onClose={() => setIsContactModalOpen(false)} />
    </footer>
  );
}
