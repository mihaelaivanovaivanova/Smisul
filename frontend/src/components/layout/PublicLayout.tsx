import { Outlet } from 'react-router-dom';
import Navbar from '../Navbar';
import Footer from '../Footer';
import CookieBanner from '../CookieBanner';
import CookiePreferencesModal from '../CookiePreferencesModal';
import AnalyticsLoader from '../AnalyticsLoader';
import LegalDocumentUpdateModal from '../LegalDocumentUpdateModal';
import TopAnnouncementBar from '../TopAnnouncementBar';

export default function PublicLayout() {
  return (
    <div className="d-flex flex-column min-vh-100">
      <LegalDocumentUpdateModal />
      <TopAnnouncementBar />
      <Navbar />
      <main className="flex-grow-1">
        <Outlet />
      </main>
      <Footer />
      <CookieBanner />
      <CookiePreferencesModal />
      <AnalyticsLoader />
    </div>
  );
}
