import { useState } from 'react';
import { fetchBroadcastRecipientCount, sendBroadcast, type SendBroadcastResult } from '../../api/admin/broadcasts';
import { getErrorMessage } from '../../api/errors';
import { useAsync } from '../../hooks/useAsync';
import ConfirmModal from '../../components/admin/ConfirmModal';

/**
 * The weekly "write it, hit send" promo screen - plain subject/body/
 * optional link, sent to everyone BroadcastService::recipients() currently
 * resolves (opted-in customers + funnel leads, minus anyone who's
 * unsubscribed/bounced/complained). No draft/schedule/history - this is
 * the one-button flow that was actually asked for, not a campaign manager.
 */
export default function BroadcastPage() {
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [ctaUrl, setCtaUrl] = useState('');
  const [ctaLabel, setCtaLabel] = useState('');
  const [showConfirm, setShowConfirm] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [sendError, setSendError] = useState<string | null>(null);
  const [result, setResult] = useState<SendBroadcastResult | null>(null);

  const { data: recipientCount, isLoading: isCountLoading, error: countError } = useAsync(
    fetchBroadcastRecipientCount,
    [],
    'Could not load the recipient count.',
  );

  const canSend = subject.trim() !== '' && body.trim() !== '';

  async function handleSend() {
    setIsSending(true);
    setSendError(null);

    try {
      const sendResult = await sendBroadcast({
        subject: subject.trim(),
        body: body.trim(),
        cta_url: ctaUrl.trim() || undefined,
        cta_label: ctaLabel.trim() || undefined,
      });
      setResult(sendResult);
      setShowConfirm(false);
    } catch (err) {
      setSendError(getErrorMessage(err, 'Could not send the broadcast.'));
    } finally {
      setIsSending(false);
    }
  }

  return (
    <div>
      <div className="mb-4">
        <h1 className="h3 mb-0">Broadcast</h1>
        <p className="text-muted mb-0">
          Sends to everyone who has opted into marketing - consented customers plus funnel leads, minus anyone who
          has unsubscribed. {!isCountLoading && !countError && recipientCount !== null && (
            <strong>{recipientCount} recipient{recipientCount === 1 ? '' : 's'} right now.</strong>
          )}
        </p>
      </div>

      {countError && <div className="alert alert-warning">{countError}</div>}
      {sendError && <div className="alert alert-danger">{sendError}</div>}

      {result && (
        <div className="alert alert-success">
          Sent to {result.sent} of {result.total} recipients.
          {result.failed > 0 && ` ${result.failed} failed - see the logs.`}
        </div>
      )}

      <div className="row">
        <div className="col-lg-8">
          <div className="mb-3">
            <label htmlFor="broadcast-subject" className="form-label">
              Subject
            </label>
            <input
              id="broadcast-subject"
              type="text"
              className="form-control"
              value={subject}
              onChange={(event) => setSubject(event.target.value)}
              maxLength={255}
            />
          </div>

          <div className="mb-3">
            <label htmlFor="broadcast-body" className="form-label">
              Message
            </label>
            <textarea
              id="broadcast-body"
              className="form-control"
              rows={10}
              value={body}
              onChange={(event) => setBody(event.target.value)}
              maxLength={5000}
              placeholder={'Plain text - separate paragraphs with a blank line.'}
            />
          </div>

          <div className="row g-2 mb-4">
            <div className="col-sm-8">
              <label htmlFor="broadcast-cta-url" className="form-label">
                Link (optional)
              </label>
              <input
                id="broadcast-cta-url"
                type="url"
                className="form-control"
                value={ctaUrl}
                onChange={(event) => setCtaUrl(event.target.value)}
                placeholder="https://smisul.bg/products/..."
              />
            </div>
            <div className="col-sm-4">
              <label htmlFor="broadcast-cta-label" className="form-label">
                Button text
              </label>
              <input
                id="broadcast-cta-label"
                type="text"
                className="form-control"
                value={ctaLabel}
                onChange={(event) => setCtaLabel(event.target.value)}
                placeholder="Разгледай →"
                maxLength={60}
              />
            </div>
          </div>

          <button
            type="button"
            className="btn btn-primary"
            disabled={!canSend || isCountLoading}
            onClick={() => setShowConfirm(true)}
          >
            Send broadcast
          </button>
        </div>
      </div>

      <ConfirmModal
        show={showConfirm}
        title="Send broadcast"
        message={`Send this to ${recipientCount ?? 'all opted-in'} recipient(s)? This cannot be undone.`}
        confirmLabel="Send"
        isLoading={isSending}
        onConfirm={() => void handleSend()}
        onCancel={() => setShowConfirm(false)}
      />
    </div>
  );
}
