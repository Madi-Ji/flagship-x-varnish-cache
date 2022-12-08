<?php
require_once './user.php';
require_once './flagshipRequest.php';

try {
    $headers = apache_request_headers();
    $flagship = new Flagship('ENV_ID', 'API_KEY');

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

    header('Cache-Control: max-age=1, s-maxage=60');

    echo '<pre>For ' . $visitorId . '<br>';

    var_dump(
        $flagship->getFlag('restaurant_cta_review_text', 'Leave a Review')
    );
} catch (\Throwable $th) {
    throw $th;
}
