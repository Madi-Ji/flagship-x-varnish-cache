<?php
require_once __DIR__ . '../vendor/autoload.php';

use Flagship\Flagship;

$envId = "bk90qks1tlug042qsqng";
$apiKey = "HwXaeJai242GCC0RGGOym57eSCEimA7A3tkDJbUG";

try {
    $headers = apache_request_headers();

    Flagship::Start($envId, $apiKey);

    $visitorId = null;

    if (isset($headers['x-fs-visitor'])) {
        $visitorId = $headers['x-fs-visitor'];
    }

    $visitor = Flagship::newVisitor($visitorId)->build();

    $visitor->fetchFlags();

    $cacheKey = $flagship->getHashKey();

    if ($cacheKey === false) {
        $cacheKey = 'optout';
        $visitorId = 'ignore-me';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        if ($cacheKey == 'optout') {
            $experiencesCookie = $cacheKey;
        } else {
            $experiencesCookie = $visitorId . '@' . $cacheKey;
        }
        header('x-fs-visitor: ' . $visitorId);
        header('x-fs-experiences: ' . $cacheKey);
        exit();
    }

    header('Cache-Control: max-age=1, s-maxage=600');

    echo '<pre>';

    if ($cacheKey == 'optout') {
        echo 'Global Cache 🔥 <br />';
    }
    echo '<button>' . $flagship->getFlag('restaurant_cta_review_text', 'Leave a Review') . '</button>';
} catch (\Throwable $th) {
    throw $th;
}
