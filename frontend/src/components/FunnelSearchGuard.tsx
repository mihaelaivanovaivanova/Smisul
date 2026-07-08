import { Navigate, Outlet } from 'react-router-dom';
import { useSettings } from '../hooks/useSettings';

/** /search redirects to / while funnel mode is on — see App.tsx's route swap at "/". */
export default function FunnelSearchGuard() {
  const { funnelModeEnabled, isLoading } = useSettings();

  if (isLoading) {
    return null;
  }

  if (funnelModeEnabled) {
    return <Navigate to="/" replace />;
  }

  return <Outlet />;
}
