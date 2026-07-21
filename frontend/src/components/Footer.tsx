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

/**
 * Deliberately flat and horizontal: one brand/nav/socials row, one
 * wrapping line of legal links, one bottom line — instead of tall
 * stacked columns. The merchant-identity line and the social icons
 * appear only once the admin fills them in (Settings → General).
 */
export default function Footer() {
  const year = new Date().getFullYear();
  const { data: legalDocuments } = useAsync(fetchLegalDocuments, [], '');
  const { data: publicSettings } = useAsync(fetchPublicSettings, [], '');
  const { openPreferencesModal } = useCookieConsent();
  const { funnelModeEnabled } = useSettings();
  const [isContactModalOpen, setIsContactModalOpen] = useState(false);

  const socials = [
    { icon: 'instagram' as const, label: 'Instagram', url: publicSettings?.social_instagram },
    { icon: 'facebook' as const, label: 'Facebook', url: publicSettings?.social_facebook },
    { icon: 'tiktok' as const, label: 'TikTok', url: publicSettings?.social_tiktok },
  ].filter((social) => Boolean(social.url));

  const hasMerchantInfo =
    publicSettings &&
    Boolean(
      publicSettings.company_name ??
        publicSettings.company_name_en ??
        publicSettings.company_manager ??
        publicSettings.company_id ??
        publicSettings.contact_address ??
        publicSettings.support_phone ??
        publicSettings.store_email,
    );

  return (
    <footer className="footer-dark mt-auto py-4">
      <div className="container d-flex flex-column gap-3">
        {/* Brand · primary nav · socials — one row. */}
        <div className="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
          <Logo tagline light />

          <nav aria-label={footer.companyHeading} className="d-flex flex-wrap justify-content-center align-items-center column-gap-4 row-gap-1">
            <Link className="text-decoration-none text-muted small" to="/">
              {nav.home}
            </Link>
            {!funnelModeEnabled && (
              <Link className="text-decoration-none text-muted small" to="/search">
                {nav.browseProducts}
              </Link>
            )}
            <Link className="text-decoration-none text-muted small" to="/about">
              {footer.about}
            </Link>
            <button
              type="button"
              className="border-0 bg-transparent p-0 text-decoration-none text-muted small"
              onClick={() => setIsContactModalOpen(true)}
            >
              {footer.contact}
            </button>
          </nav>

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
        </div>

        <hr className="my-0" style={{ borderColor: 'rgba(255, 255, 255, 0.18)' }} />

        {/* Legal links — one wrapping line. */}
        <nav
          aria-label={footer.legalHeading}
          className="d-flex flex-wrap justify-content-center justify-content-md-start column-gap-4 row-gap-1"
        >
          {legalDocuments?.map((document) => (
            <Link key={document.slug} className="text-decoration-none text-muted small" to={`/legal/${document.slug}`}>
              {document.title}
            </Link>
          ))}
          <button
            type="button"
            className="border-0 bg-transparent p-0 text-decoration-none text-muted small"
            onClick={openPreferencesModal}
          >
            {footer.cookieSettings}
          </button>
        </nav>

        {/* Merchant identity — one muted line, only when configured. */}
        {hasMerchantInfo && publicSettings && (
          <p className="d-flex flex-wrap justify-content-center justify-content-md-start column-gap-4 row-gap-1 small text-muted mb-0">
            {(publicSettings.company_name_en || publicSettings.company_name) && (
              <span>
                {footer.companyLabel}:{' '}
                {[publicSettings.company_name_en, publicSettings.company_name].filter(Boolean).join(' / ')}
              </span>
            )}
            {publicSettings.company_manager && (
              <span>
                {footer.managerLabel}: {publicSettings.company_manager}
              </span>
            )}
            {publicSettings.company_id && (
              <span>
                {footer.companyIdLabel}: {publicSettings.company_id}
              </span>
            )}
            {publicSettings.contact_address && <span>{publicSettings.contact_address}</span>}
            {publicSettings.support_phone && (
              <a className="text-decoration-none text-muted" href={`tel:${publicSettings.support_phone.replace(/\s+/g, '')}`}>
                {publicSettings.support_phone}
              </a>
            )}
            {publicSettings.store_email && (
              <a className="text-decoration-none text-muted" href={`mailto:${publicSettings.store_email}`}>
                {publicSettings.store_email}
              </a>
            )}
          </p>
        )}

        {/* Copyright · payment marks. */}
        <div className="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
          <span className="text-muted small">
            &copy; {year} {siteName}. Всички права запазени.
          </span>
          <span className="footer-payment-info text-center text-sm-end">
            <span className="footer-payment-logos d-inline-flex align-items-center gap-2" aria-label="Приемани карти">
              <img src="/payments/visa.svg" alt="Visa" height={14} loading="lazy" />
              <img src="/payments/mastercard.svg" alt="Mastercard" height={22} loading="lazy" />
              <img src="/payments/amex.svg" alt="American Express" height={22} loading="lazy" />
              <img src="/payments/apple-pay.svg" alt="Apple Pay" height={22} loading="lazy" />
              <img src="/payments/google-pay.svg" alt="Google Pay" height={22} loading="lazy" />
            </span>
            <span className="footer-payment-copy d-block">{footer.cardOnlyPayment}</span>
            <span className="footer-payment-wallets d-block">{footer.walletsAccepted}</span>
          </span>
        </div>
      </div>

      <ContactModal show={isContactModalOpen} onClose={() => setIsContactModalOpen(false)} />
    </footer>
  );
}
