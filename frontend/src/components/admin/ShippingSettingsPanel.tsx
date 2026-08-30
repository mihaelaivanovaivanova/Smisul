import { useEffect, useState } from 'react';
import {
  fetchShippingProviderSettings,
  updateShippingProviderSetting,
  type ShippingProviderSetting,
  type ShippingProviderSettingUpdate,
} from '../../api/admin/shippingSettings';
import { fetchSettings, updateSettings } from '../../api/admin/settings';
import { getErrorMessage } from '../../api/errors';
import LoadingState from '../LoadingState';

/**
 * Storefront-wide "Box Now marketing chrome" toggles — the top announcement
 * banner (TopAnnouncementBar.tsx, every page) and the funnel page's
 * floating badge (BoxNowBadge.tsx). These live in the generic Setting
 * table (group "general", same system as the General tab — see
 * SettingsSeeder.php) rather than on the box_now ShippingProviderSetting
 * row itself, since that row's `enabled` flag means "API credentials are
 * live" (gates real BOX NOW shipments — see ShippingProviderSettingsService),
 * a different concern from "show this marketing copy." Rendered here,
 * inside the Box Now card, purely as a UI placement choice; SettingsPage.tsx
 * filters these same keys out of the generic General tab list so they
 * don't show up twice.
 */
const BOX_NOW_BANNER_ENABLED_KEY = 'general.box_now_banner_enabled';
const BOX_NOW_BADGE_ENABLED_KEY = 'general.box_now_badge_enabled';
const BOX_NOW_BANNER_MESSAGE_KEY = 'general.box_now_banner_message';

function BoxNowMarketingFields() {
  const [bannerEnabled, setBannerEnabled] = useState(true);
  const [badgeEnabled, setBadgeEnabled] = useState(true);
  const [bannerMessage, setBannerMessage] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSettings()
      .then((data) => {
        const items = data.editable.general ?? [];
        const findValue = (key: string) => items.find((item) => item.key === key)?.value;
        setBannerEnabled(Boolean(findValue(BOX_NOW_BANNER_ENABLED_KEY) ?? true));
        setBadgeEnabled(Boolean(findValue(BOX_NOW_BADGE_ENABLED_KEY) ?? true));
        setBannerMessage(String(findValue(BOX_NOW_BANNER_MESSAGE_KEY) ?? ''));
      })
      .catch((err) => setError(getErrorMessage(err, 'Could not load Box Now marketing settings.')))
      .finally(() => setIsLoading(false));
  }, []);

  async function save() {
    setSaving(true);
    setError(null);
    setMessage(null);
    try {
      await updateSettings({
        [BOX_NOW_BANNER_ENABLED_KEY]: bannerEnabled,
        [BOX_NOW_BADGE_ENABLED_KEY]: badgeEnabled,
        [BOX_NOW_BANNER_MESSAGE_KEY]: bannerMessage,
      });
      setMessage('Box Now marketing settings saved.');
    } catch (err) {
      setError(getErrorMessage(err, 'Could not save Box Now marketing settings.'));
    } finally {
      setSaving(false);
    }
  }

  if (isLoading) {
    return <LoadingState message="Loading Box Now marketing settings…" />;
  }

  return (
    <>
      <hr className="my-4" />
      <h6>Box Now marketing</h6>
      <p className="text-body-secondary small">
        Controls the storefront's top announcement banner (every page) and the funnel page's floating Box Now badge.
      </p>
      {error && <div className="alert alert-danger">{error}</div>}
      {message && <div className="alert alert-success">{message}</div>}
      <div className="form-check form-switch mb-2">
        <input
          className="form-check-input"
          type="checkbox"
          id="box-now-banner-enabled"
          checked={bannerEnabled}
          onChange={(event) => setBannerEnabled(event.target.checked)}
        />
        <label className="form-check-label" htmlFor="box-now-banner-enabled">
          Show top banner
        </label>
      </div>
      <div className="form-check form-switch mb-3">
        <input
          className="form-check-input"
          type="checkbox"
          id="box-now-badge-enabled"
          checked={badgeEnabled}
          onChange={(event) => setBadgeEnabled(event.target.checked)}
        />
        <label className="form-check-label" htmlFor="box-now-badge-enabled">
          Show floating badge
        </label>
      </div>
      <div className="mb-3">
        <label className="form-label" htmlFor="box-now-banner-message">
          Top banner message
        </label>
        <input
          className="form-control"
          id="box-now-banner-message"
          value={bannerMessage}
          onChange={(event) => setBannerMessage(event.target.value)}
        />
      </div>
      <button className="btn btn-primary" type="button" onClick={() => void save()} disabled={saving}>
        {saving ? 'Saving…' : 'Save Box Now marketing'}
      </button>
    </>
  );
}

/** '' means "no override — use the hardcoded default", distinct from 0 (a genuine free-shipping price). */
function priceFieldState(value: number | null): string {
  return value === null ? '' : String(value);
}

function parsePriceField(value: string): number | null {
  return value === '' ? null : Number(value);
}

