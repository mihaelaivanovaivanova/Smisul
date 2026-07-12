import { useState } from 'react';
import { Link } from 'react-router-dom';
import Logo from './Logo';
import ContactModal from './ContactModal';
import { fetchLegalDocuments } from '../api/legal';
import { useAsync } from '../hooks/useAsync';
import { useCookieConsent } from '../hooks/useCookieConsent';
import { useSettings } from '../hooks/useSettings';
import { footer, nav, siteName } from '../content/copy';

export default function Footer() {
  const year = new Date().getFullYear();
  const { data: legalDocuments } = useAsync(fetchLegalDocuments, [], '');
  const { openPreferencesModal } = useCookieConsent();
  const { funnelModeEnabled } = useSettings();
  const [isContactModalOpen, setIsContactModalOpen] = useState(false);

  return (
    <footer className="footer-dark mt-auto py-5">
      <div className="container">
        <div className="d-flex flex-column flex-md-row justify-content-between gap-4">
          <div>
            <Logo tagline light />
            <p className="text-muted mt-2 mb-0" style={{ maxWidth: '24rem' }}>
              {footer.description}
            </p>
          </div>

          <nav aria-label={footer.companyHeading}>
            <h2 className="h6">{footer.companyHeading}</h2>
            <ul className="list-unstyled d-flex flex-column gap-1">
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

          <nav aria-label={footer.legalHeading}>
            <h2 className="h6">{footer.legalHeading}</h2>
            <ul className="list-unstyled d-flex flex-column gap-1">
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
        <hr className="my-4" style={{ borderColor: 'rgba(255, 255, 255, 0.18)' }} />
        <div className="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 text-center text-sm-start">
          <span className="text-muted small">{footer.tagline}</span>
          <span className="text-muted small">
            &copy; {year} {siteName}. Всички права запазени.
          </span>
        </div>
      </div>

      <ContactModal show={isContactModalOpen} onClose={() => setIsContactModalOpen(false)} />
    </footer>
  );
}
