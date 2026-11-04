# App/Services guidelines

- Every third-party service the app reaches over the network (an AI provider, a mailer, a payment or streaming platform) sits behind a contract, so swapping vendor changes one class.
- The contract is an interface in `app/Contracts/Services`, named after the role it plays, never the vendor: `AnalyticsProvider`, `TurnProvider`.
- Each implementation lives in `app/Services/{Role}s/` and is named vendor + role: `app/Services/AnalyticsProviders/FathomAnalyticsProvider.php`.
- Add the contract to service provider bindings: `PRODUCTION_SERVICES` with the real implementation, and `LOCAL_SERVICES` with a local one that needs no account, credentials or network (`Local{Role}` or `Null{Role}`).
- Everything else depends on the contract, never on the implementation: inject it into constructors, controller methods, or form requests.
- Classes in `app/Services` that only parse or compute (`Urls`, `Mentions`) talk to nobody and stay plain classes without an interface contract.

@boostsnippet('Example service contract and implementation', 'php')
<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface AnalyticsProvider
{
    /**
     * @param array<string, mixed> $data
     */
    public function track(string $event, array $data = []): void;
}

namespace App\Services\AnalyticsProviders;

use App\Contracts\Services\AnalyticsProvider;

final readonly class FathomAnalyticsProvider implements AnalyticsProvider
{
    public function track(string $event, array $data = []): void
    {
        // Implementation
    }
}
@endboostsnippet