function ProviderForm({ provider, onChanged }: { provider: ShippingProviderSetting; onChanged: (providers: ShippingProviderSetting[]) => void }) {
  const isBoxNow = provider.provider === 'box_now';
  const [enabled, setEnabled] = useState(provider.enabled);
  const [baseUrl, setBaseUrl] = useState(provider.base_url ?? '');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [clientId, setClientId] = useState('');
  const [clientSecret, setClientSecret] = useState('');
  const [priceOffice, setPriceOffice] = useState(priceFieldState(provider.price_office));
  const [priceLocker, setPriceLocker] = useState(priceFieldState(provider.price_locker));
  const [priceAddress, setPriceAddress] = useState(priceFieldState(provider.price_address));
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function save() {
    setSaving(true);
    setError(null);
    setMessage(null);
    try {
      const values: ShippingProviderSettingUpdate = {
        enabled,
        base_url: baseUrl,
        price_office: parsePriceField(priceOffice),
        price_locker: parsePriceField(priceLocker),
        price_address: parsePriceField(priceAddress),
      };
      if (isBoxNow) {
        if (clientId) values.client_id = clientId;
        if (clientSecret) values.client_secret = clientSecret;
      } else {
        if (username) values.username = username;
        if (password) values.password = password;
      }

      const providers = await updateShippingProviderSetting(provider.provider, values);
      setUsername('');
      setPassword('');
      setClientId('');
      setClientSecret('');
      setMessage(`${provider.label} configuration saved.`);
      onChanged(providers);
    } catch (err) {
      setError(getErrorMessage(err, `Could not save ${provider.label} configuration.`));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="card">
      <div className="card-header d-flex justify-content-between align-items-center">
        <strong>{provider.label}</strong>
        <span className={`badge text-bg-${provider.configured ? 'success' : 'secondary'}`}>
          {provider.configured ? 'Configured' : 'Not configured'}
        </span>
      </div>
      <div className="card-body">
        {error && <div className="alert alert-danger">{error}</div>}
        {message && <div className="alert alert-success">{message}</div>}
        <div className="form-check form-switch mb-3">
          <input
            className="form-check-input"
            type="checkbox"
            id={`${provider.provider}-enabled`}
            checked={enabled}
            onChange={(event) => setEnabled(event.target.checked)}
          />
          <label className="form-check-label" htmlFor={`${provider.provider}-enabled`}>
            Enable {provider.label}
          </label>
        </div>
        <div className="row g-3">
          <div className="col-md-6">
            <label className="form-label">API base URL</label>
            <input className="form-control" type="url" value={baseUrl} onChange={(event) => setBaseUrl(event.target.value)} />
          </div>
          {isBoxNow ? (
            <>
              <div className="col-md-6">
                <label className="form-label">Client ID</label>
                <input
                  className="form-control"
                  value={clientId}
                  onChange={(event) => setClientId(event.target.value)}
                  placeholder={provider.client_id_configured ? 'Configured — leave blank to keep current value' : 'Client ID'}
                />
              </div>
              <div className="col-md-6">
                <label className="form-label">Client secret</label>
                <input
                  className="form-control"
                  type="password"
                  value={clientSecret}
                  onChange={(event) => setClientSecret(event.target.value)}
                  placeholder={provider.client_secret_configured ? 'Configured — leave blank to keep current value' : 'Client secret'}
                />
              </div>
            </>
          ) : (
            <>
              <div className="col-md-6">
                <label className="form-label">Username</label>
                <input
                  className="form-control"
                  value={username}
                  onChange={(event) => setUsername(event.target.value)}
                  placeholder={provider.username_configured ? 'Configured — leave blank to keep current value' : 'Username'}
                />
              </div>
              <div className="col-md-6">
                <label className="form-label">Password</label>
                <input
                  className="form-control"
                  type="password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  placeholder={provider.password_configured ? 'Configured — leave blank to keep current value' : 'Password'}
                />
              </div>
            </>
          )}
        </div>

        <hr className="my-4" />
        <h6>Delivery prices</h6>
        <p className="text-body-secondary small">Leave a field blank to use the built-in default price for that delivery type.</p>
        <div className="row g-3">
          {!isBoxNow && (
            <div className="col-md-4">
              <label className="form-label">Price to office (EUR)</label>
              <input
                className="form-control"
                type="number"
                min="0"
                step="0.01"
                value={priceOffice}
                onChange={(event) => setPriceOffice(event.target.value)}
              />
            </div>
          )}
          <div className="col-md-4">
            <label className="form-label">Price to locker/automat (EUR)</label>
            <input
              className="form-control"
              type="number"
              min="0"
              step="0.01"
              value={priceLocker}
              onChange={(event) => setPriceLocker(event.target.value)}
            />
          </div>
          {!isBoxNow && (
            <div className="col-md-4">
              <label className="form-label">Price to address (EUR)</label>
              <input
                className="form-control"
                type="number"
                min="0"
                step="0.01"
                value={priceAddress}
                onChange={(event) => setPriceAddress(event.target.value)}
              />
            </div>
          )}
        </div>

        <button className="btn btn-primary mt-4" type="button" onClick={() => void save()} disabled={saving}>
          {saving ? 'Saving…' : `Save ${provider.label}`}
        </button>

        {isBoxNow && <BoxNowMarketingFields />}
      </div>
    </div>
  );
}

export default function ShippingSettingsPanel() {
  const [providers, setProviders] = useState<ShippingProviderSetting[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchShippingProviderSettings()
      .then(setProviders)
      .catch((err) => setError(getErrorMessage(err, 'Could not load shipping settings.')));
  }, []);

  if (error) return <div className="alert alert-danger">{error}</div>;
  if (!providers) return <LoadingState message="Loading shipping settings…" />;

  return (
    <div className="d-flex flex-column gap-4">
      <div className="alert alert-info mb-0">Passwords and client secrets are encrypted in the database and never returned to the browser. Leave those fields blank to keep the current values.</div>
      {providers.map((provider) => (
        <ProviderForm
          key={`${provider.provider}-${provider.username_configured}-${provider.password_configured}-${provider.client_id_configured}-${provider.client_secret_configured}-${provider.price_office}-${provider.price_locker}-${provider.price_address}`}
          provider={provider}
          onChanged={setProviders}
        />
      ))}
    </div>
  );
}
