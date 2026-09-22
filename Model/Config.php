<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads and validates the effective Basicrum configuration for a store scope.
 */
class Config
{
    public const XML_PATH_ENABLED = 'basicrum/general/enabled';
    public const XML_PATH_BEACON_ENDPOINT = 'basicrum/general/beacon_endpoint';
    public const XML_PATH_BRUM_SITE_ID = 'basicrum/general/brum_site_id';
    public const XML_PATH_CONSENT_ENABLED = 'basicrum/consent/enabled';
    public const XML_PATH_CONSENT_MODE = 'basicrum/consent/mode';
    public const XML_PATH_STRIP_QUERY_STRING = 'basicrum/privacy/strip_query_string';
    public const XML_PATH_WAIT_ENABLED = 'basicrum/performance/wait_after_onload';
    public const XML_PATH_WAIT_MS = 'basicrum/performance/delay_ms';
    public const XML_PATH_DEVELOPMENT_MODE = 'basicrum/developer/development_mode';

    public const BOOMERANG_VERSION = '1.815.60';
    public const CONSENT_MODE_MANUAL = 'manual';
    public const MAX_WAIT_MS = 30000;

    /**
     * Legacy values are retained so an upgrade never discards saved data.
     * They all mean manual callback integration and never imply consent.
     */
    public const LEGACY_CONSENT_MODES = ['explicit', 'implicit', 'cookie', 'gdpr'];

    private const BRUM_SITE_ID_PATTERN =
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Defaults used by config.xml and by runtime normalization.
     *
     * @return array<string, bool|int|string>
     */
    public static function getDefaults(): array
    {
        return [
            'enabled' => false,
            'beacon_endpoint' => '',
            'brum_site_id' => '',
            'consent_enabled' => true,
            'consent_mode' => self::CONSENT_MODE_MANUAL,
            'strip_query_string' => false,
            'wait_after_onload' => false,
            'delay_ms' => 0,
            'development_mode' => false,
        ];
    }

    /**
     * Return a complete, validated runtime configuration or null when inactive.
     *
     * @param string|int|null $scopeCode
     * @return array<string, bool|int|string>|null
     */
    public function getRuntimeConfig(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        $scopeCode = null
    ): ?array {
        if (!$this->getBoolean(self::XML_PATH_ENABLED, false, $scopeType, $scopeCode)) {
            return null;
        }

        $endpoint = $this->getBeaconEndpoint($scopeType, $scopeCode);
        $siteId = $this->getBrumSiteId($scopeType, $scopeCode);

        if ($endpoint === null || $siteId === null) {
            return null;
        }

        return [
            'beacon_endpoint' => $endpoint,
            'brum_site_id' => $siteId,
            'consent_enabled' => $this->getBoolean(
                self::XML_PATH_CONSENT_ENABLED,
                true,
                $scopeType,
                $scopeCode
            ),
            'consent_mode' => $this->getConsentMode($scopeType, $scopeCode),
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
     * @param string|int|null $scopeCode
     * @return array{state: string, consent_mode: string}
     */
    public function getStatus(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        $scopeCode = null
    ): array {
        if (!$this->getBoolean(self::XML_PATH_ENABLED, false, $scopeType, $scopeCode)) {
            return ['state' => 'disabled', 'consent_mode' => $this->getConsentMode($scopeType, $scopeCode)];
        }

        $rawEndpoint = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_BEACON_ENDPOINT,
            $scopeType,
            $scopeCode
        ));
        if ($rawEndpoint === '') {
            return ['state' => 'missing_endpoint', 'consent_mode' => $this->getConsentMode($scopeType, $scopeCode)];
        }
        if (!self::isValidBeaconEndpoint($rawEndpoint)) {
            return ['state' => 'invalid_endpoint', 'consent_mode' => $this->getConsentMode($scopeType, $scopeCode)];
        }

        $rawSiteId = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_BRUM_SITE_ID,
            $scopeType,
            $scopeCode
        ));
        if ($rawSiteId === '') {
            return ['state' => 'missing_site_id', 'consent_mode' => $this->getConsentMode($scopeType, $scopeCode)];
        }
        if (!self::isValidBrumSiteId($rawSiteId)) {
            return ['state' => 'invalid_site_id', 'consent_mode' => $this->getConsentMode($scopeType, $scopeCode)];
        }

        $consentRequired = $this->getBoolean(
            self::XML_PATH_CONSENT_ENABLED,
            true,
            $scopeType,
            $scopeCode
        );

        return [
            'state' => $consentRequired ? 'active_consent' : 'active_immediate',
            'consent_mode' => $this->getConsentMode($scopeType, $scopeCode),
        ];
    }

    /**
     * Validate a collector URL without accepting executable URL schemes.
     */
    public static function isValidBeaconEndpoint($value): bool
    {
        if (!is_string($value) || $value === '' || trim($value) !== $value) {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

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
     */
    public static function isValidBrumSiteId($value): bool
    {
        return is_string($value) && preg_match(self::BRUM_SITE_ID_PATTERN, $value) === 1;
    }

    /**
     * Normalize only explicit boolean values. Unknown values fail to the caller's default.
     */
    public static function normalizeBoolean($value, bool $default): bool
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
     */
    public static function normalizeWaitMilliseconds($value): int
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return 0;
        }

        return min(self::MAX_WAIT_MS, max(0, (int) $value));
    }

    /**
     * @param string|int|null $scopeCode
     */
    private function getBeaconEndpoint(string $scopeType, $scopeCode): ?string
    {
        $endpoint = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_BEACON_ENDPOINT,
            $scopeType,
            $scopeCode
        ));

        if (!self::isValidBeaconEndpoint($endpoint)) {
            return null;
        }

        $developmentMode = $this->getBoolean(
            self::XML_PATH_DEVELOPMENT_MODE,
            false,
            $scopeType,
            $scopeCode
        );
        if (!$developmentMode && stripos($endpoint, 'http://') === 0) {
            $endpoint = 'https://' . substr($endpoint, 7);
        }

        return $endpoint;
    }

    /**
     * @param string|int|null $scopeCode
     */
    private function getBrumSiteId(string $scopeType, $scopeCode): ?string
    {
        $siteId = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_BRUM_SITE_ID,
            $scopeType,
            $scopeCode
        ));

        return self::isValidBrumSiteId($siteId) ? $siteId : null;
    }

    /**
     * @param string|int|null $scopeCode
     */
    private function getConsentMode(string $scopeType, $scopeCode): string
    {
        $mode = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_CONSENT_MODE,
            $scopeType,
            $scopeCode
        ));

        if ($mode === self::CONSENT_MODE_MANUAL || in_array($mode, self::LEGACY_CONSENT_MODES, true)) {
            return $mode;
        }

        return self::CONSENT_MODE_MANUAL;
    }

    /**
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
