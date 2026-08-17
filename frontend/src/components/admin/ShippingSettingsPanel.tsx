import { useEffect, useState } from 'react';
import {
  fetchShippingProviderSettings,
  updateShippingProviderSetting,
  type ShippingProviderSetting,
  type ShippingProviderSettingUpdate,
} from '../../api/admin/shippingSettings';
import { getErrorMessage } from '../../api/errors';
import LoadingState from '../LoadingState';

function ProviderForm({ provider, onChanged }: { provider: ShippingProviderSetting; onChanged: (providers: ShippingProviderSetting[]) => void }) {
  const isBoxNow = provider.provider === 'box_now';
  const [enabled, setEnabled] = useState(provider.enabled);
  const [baseUrl, setBaseUrl] = useState(provider.base_url ?? '');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [clientId, setClientId] = useState('');
  const [clientSecret, setClientSecret] = useState('');
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function save() {
    setSaving(true);
    setError(null);
    setMessage(null);
    try {
      const values: ShippingProviderSettingUpdate = { enabled, base_url: baseUrl };
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
        <button className="btn btn-primary mt-4" type="button" onClick={() => void save()} disabled={saving}>
          {saving ? 'Saving…' : `Save ${provider.label}`}
        </button>
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
          key={`${provider.provider}-${provider.username_configured}-${provider.password_configured}-${provider.client_id_configured}-${provider.client_secret_configured}`}
          provider={provider}
          onChanged={setProviders}
        />
      ))}
    </div>
  );
}
