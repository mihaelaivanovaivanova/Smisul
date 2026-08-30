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
      <div className="container d-flex flex-column gap-4">
        {/* Brand. */}
        <div className="d-flex justify-content-center justify-content-md-start">
          <Logo tagline light />
        </div>

        {/* Contacts (left) · legal documents (right). */}
        <div className="row gy-4">
          {hasMerchantInfo && publicSettings && (
            <div className="col-12 col-md-6 text-center text-md-start">
              <h2 className="h6 text-uppercase small fw-bold mb-3">{footer.contactsHeading}</h2>
              <div className="d-flex flex-column gap-2 small text-muted">
                {(publicSettings.company_name_en || publicSettings.company_name) && (
                  <span>{[publicSettings.company_name_en, publicSettings.company_name].filter(Boolean).join(' / ')}</span>
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
              </div>
            </div>
          )}

          <div className="col-12 col-md-6 text-center text-md-end">
            <h2 className="h6 text-uppercase small fw-bold mb-3">{footer.legalHeading}</h2>
            <nav
              aria-label={footer.legalHeading}
              className="d-flex flex-column align-items-center align-items-md-end gap-2"
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
          </div>
        </div>

        {/* Socials (left) · payment marks (right) — same row, so the two
            icon groups sit vertically aligned with each other. */}
        <div className="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
          {socials.length > 0 ? (
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
          ) : (
            <span />
          )}
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

        {/* Copyright. */}
        <span className="text-muted small text-center text-md-start">
          &copy; {year} {siteName}
        </span>
      </div>
    </footer>
  );
}
