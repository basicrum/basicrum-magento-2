<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads and validates the effective Basicrum configuration for a store scope.
 *
 * @phpstan-type RuntimeConfig array{beacon_endpoint: string, brum_site_id: string,
 *     consent_enabled: bool, strip_query_string: bool, wait_after_onload: bool, delay_ms: int}
 */
class Config
{
    public const XML_PATH_ENABLED = 'basicrum/general/enabled';
    public const XML_PATH_BEACON_ENDPOINT = 'basicrum/general/beacon_endpoint';
    public const XML_PATH_BRUM_SITE_ID = 'basicrum/general/brum_site_id';
    public const XML_PATH_CONSENT_ENABLED = 'basicrum/consent/enabled';
    public const XML_PATH_STRIP_QUERY_STRING = 'basicrum/privacy/strip_query_string';
    public const XML_PATH_WAIT_ENABLED = 'basicrum/performance/wait_after_onload';
    public const XML_PATH_WAIT_MS = 'basicrum/performance/delay_ms';
    public const XML_PATH_DEVELOPMENT_MODE = 'basicrum/developer/development_mode';

    public const BOOMERANG_VERSION = '1.815.60';
    public const MAX_WAIT_MS = 30000;

    private const BRUM_SITE_ID_PATTERN =
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * Initialize the scope-aware configuration reader.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Return a complete, validated runtime configuration or null when inactive.
     *
     * @param string $scopeType
     * @param string|int|null $scopeCode
     * @return RuntimeConfig|null
     */
    public function getRuntimeConfig(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        $scopeCode = null
    ): ?array {
        // Admin feedback and storefront eligibility use the same decision.
        $state = $this->getStatus($scopeType, $scopeCode);
        if (!in_array($state, ['active_consent', 'active_immediate'], true)) {
            return null;
        }

        return [
            'beacon_endpoint' => self::normalizeBeaconEndpoint(
                $this->getString(self::XML_PATH_BEACON_ENDPOINT, $scopeType, $scopeCode),
                $this->getBoolean(self::XML_PATH_DEVELOPMENT_MODE, false, $scopeType, $scopeCode)
            ),
            'brum_site_id' => $this->getString(self::XML_PATH_BRUM_SITE_ID, $scopeType, $scopeCode),
            'consent_enabled' => $state === 'active_consent',
            'strip_query_string' => $this->getBoolean(
                self::XML_PATH_STRIP_QUERY_STRING,
                false,
                $scopeType,
                $scopeCode
            ),
            'wait_after_onload' => $this->getBoolean(
                self::XML_PATH_WAIT_ENABLED,
                false,
                $scopeType,
                $scopeCode
            ),
            'delay_ms' => self::normalizeWaitMilliseconds(
                $this->scopeConfig->getValue(self::XML_PATH_WAIT_MS, $scopeType, $scopeCode)
            ),
        ];
    }

    /**
     * Describe why the effective scope is active or inactive for admin feedback.
     *
     * @param string $scopeType
     * @param string|int|null $scopeCode
     */
    public function getStatus(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        $scopeCode = null
    ): string {
        if (!$this->getBoolean(self::XML_PATH_ENABLED, false, $scopeType, $scopeCode)) {
            return 'disabled';
        }

        $rawEndpoint = $this->getString(self::XML_PATH_BEACON_ENDPOINT, $scopeType, $scopeCode);
        if ($rawEndpoint === '') {
            return 'missing_endpoint';
        }
        if (!self::isValidBeaconEndpoint($rawEndpoint)) {
            return 'invalid_endpoint';
        }

        $rawSiteId = $this->getString(self::XML_PATH_BRUM_SITE_ID, $scopeType, $scopeCode);
        if ($rawSiteId === '') {
            return 'missing_site_id';
        }
        if (!self::isValidBrumSiteId($rawSiteId)) {
            return 'invalid_site_id';
        }

        $consentRequired = $this->getBoolean(
            self::XML_PATH_CONSENT_ENABLED,
            true,
            $scopeType,
            $scopeCode
        );

        return $consentRequired ? 'active_consent' : 'active_immediate';
    }

    /**
     * Validate a collector URL without accepting executable URL schemes.
     *
     * @param mixed $value
     */
    public static function isValidBeaconEndpoint(mixed $value): bool
    {
        if (!is_string($value) || $value === '' || trim($value) !== $value) {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // Native parsing deliberately matches validation and the CSP origin parser.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        // Credentials would be exposed in storefront configuration, while a
        // fragment is never part of an HTTP request. Query strings remain
        // supported for compatibility with collectors that require them.
        if (array_key_exists('user', $parts)
            || array_key_exists('pass', $parts)
            || array_key_exists('fragment', $parts)
        ) {
            return false;
        }

        return in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true);
    }

    /**
     * Validate a Brum Site ID as an RFC 4122 UUIDv4.
     *
     * @param mixed $value
     */
    public static function isValidBrumSiteId(mixed $value): bool
    {
        return is_string($value) && preg_match(self::BRUM_SITE_ID_PATTERN, $value) === 1;
    }

    /**
     * Normalize only explicit boolean values. Unknown values fail to the caller's default.
     *
     * @param mixed $value
     * @param bool $default
     */
    public static function normalizeBoolean(mixed $value, bool $default): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        return $default;
    }

    /**
     * Clamp a configured delay to the supported zero-to-30-second range.
     *
     * @param mixed $value
     */
    public static function normalizeWaitMilliseconds(mixed $value): int
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return 0;
        }

        return min(self::MAX_WAIT_MS, max(0, (int) $value));
    }

    /**
     * Read a trimmed scoped string.
     *
     * @param string $path
     * @param string $scopeType
     * @param string|int|null $scopeCode
     */
    private function getString(string $path, string $scopeType, $scopeCode): string
    {
        return trim((string) $this->scopeConfig->getValue($path, $scopeType, $scopeCode));
    }

    /**
     * Apply the same HTTPS policy at save time and runtime after validation.
     *
     * @param string $endpoint
     * @param bool $httpAllowed
     */
    public static function normalizeBeaconEndpoint(string $endpoint, bool $httpAllowed): string
    {
        return !$httpAllowed && stripos($endpoint, 'http://') === 0
            ? 'https://' . substr($endpoint, 7)
            : $endpoint;
    }

    /**
     * Read an explicitly supported boolean, or the caller's safe default.
     *
     * @param string $path
     * @param bool $default
     * @param string $scopeType
     * @param string|int|null $scopeCode
     */
    private function getBoolean(
        string $path,
        bool $default,
        string $scopeType,
        $scopeCode
    ): bool {
        return self::normalizeBoolean(
            $this->scopeConfig->getValue($path, $scopeType, $scopeCode),
            $default
        );
    }
}
