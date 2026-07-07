import { useEffect, useId, useState } from 'react';
import { walletEndpointUrls } from '../../api/payment';
import LoadingState from '../LoadingState';
import ErrorState from '../ErrorState';
import { checkout as checkoutCopy } from '../../content/copy';
import type { PaymentMethodValue, PaymentWalletSession } from '../../types/payment';

declare global {
  interface Window {
    ICardIpgGAPay?: new (config: Record<string, unknown>) => {
      create: () => void;
    };
  }
}

interface IcardWalletButtonsProps {
  orderId: number;
  guestAccessToken?: string | null;
  session: PaymentWalletSession;
  method: Extract<PaymentMethodValue, 'apple_pay' | 'google_pay'>;
  amount: number;
  onSuccess: () => void;
  onDecline: () => void;
}

function loadScript(src: string): Promise<void> {
  return new Promise((resolve, reject) => {
    document.getElementById('icard-wallet-sdk')?.remove();

    const script = document.createElement('script');
    script.id = 'icard-wallet-sdk';
    script.src = src;
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('iCard wallet SDK could not be loaded.'));
    document.body.appendChild(script);
  });
}

/**
 * Renders the real Apple Pay/Google Pay button via iCard's own wallet SDK
 * (ICardIpgGAPay) — the SDK itself calls tokenProviderSessionUrl (Apple's
 * merchant validation) and processPaymentUrl (the tokenized purchase) on
 * WalletPaymentController directly, so this component only ever bootstraps
 * it and relays its onSuccess/onDecline/onError callbacks.
 */
export default function IcardWalletButtons({
  orderId,
  guestAccessToken,
  session,
  method,
  amount,
  onSuccess,
  onDecline,
}: IcardWalletButtonsProps) {
  const uniqueId = useId().replace(/:/g, '');
  const containerId = `icard-${method}-${uniqueId}`;
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function setupWallet() {
      try {
        setIsLoading(true);
        setError(null);

        await loadScript(session.wallet_js_url);

        if (cancelled) return;

        if (!window.ICardIpgGAPay) {
          throw new Error(checkoutCopy.paymentStep.wallet.loadError);
        }

        const { tokenProviderSessionUrl, processPaymentUrl } = walletEndpointUrls(orderId, guestAccessToken);

        const wallet = new window.ICardIpgGAPay({
          processPaymentUrl,
          tokenProviderSessionUrl,
          mid: session.mid,
          merchantName: session.mid_name,
          amount: amount.toFixed(2),
          currencyAlpha: session.currency_alpha,
          environment: session.environment,
          merchantSessionData: JSON.stringify({ orderId }),
          ...(method === 'apple_pay'
            ? { appleConfig: { btnContainerId: containerId, btnColor: 'black', merchantDomain: session.apple_merchant_domain } }
            : { googleConfig: { btnContainerId: containerId, btnColor: 'black' } }),
          onSuccess: () => onSuccess(),
          onDecline: () => onDecline(),
          onError: () => onDecline(),
        });

        wallet.create();
      } catch (setupError) {
        if (!cancelled) {
          setError(setupError instanceof Error ? setupError.message : checkoutCopy.paymentStep.wallet.loadError);
        }
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    }

    void setupWallet();

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [orderId, guestAccessToken, session, method, amount, containerId]);

  return (
    <div>
      <p className="text-muted small">{checkoutCopy.paymentStep.wallet.securedByIcard}</p>
      {session.environment === 'sandbox' && <p className="text-muted small">{checkoutCopy.paymentStep.wallet.sandboxNotice}</p>}
      {isLoading && <LoadingState message={checkoutCopy.paymentStep.wallet.loading} />}
      <div id={containerId} />
      {error && <ErrorState message={error} />}
    </div>
  );
}
