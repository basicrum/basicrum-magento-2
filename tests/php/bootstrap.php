<?php
declare(strict_types=1);

namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface
    {
        public const SCOPE_TYPE_DEFAULT = 'default';

        public function getValue($path = null, $scopeType = null, $scopeCode = null);

        public function isSetFlag($path, $scopeType = null, $scopeCode = null);
    }

    class Value
    {
        protected $_config;
        private $value;
        private array $data = [];

        public function __construct(
            $context = null,
            $registry = null,
            $config = null,
            $cacheTypeList = null,
            $resource = null,
            $resourceCollection = null,
            array $data = []
        ) {
            $this->_config = $config;
            $this->data = $data;
        }

        public function beforeSave()
        {
            return $this;
        }

        public function getValue()
        {
            return $this->value;
        }

        public function setValue($value): self
        {
            $this->value = $value;
            return $this;
        }

        public function getData($key = null)
        {
            return $key === null ? $this->data : ($this->data[$key] ?? null);
        }

        public function setData($key, $value): self
        {
            $this->data[$key] = $value;
            return $this;
        }

        public function getStore()
        {
            return $this->data['store'] ?? '';
        }

        public function getWebsite()
        {
            return $this->data['website'] ?? '';
        }

        public function getScope()
        {
            return $this->data['scope'] ?? '';
        }

        public function getScopeCode()
        {
            return $this->data['scope_code'] ?? '';
        }
    }
}

namespace Magento\Framework\App {
    interface RequestInterface
    {
        public function getParam($key, $defaultValue = null);
    }
}

namespace Magento\Framework\App\Cache {
    interface TypeListInterface
    {
    }
}

namespace Magento\Framework\Model {
    class Context
    {
    }
}

namespace Magento\Framework {
    class Registry
    {
    }
}

namespace Magento\Framework\Model\ResourceModel {
    abstract class AbstractResource
    {
    }
}

namespace Magento\Framework\Data\Collection {
    abstract class AbstractDb
    {
    }
}

namespace Magento\Framework\Data {
    interface OptionSourceInterface
    {
        public function toOptionArray(): array;
    }
}

namespace Magento\Framework\Exception {
    class LocalizedException extends \Exception
    {
    }
}

namespace Magento\Store\Model {
    interface ScopeInterface
    {
        public const SCOPE_STORE = 'store';
        public const SCOPE_WEBSITE = 'website';
        public const SCOPE_STORES = 'stores';
        public const SCOPE_WEBSITES = 'websites';
    }

    interface StoreManagerInterface
    {
        public function getStore($storeId = null);
    }
}

namespace Magento\Framework\View\Element\Block {
    interface ArgumentInterface
    {
    }
}

namespace {
    use Magento\Framework\App\Config\ScopeConfigInterface;
    use Magento\Framework\App\RequestInterface;
    use Magento\Store\Model\ScopeInterface;

    if (!function_exists('__')) {
        function __($message)
        {
            return $message;
        }
    }

    final class BasicrumTestScopeConfig implements ScopeConfigInterface
    {
        /** @var array<string, mixed> */
        private array $values;

        /** @var array<string, string> */
        private array $storeWebsites;

        /** @param array<string, mixed> $values */
        public function __construct(array $values = [], array $storeWebsites = [])
        {
            $this->values = $values;
            $this->storeWebsites = $storeWebsites;
        }

        public function getValue($path = null, $scopeType = null, $scopeCode = null)
        {
            $scopeType = $scopeType ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeCode = $scopeCode === null ? '0' : (string) $scopeCode;
            $key = $scopeType . '|' . $scopeCode . '|' . $path;
            if (array_key_exists($key, $this->values)) {
                return $this->values[$key];
            }

            if ($scopeType === ScopeInterface::SCOPE_STORE) {
                $website = $this->storeWebsites[$scopeCode] ?? null;
                if ($website !== null) {
                    $websiteKey = ScopeInterface::SCOPE_WEBSITE . '|' . $website . '|' . $path;
                    if (array_key_exists($websiteKey, $this->values)) {
                        return $this->values[$websiteKey];
                    }
                }
            }

            $defaultKey = ScopeConfigInterface::SCOPE_TYPE_DEFAULT . '|0|' . $path;
            return $this->values[$defaultKey] ?? null;
        }

        public function isSetFlag($path, $scopeType = null, $scopeCode = null)
        {
            return (bool) $this->getValue($path, $scopeType, $scopeCode);
        }
    }

    final class BasicrumTestWebsite
    {
        public function __construct(private string $code)
        {
        }

        public function getCode(): string
        {
            return $this->code;
        }
    }

    final class BasicrumTestStore
    {
        public function __construct(private string $websiteCode)
        {
        }

        public function getWebsite(): BasicrumTestWebsite
        {
            return new BasicrumTestWebsite($this->websiteCode);
        }
    }

    final class BasicrumTestStoreManager implements \Magento\Store\Model\StoreManagerInterface
    {
        /** @param array<string, string> $storeWebsites */
        public function __construct(private array $storeWebsites = [])
        {
        }

        public function getStore($code = null): BasicrumTestStore
        {
            return new BasicrumTestStore($this->storeWebsites[(string) $code] ?? 'base');
        }
    }

    final class BasicrumTestRequest implements RequestInterface
    {
        /** @param array<string, mixed> $params */
        public function __construct(private array $params = [])
        {
        }

        public function getParam($key, $defaultValue = null)
        {
            return $this->params[$key] ?? $defaultValue;
        }
    }

    function basicrum_test_key(string $scope, $scopeCode, string $path): string
    {
        return $scope . '|' . (string) $scopeCode . '|' . $path;
    }

    function basicrum_assert_true($actual, string $message): void
    {
        if ($actual !== true) {
            throw new RuntimeException($message . '; actual=' . var_export($actual, true));
        }
    }

    function basicrum_assert_false($actual, string $message): void
    {
        if ($actual !== false) {
            throw new RuntimeException($message . '; actual=' . var_export($actual, true));
        }
    }

    function basicrum_assert_same($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                $message . '; expected=' . var_export($expected, true) . '; actual=' . var_export($actual, true)
            );
        }
    }

    function basicrum_assert_contains(string $needle, string $haystack, string $message): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException($message . '; missing=' . $needle);
        }
    }

    function basicrum_assert_not_contains(string $needle, string $haystack, string $message): void
    {
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException($message . '; unexpected=' . $needle);
        }
    }
}
