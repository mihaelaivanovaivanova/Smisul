import { Link } from 'react-router-dom';
import Logo from './Logo';
import Icon from './icons/Icon';
import { fetchLegalDocuments } from '../api/legal';
import { fetchPublicSettings } from '../api/settings';
import { useAsync } from '../hooks/useAsync';
import { useCookieConsent } from '../hooks/useCookieConsent';
import { footer, siteName } from '../content/copy';

/**
 * Deliberately flat and horizontal: one brand/legal/socials row, one
 * bottom line — instead of tall stacked columns. The legal links sit
 * to the right of the logo, bottom-aligned with it. The merchant-identity
 * line and the social icons appear only once the admin fills them in
 * (Settings → General).
 */
export default function Footer() {
  const year = new Date().getFullYear();
  const { data: legalDocuments } = useAsync(fetchLegalDocuments, [], '');
  const { data: publicSettings } = useAsync(fetchPublicSettings, [], '');
  const { openPreferencesModal } = useCookieConsent();

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
        publicSettings.company_id ??
        publicSettings.store_email,
    );

  return (
    <footer className="footer-dark mt-auto py-4">
      <div className="container d-flex flex-column gap-3">
        {/* Brand · legal links · socials — one row, legal + socials
            bottom-aligned with the logo on md+ (align-items-end); centered
            and stacked on narrow screens where there's no "bottom" to
            align to. */}
        <div className="d-flex flex-column flex-md-row justify-content-center justify-content-md-start align-items-center align-items-md-end gap-3 gap-md-5">
          <Logo tagline light />

          <div className="d-flex flex-column align-items-center align-items-md-end gap-2 mt-md-3">
            <nav
              aria-label={footer.legalHeading}
              className="d-flex flex-column flex-md-row flex-md-wrap align-items-center justify-content-center justify-content-md-end column-gap-4 row-gap-3 row-gap-md-1"
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
        </div>

        {/* Merchant identity — one muted line, only when configured. */}
        {hasMerchantInfo && publicSettings && (
          <p className="d-flex flex-wrap justify-content-center justify-content-md-start column-gap-4 row-gap-1 small text-muted mb-0">
            {(publicSettings.company_name_en || publicSettings.company_name) && (
              <span>
                {footer.companyLabel}:{' '}
                {[publicSettings.company_name_en, publicSettings.company_name].filter(Boolean).join(' / ')}
              </span>
            )}
            {publicSettings.company_id && (
              <span>
                {footer.companyIdLabel}: {publicSettings.company_id}
              </span>
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
            {/* Monochrome simple-icons marks (Icon.tsx), not the real payment
                logos + grayscale-filter treatment DeliveryPaymentReturnsSection.tsx
                uses on the funnel page — those brand logos read wrong under a
                filter (Mastercard's two circles wash to the same near-white
                and lose the overlap, etc.); these are vector glyphs designed
                to be a single flat color from the start. */}
            <span className="footer-payment-logos d-inline-flex align-items-center gap-3" aria-label="Приемани карти и начини на плащане">
              <Icon name="visa" />
              <Icon name="mastercard" />
              <Icon name="amex" />
              <Icon name="apple-pay" />
              <Icon name="google-pay" />
            </span>
          </span>
        </div>
      </div>
    </footer>
  );
}
