import { useEffect, useState } from 'react';
import {
  activateICardProfile,
  fetchICardProfiles,
  updateICardProfile,
  type ICardProfile,
  type ICardProfileUpdate,
} from '../../api/admin/icard';
import { getErrorMessage } from '../../api/errors';
import LoadingState from '../LoadingState';

const textFields: { key: keyof ICardProfileUpdate; label: string; type?: string }[] = [
  { key: 'mid', label: 'MID (15 characters)' },
  { key: 'mid_name', label: 'Merchant display name' },
  { key: 'originator', label: 'Originator' },
  { key: 'key_index', label: 'Key index' },
  { key: 'key_index_resp', label: 'Response key index' },
  { key: 'currency_numeric', label: 'Currency numeric code' },
  { key: 'base_url', label: 'IPG API URL', type: 'url' },
  { key: 'modal_js_url', label: 'Modal JavaScript URL', type: 'url' },
  { key: 'wallet_js_url', label: 'Apple/Google Pay SDK URL', type: 'url' },
  { key: 'webhook_url', label: 'Callback URL', type: 'url' },
  { key: 'apple_merchant_id', label: 'Apple merchant ID' },
  { key: 'apple_merchant_domain', label: 'Apple verified domain' },
  { key: 'google_merchant_id', label: 'Google merchant ID' },
];

function ProfileForm({ profile, onChanged }: { profile: ICardProfile; onChanged: (profiles: ICardProfile[]) => void }) {
  const [values, setValues] = useState<ICardProfileUpdate>({
    enabled: profile.enabled,
    mid: profile.mid,
    mid_name: profile.mid_name,
    originator: profile.originator,
    key_index: profile.key_index,
    key_index_resp: profile.key_index_resp,
    ipg_version: profile.ipg_version,
    currency_numeric: profile.currency_numeric,
    base_url: profile.base_url,
    modal_js_url: profile.modal_js_url,
    wallet_js_url: profile.wallet_js_url,
    webhook_url: profile.webhook_url,
    callback_ips: profile.callback_ips ?? [],
    apple_pay_enabled: profile.apple_pay_enabled,
    google_pay_enabled: profile.google_pay_enabled,
    apple_merchant_id: profile.apple_merchant_id,
    apple_merchant_domain: profile.apple_merchant_domain,
    google_merchant_id: profile.google_merchant_id,
    google_environment: profile.google_environment,
  });
  const [privateKey, setPrivateKey] = useState('');
  const [publicKey, setPublicKey] = useState('');
  const [callbackIps, setCallbackIps] = useState((profile.callback_ips ?? []).join('\n'));
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function save() {
    setSaving(true); setError(null); setMessage(null);
    try {
      const profiles = await updateICardProfile(profile.environment, {
        ...values,
        callback_ips: callbackIps.split(/[,\n]/).map((value) => value.trim()).filter(Boolean),
        ...(privateKey ? { private_key: privateKey } : {}),
        ...(publicKey ? { public_key: publicKey } : {}),
      });
      setPrivateKey(''); setPublicKey(''); setMessage('iCard configuration saved.'); onChanged(profiles);
    } catch (err) { setError(getErrorMessage(err, 'Could not save iCard configuration.')); }
    finally { setSaving(false); }
  }

  async function activate() {
    setSaving(true); setError(null);
    try { onChanged(await activateICardProfile(profile.environment)); }
    catch (err) { setError(getErrorMessage(err, 'Could not activate environment.')); }
    finally { setSaving(false); }
  }

  return (
    <div className="card">
      <div className="card-header d-flex justify-content-between align-items-center">
        <strong>iCard {profile.environment === 'production' ? 'Production' : 'Sandbox'}</strong>
        {profile.is_active ? <span className="badge text-bg-success">Active</span> : <button className="btn btn-sm btn-outline-primary" onClick={() => void activate()} disabled={saving}>Make active</button>}
      </div>
      <div className="card-body">
        {error && <div className="alert alert-danger">{error}</div>}
        {message && <div className="alert alert-success">{message}</div>}
        <div className="form-check form-switch mb-3"><input className="form-check-input" type="checkbox" checked={values.enabled} onChange={(e) => setValues({ ...values, enabled: e.target.checked })} /><label className="form-check-label">Enable iCard payments</label></div>
        <div className="row g-3">
          {textFields.map(({ key, label, type }) => (
            <div className="col-md-6" key={key}>
              <label className="form-label">{label}</label>
              <input className="form-control" type={type ?? 'text'} value={String(values[key] ?? '')} onChange={(e) => setValues({ ...values, [key]: e.target.value })} />
            </div>
          ))}
          <div className="col-md-6"><label className="form-label">Allowed callback IPs (one per line)</label><textarea className="form-control" rows={4} value={callbackIps} onChange={(e) => setCallbackIps(e.target.value)} /></div>
          <div className="col-md-6"><label className="form-label">Merchant private key PEM</label><textarea className="form-control font-monospace" rows={4} value={privateKey} onChange={(e) => setPrivateKey(e.target.value)} placeholder={profile.private_key_configured ? 'Configured — leave blank to keep current key' : 'Paste private key'} /></div>
          <div className="col-md-6"><label className="form-label">iCard public key PEM</label><textarea className="form-control font-monospace" rows={4} value={publicKey} onChange={(e) => setPublicKey(e.target.value)} placeholder={profile.public_key_configured ? 'Configured — leave blank to keep current key' : 'Paste iCard public key'} /></div>
          <div className="col-md-6"><label className="form-label">Google Pay environment</label><select className="form-select" value={values.google_environment} onChange={(e) => setValues({ ...values, google_environment: e.target.value as 'TEST' | 'PRODUCTION' })}><option value="TEST">TEST</option><option value="PRODUCTION">PRODUCTION</option></select></div>
        </div>
        <div className="alert alert-secondary mt-3 mb-0">
          Apple Pay и Google Pay се управляват от iCard и се показват вътре в платежния modal, когато са активни за MID-а.
        </div>
        <button className="btn btn-primary mt-4" type="button" onClick={() => void save()} disabled={saving}>{saving ? 'Saving…' : `Save ${profile.environment}`}</button>
      </div>
    </div>
  );
}

export default function ICardSettingsPanel() {
  const [profiles, setProfiles] = useState<ICardProfile[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => { fetchICardProfiles().then(setProfiles).catch((err) => setError(getErrorMessage(err, 'Could not load iCard settings.'))); }, []);
  if (error) return <div className="alert alert-danger">{error}</div>;
  if (!profiles) return <LoadingState message="Loading iCard settings…" />;
  return <div className="d-flex flex-column gap-4"><div className="alert alert-info mb-0">Private/public keys are encrypted in the database and never returned to the browser. Leave key fields blank to keep the current values.</div>{profiles.map((profile) => <ProfileForm key={`${profile.environment}-${profile.is_active}-${profile.mid}-${profile.private_key_configured}`} profile={profile} onChanged={setProfiles} />)}</div>;
}
