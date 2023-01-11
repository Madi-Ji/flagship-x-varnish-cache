<?php
require_once __DIR__ . '/vendor/autoload.php';

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

    $cacheHashKey = null;
    $visitor = Flagship::newVisitor($visitorId)->build();

    $visitorId = $visitor->getVisitorId();

    $visitor->fetchFlags();

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD' || isset($headers['x-fs-experiences'])) {

        $experiences = [];
        foreach ($visitor->getFlagsDTO() as $value) {
            $experience = "{$value->getCampaignId()}:{$value->getVariationId()}";
            if (in_array($experience, $experiences)) {
                continue;
            }
            $experiences[] = $experience;
        }
        if (count($experiences)) {
            $cacheHashKey = implode("|", $experiences);
        }
    }


    if (!$cacheHashKey) {
        $cacheHashKey = 'optout';
        $visitorId = 'ignore-me';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        if ($cacheHashKey == 'optout') {
            $experiencesCookie = $cacheHashKey;
        } else {
            $experiencesCookie = $visitorId . '@' . $cacheHashKey;
        }
        header('x-fs-visitor: ' . $visitorId);
        header('x-fs-experiences: ' . $cacheHashKey);
        exit();
    }

    header('Cache-Control: max-age=1, s-maxage=600');

    echo '<pre>';

    if ($cacheHashKey == 'optout') {
        echo 'Global Cache 🔥 <br />';
    }
    echo '<button>' . $visitor->getFlag('restaurant_cta_review_text', 'Leave a Review')->getValue() . '</button>';
} catch (\Throwable $th) {
    throw $th;
}
