<?php

namespace App\Services;

use App\DataTransferObjects\Shipping\SettlementData;
use App\Services\Shipping\ShippingProviderSettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bulgaria's full settlement nomenclature (towns, cities, and villages —
 * ~5,300 of them) for the "Населено място" picker shown once "до адрес"
 * delivery is selected at checkout. Sourced from Speedy's real, confirmed
 * `location/site/csv/{countryId}` export (see SpeedyShippingProvider's own
 * docblock for how those credentials were verified) — the only carrier
 * integration here whose credentials expose the country's complete address
 * nomenclature rather than just that carrier's own office locations. This
 * is address reference data, not carrier data, so it's kept independent of
 * which carrier ends up actually delivering the order.
 */
class BulgarianSettlementService
{
    private const CACHE_KEY = 'bulgarian_settlements';

    // Settlement nomenclature is effectively static — cached for a week
    // rather than fetched (a ~700KB CSV of ~5,300 rows) on every request.
    private const CACHE_TTL_SECONDS = 60 * 60 * 24 * 7;

    public function __construct(private readonly ShippingProviderSettingsService $settings) {}

    /**
     * @return list<SettlementData>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            try {
                return $this->fetch();
            } catch (ConnectionException|RequestException $exception) {
                Log::warning('Bulgarian settlement list fetch failed', ['error' => $exception->getMessage()]);

                return [];
            } catch (Throwable $exception) {
                // The CSV shape isn't guaranteed to stay exactly as
                // observed during development — degrade to an empty list
                // rather than a 500 if a column is ever missing/reordered.
                Log::warning('Bulgarian settlement list had an unexpected shape', ['error' => $exception->getMessage()]);

                return [];
            }
        });
    }

    /**
     * @return list<SettlementData>
     */
    private function fetch(): array
    {
        $credentials = $this->settings->credentialsFor('speedy');

        $response = Http::baseUrl((string) ($credentials['base_url'] ?? ''))
            ->timeout(30)
            ->post('location/site/csv/100', [
                'userName' => (string) ($credentials['username'] ?? ''),
                'password' => (string) ($credentials['password'] ?? ''),
                'language' => 'BG',
            ]);

        if (! $response->successful() || $response->body() === '') {
            return [];
        }

        $lines = array_filter(preg_split('/\r\n|\r|\n/', trim($response->body())), fn (string $line) => $line !== '');
        $header = str_getcsv((string) array_shift($lines));

        return collect($lines)
            ->map(function (string $line) use ($header) {
                $row = array_combine($header, str_getcsv($line));

                return new SettlementData(
                    id: (string) $row['id'],
                    type: (string) $row['type'],
                    name: (string) $row['name'],
                    municipality: (string) $row['municipality'],
                    region: (string) $row['region'],
                    postalCode: (string) $row['postCode'],
                );
            })
            ->values()
            ->all();
    }
}
