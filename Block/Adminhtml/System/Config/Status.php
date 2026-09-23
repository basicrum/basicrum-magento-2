<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Block\Adminhtml\System\Config;

use Basicrum\Analytics\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

/**
 * Explains the effective monitoring state for the selected admin scope.
 */
class Status extends ReadOnlyField
{
    /**
     * Initialize the native Admin field renderer.
     *
     * @param Context $context
     * @param Config $config
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        [$scopeType, $scopeCode] = $this->getSelectedScope();
        $status = $this->config->getStatus($scopeType, $scopeCode);

        $messages = [
            'disabled' => __('Inactive: enable Basicrum to emit monitoring scripts.'),
            'missing_endpoint' => __('Inactive: enter a Beacon Endpoint for this scope or inherit one.'),
            'invalid_endpoint' => __('Inactive: the effective Beacon Endpoint is invalid.'),
            'missing_site_id' => __('Inactive: enter a UUIDv4 Brum Site ID for this scope or inherit one.'),
            'invalid_site_id' => __('Inactive: the effective Brum Site ID is not a UUIDv4.'),
            // Translation keys remain whole strings, not concatenated fragments.
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'active_consent' => __('Ready, consent-controlled: the storefront emits only the inert consent wrapper until the current page receives an authoritative allow callback.'),
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'active_immediate' => __('Ready, immediate: Boomerang loads without waiting for a consent decision and may set cookies and send performance data.'),
        ];

        $message = $messages[$status] ?? __('Inactive: review the effective Basicrum configuration.');

        return sprintf(
            '<div class="message message-%s"><div>%s</div></div>',
            str_starts_with($status, 'active_') ? 'success' : 'warning',
            $this->escapeHtml((string) $message)
        );
    }

    /**
     * Resolve the scope selected in Magento's configuration UI.
     *
     * @return array{0: string, 1: string|null}
     */
    private function getSelectedScope(): array
    {
        $storeCode = (string) $this->getRequest()->getParam('store', '');
        if ($storeCode !== '') {
            return [ScopeInterface::SCOPE_STORE, $storeCode];
        }

        $websiteCode = (string) $this->getRequest()->getParam('website', '');
        if ($websiteCode !== '') {
            return [ScopeInterface::SCOPE_WEBSITE, $websiteCode];
        }

        return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null];
    }
}
