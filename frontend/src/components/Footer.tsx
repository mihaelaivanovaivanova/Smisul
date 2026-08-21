import { useState } from 'react';
import { Link } from 'react-router-dom';
import Logo from './Logo';
import ContactModal from './ContactModal';
import Icon from './icons/Icon';
import { fetchLegalDocuments } from '../api/legal';
import { fetchPublicSettings } from '../api/settings';
import { useAsync } from '../hooks/useAsync';
import { useAuth } from '../hooks/useAuth';
import { useCookieConsent } from '../hooks/useCookieConsent';
import { useSettings } from '../hooks/useSettings';
import { footer, nav, orders as ordersCopy, siteName } from '../content/copy';

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
  const { isAuthenticated, isLoading: authLoading } = useAuth();
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
        publicSettings.company_id ??
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
            {/* The header keeps only a discreet account icon (see
                Navbar.tsx) — account/login/orders stay fully reachable
                here as plain text links. */}
            {!authLoading && (
              isAuthenticated ? (
                <>
                  <Link className="text-decoration-none text-muted small" to="/profile">
                    {nav.profile}
                  </Link>
                  <Link className="text-decoration-none text-muted small" to="/profile/orders">
                    {ordersCopy.title}
                  </Link>
                </>
              ) : (
                <Link className="text-decoration-none text-muted small" to="/login">
                  {nav.login}
                </Link>
              )
            )}
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

        {/* Legal links — a clean single column at narrow widths (a wrapping
            row here left uneven, ragged center-aligned lines whose length
            varies link to link); back to the original wrapping row from
            sm (576px) up, where there's enough width for it to read as
            intentional. row-gap-3 in the stacked column gives each link
            a comfortable tap target (row-gap-1 was fine for a wrapped
            row's occasional second line, too tight for a full stack). */}
        <nav
          aria-label={footer.legalHeading}
          className="d-flex flex-column flex-sm-row flex-wrap align-items-center justify-content-center justify-content-md-start column-gap-4 row-gap-3 row-gap-sm-1"
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
              <Icon name="hand-coins" />
            </span>
          </span>
        </div>
      </div>

      <ContactModal show={isContactModalOpen} onClose={() => setIsContactModalOpen(false)} />
    </footer>
  );
}
