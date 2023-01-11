<?php
require_once './user.php';
require_once './flagshipRequest.php';

$envId = "";
$apiKey = "";

try {
    $headers = apache_request_headers();
    $flagship = new Flagship($envId, $apiKey);

    if (!isset($headers['x-fs-visitor'])) {
        $visitorId = $flagship->generateUID();
    } else {
        $visitorId = $headers['x-fs-visitor'];
    }

    $flagship->start(
        $visitorId,
        json_encode([
            'nbBooking' => 4,
        ])
    );

    $cacheHashKey = $flagship->getHashKey();

    if ($cacheHashKey === false) {
        $cacheHashKey = 'optout';
        $visitorId = 'ignore-me';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        header('x-fs-visitor: ' . $visitorId);
        header('x-fs-experiences: ' . $cacheHashKey);
        exit();
    }

    header('x-fs-visitor: ' . $visitorId);
    header('x-fs-experiences: ' . $cacheHashKey);
    header('Cache-Control: max-age=1, s-maxage=600');

    echo '<pre>';

    if ($cacheHashKey == 'optout') {
        echo 'Global Cache 🔥 <br />';
    }
    echo '<button>' . $flagship->getFlag('restaurant_cta_review_text', 'Leave a Review') . '</button>';
} catch (\Throwable $th) {
    throw $th;
}
