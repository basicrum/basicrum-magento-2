<?php
declare(strict_types=1);

use Basicrum\Analytics\Model\PageTypeDetector;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;

$root = dirname(__DIR__, 2);
require __DIR__ . '/bootstrap.php';
require $root . '/Api/PageTypeDetectorInterface.php';
require $root . '/Model/Config.php';
require $root . '/Model/PageTypeDetector.php';
require $root . '/ViewModel/Footer.php';
require __DIR__ . '/footer-fixture.php';

$cases = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$rendered = [];
foreach ($cases as $name => $settings) {
    $values = [];
    foreach ($settings as $path => $value) {
        $values[basicrum_test_key('default', 0, $path)] = $value;
    }
    $rendered[$name] = basicrum_render_footer(
        $values,
        new PageTypeDetector(new HttpRequest('catalog_product_view'), new HttpResponse())
    );
}
echo json_encode($rendered, JSON_THROW_ON_ERROR);
