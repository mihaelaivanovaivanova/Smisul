import { useEffect, useState } from 'react';
import Icon from '../icons/Icon';
import { funnelAssurance } from '../../content/copy';

/**
 * Honest same-day-dispatch urgency: "Поръчай до 14:00 ч. — изпращаме още
 * днес (остават 2 ч 05 мин)". Renders only on weekdays while the visitor's
 * local clock is before the admin-configured cutoff (Settings → General;
 * empty disables it everywhere) — after the cutoff or on weekends it
 * simply disappears rather than showing a promise that can't be kept.
 * Visitors are BG-local, so the client clock is close enough to warehouse
 * time.
 */
export default function DispatchPromise({ cutoff, className }: { cutoff: string | null | undefined; className?: string }) {
  const [now, setNow] = useState(() => new Date());

  // Tick every 30s so the remaining-time text stays honest while the tab
  // is open (and the line vanishes on its own when the cutoff passes).
  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), 30_000);
    return () => clearInterval(timer);
  }, []);

  const match = cutoff?.match(/^(\d{1,2}):(\d{2})$/);

  if (!match) {
    return null;
  }

  const day = now.getDay();
  const isWeekday = day >= 1 && day <= 5;

  const cutoffDate = new Date(now);
  cutoffDate.setHours(Number(match[1]), Number(match[2]), 0, 0);
  const remainingMs = cutoffDate.getTime() - now.getTime();

  if (!isWeekday || remainingMs <= 0) {
    return null;
  }

  const totalMinutes = Math.floor(remainingMs / 60_000);
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  const remaining = hours > 0 ? `${hours} ч ${String(minutes).padStart(2, '0')} мин` : `${minutes} мин`;

  return (
    <span className={`funnel-dispatch ${className ?? ''}`}>
      <Icon name="clock" /> {funnelAssurance.dispatch(`${match[1]}:${match[2]}`, remaining)}
    </span>
  );
}
