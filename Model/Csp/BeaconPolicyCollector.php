<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Model\Csp;

use Basicrum\Analytics\Model\Config;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;

/**
 * Adds the validated effective collector origin to storefront fetch policies.
 */
class BeaconPolicyCollector implements PolicyCollectorInterface
{
    private const DIRECTIVES = ['connect-src', 'img-src'];

    public function __construct(
        private Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function collect(array $defaultPolicies = []): array
    {
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

    private function getOrigin(string $endpoint): ?string
    {
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
