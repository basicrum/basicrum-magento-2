<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Model\Csp;

use Basicrum\Analytics\Model\Config;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;

/**
 * Adds the validated effective collector origin to storefront fetch policies.
 */
class BeaconPolicyCollector implements PolicyCollectorInterface
{
    private const DIRECTIVES = ['connect-src', 'img-src'];

    /**
     * Initialize the storefront-only policy collector.
     *
     * @param Config $config
     * @param State $appState
     */
    public function __construct(
        private Config $config,
        private State $appState
    ) {
    }

    /**
     * @inheritDoc
     */
    public function collect(array $defaultPolicies = []): array
    {
        // Register alongside core collectors in global DI; area-level arrays
        // replace them. Keep the contribution itself strictly storefront-only.
        try {
            if ($this->appState->getAreaCode() !== Area::AREA_FRONTEND) {
                return $defaultPolicies;
            }
        } catch (LocalizedException $exception) {
            // CLI/bootstrap contexts may not have selected an area yet.
            return $defaultPolicies;
        }

        $runtimeConfig = $this->config->getRuntimeConfig();
        if ($runtimeConfig === null) {
            return $defaultPolicies;
        }

        $origin = $this->getOrigin((string) $runtimeConfig['beacon_endpoint']);
        if ($origin === null) {
            return $defaultPolicies;
        }

        foreach (self::DIRECTIVES as $directive) {
            $defaultPolicies[] = new FetchPolicy($directive, false, [$origin]);
        }

        return $defaultPolicies;
    }

    /**
     * Extract a fetch-policy origin from the validated effective endpoint.
     *
     * @param string $endpoint
     */
    private function getOrigin(string $endpoint): ?string
    {
        // Use the same native URL parser as Config's endpoint validation.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $origin = $scheme . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}
