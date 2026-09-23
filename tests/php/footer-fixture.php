<?php
declare(strict_types=1);

use Basicrum\Analytics\Api\PageTypeDetectorInterface;
use Basicrum\Analytics\Model\Config;
use Basicrum\Analytics\ViewModel\Footer;

/**
 * Render production PHP, substituting only Magento's block/HTML renderer.
 * Native integration tests remain responsible for real layout and CSP behavior.
 *
 * @param array<string, mixed> $values
 */
function basicrum_render_footer(array $values, PageTypeDetectorInterface $detector): string
{
    $footer = new Footer($detector, new Config(new BasicrumTestScopeConfig($values)));
    $block = new class($footer) {
        public function __construct(private Footer $footer) {}
        public function getViewModel(): Footer { return $this->footer; }
        public function getViewFileUrl(string $asset): string
        {
            return 'https://shop.test/static/version123/' . str_replace('::', '/', $asset);
        }
    };
    $secureRenderer = new class {
        public function renderTag(string $tag, array $attributes, string $content, bool $textContent): string
        {
            $rendered = '';
            foreach ($attributes as $name => $value) {
                $rendered .= ' ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '="'
                    . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
            }
            return '<' . $tag . $rendered . '>' . $content . '</' . $tag . '>';
        }
    };

    ob_start();
    try {
        include dirname(__DIR__, 2) . '/view/frontend/templates/footer.phtml';
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
}
