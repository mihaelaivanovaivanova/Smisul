import { Outlet } from 'react-router-dom';
import Navbar from '../Navbar';
import Footer from '../Footer';
import CookieBanner from '../CookieBanner';
import CookiePreferencesModal from '../CookiePreferencesModal';

export default function PublicLayout() {
  return (
    <div className="d-flex flex-column min-vh-100">
      <Navbar />
      <main className="flex-grow-1">
        <Outlet />
      </main>
      <Footer />
      <CookieBanner />
      <CookiePreferencesModal />
    </div>
  );
}
