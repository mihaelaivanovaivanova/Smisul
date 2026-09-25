import { useLayoutEffect } from 'react';
import { Routes, Route, useLocation } from 'react-router-dom';
import PublicLayout from './components/layout/PublicLayout';
import ProtectedRoute from './components/ProtectedRoute';
import GuestRoute from './components/GuestRoute';
import FunnelSearchGuard from './components/FunnelSearchGuard';
import AdminRoute from './components/admin/AdminRoute';
import AdminLayout from './components/admin/AdminLayout';
import HomePage from './pages/HomePage';
import FunnelLandingPage from './pages/FunnelLandingPage';
import { useSettings } from './hooks/useSettings';
import ProductPage from './pages/ProductPage';
import MiswakLandingPage from './pages/MiswakLandingPage';
import CategoryPage from './pages/CategoryPage';
import SearchPage from './pages/SearchPage';
import CartPage from './pages/CartPage';
import CheckoutPage from './pages/CheckoutPage';
import OrderConfirmationPage from './pages/OrderConfirmationPage';
import OrderTrackingPage from './pages/OrderTrackingPage';
import NotFoundPage from './pages/NotFoundPage';
import RegisterPage from './pages/RegisterPage';
import LoginPage from './pages/LoginPage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import VerifyEmailPage from './pages/VerifyEmailPage';
import ProfilePage from './pages/ProfilePage';
import OrdersPage from './pages/OrdersPage';
import FavoritesPage from './pages/FavoritesPage';
import ReviewsPage from './pages/ReviewsPage';
import LegalPage from './pages/LegalPage';
import AboutPage from './pages/AboutPage';
import DashboardPage from './pages/admin/DashboardPage';
import ProductsPage from './pages/admin/ProductsPage';
import CategoriesPage from './pages/admin/CategoriesPage';
import PromotionsPage from './pages/admin/PromotionsPage';
import ContentPage from './pages/admin/ContentPage';
import AdminOrdersPage from './pages/admin/AdminOrdersPage';
import AdminReviewsPage from './pages/admin/AdminReviewsPage';
import OrderDetailPage from './pages/admin/OrderDetailPage';
import CustomersPage from './pages/admin/CustomersPage';
import CustomerDetailPage from './pages/admin/CustomerDetailPage';
import MediaLibraryPage from './pages/admin/MediaLibraryPage';
import SettingsPage from './pages/admin/SettingsPage';
import LogsPage from './pages/admin/LogsPage';
import ComplaintsPage from './pages/admin/ComplaintsPage';
import FunnelPage from './pages/admin/FunnelPage';
import LeadsPage from './pages/admin/LeadsPage';

function ScrollToTop() {
  const { pathname, search, hash } = useLocation();

  useLayoutEffect(() => {
    // Hash links intentionally manage their own target position (for
    // example the FAQ links on the funnel landing page).
    if (hash) {
      return;
    }

    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });

    // On mobile, navigating away mid-fling (e.g. tapping "Add to cart" right
    // after a scroll gesture) leaves the browser's momentum scroll still
    // settling on its own compositor thread — a plain scrollTo (even a
    // follow-up one queued on the next frame) loses that race, since the
    // fling keeps nudging the position for several hundred ms independent
    // of any JS timing. The only thing that reliably stops it is making the
    // page briefly unscrollable, which the compositor respects immediately;
    // scrollTo(0) is re-applied once more right before unlocking in case the
    // fling was still in flight when the lock engaged.
    const { documentElement, body } = document;
    const previousHtmlOverflow = documentElement.style.overflow;
    const previousBodyOverflow = body.style.overflow;
    documentElement.style.overflow = 'hidden';
    body.style.overflow = 'hidden';

    const timeout = window.setTimeout(() => {
      window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
      documentElement.style.overflow = previousHtmlOverflow;
      body.style.overflow = previousBodyOverflow;
    }, 150);

    return () => {
      window.clearTimeout(timeout);
      documentElement.style.overflow = previousHtmlOverflow;
      body.style.overflow = previousBodyOverflow;
    };
  }, [pathname, search, hash]);

  return null;
}

export default function App() {
  const { funnelModeEnabled } = useSettings();

  return (
    <>
      <ScrollToTop />
      <Routes>
      <Route element={<PublicLayout />}>
        <Route path="/" element={funnelModeEnabled ? <FunnelLandingPage /> : <HomePage />} />
        <Route path="/products/:slug" element={<ProductPage />} />
        {/* Unlisted review link for the in-progress Juun.bg-structured
            redesign — nothing on the site links here, so it stays out of
            the cart/order-confirmation/favorites flows that point at
            /products/miswak until the redesign is ready to launch there. */}
        <Route path="/preview/miswak" element={<MiswakLandingPage />} />
        <Route path="/categories/:slug" element={<CategoryPage />} />
        <Route element={<FunnelSearchGuard />}>
          <Route path="/search" element={<SearchPage />} />
        </Route>
        <Route path="/cart" element={<CartPage />} />
        <Route path="/checkout" element={<CheckoutPage />} />
        <Route path="/order-confirmation/:orderId" element={<OrderConfirmationPage />} />
        <Route path="/orders/:orderId/tracking" element={<OrderTrackingPage />} />
        <Route path="/legal/:slug" element={<LegalPage />} />
        <Route path="/about" element={<AboutPage />} />

        <Route element={<GuestRoute />}>
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        </Route>

        <Route path="/reset-password" element={<ResetPasswordPage />} />
        <Route path="/verify-email/:id/:hash" element={<VerifyEmailPage />} />

        <Route element={<ProtectedRoute />}>
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="/profile/orders" element={<OrdersPage />} />
          <Route path="/profile/favorites" element={<FavoritesPage />} />
          <Route path="/profile/reviews" element={<ReviewsPage />} />
        </Route>

        <Route path="*" element={<NotFoundPage />} />
      </Route>

      <Route element={<AdminRoute />}>
        <Route path="/admin" element={<AdminLayout />}>
          <Route index element={<DashboardPage />} />
          <Route path="products" element={<ProductsPage />} />
          <Route path="categories" element={<CategoriesPage />} />
          <Route path="promotions" element={<PromotionsPage />} />
          <Route path="content" element={<ContentPage />} />
          <Route path="funnel" element={<FunnelPage />} />
          <Route path="leads" element={<LeadsPage />} />
          <Route path="orders" element={<AdminOrdersPage />} />
          <Route path="reviews" element={<AdminReviewsPage />} />
          <Route path="orders/:orderId" element={<OrderDetailPage />} />
          <Route path="customers" element={<CustomersPage />} />
          <Route path="customers/:customerId" element={<CustomerDetailPage />} />
          <Route path="media" element={<MediaLibraryPage />} />
          <Route path="settings" element={<SettingsPage />} />
          <Route path="complaints" element={<ComplaintsPage />} />
          <Route path="logs" element={<LogsPage />} />
        </Route>
      </Route>
      </Routes>
    </>
  );
}
