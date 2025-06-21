<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Block\Adminhtml\System\Config;

class ConsentMode implements \Magento\Framework\Data\OptionSourceInterface
{
 public function toOptionArray()
 {
  return [
    ['value' => 'explicit', 'label' => __('Explicit Consent')],
    ['value' => 'implicit', 'label' => __('Implicit Consent')],
    ['value' => 'cookie', 'label' => __('Cookie Banner')],
    ['value' => 'gdpr', 'label' => __('GDPR Banner')]
  ];
 }
}