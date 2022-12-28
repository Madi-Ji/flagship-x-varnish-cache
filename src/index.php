<?php
require_once './user.php';
require_once './flagshipRequest.php';

try {
    $headers = apache_request_headers();
    $flagship = new Flagship('bk90qks1tlug042qsqng', 'HwXaeJai242GCC0RGGOym57eSCEimA7A3tkDJbUG');

    if (!isset($headers['x-fs-visitor'])) {
        $visitorId = $flagship->generateUID();
    } else {
        $visitorId = $headers['x-fs-visitor'];
    }

    if($_SERVER['REQUEST_METHOD'] === 'HEAD' || isset($headers['x-fs-experiences'])){
        $flagship->start(
            $visitorId,
            json_encode([
                'nbBooking' => 4,
            ])
        );
    }
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
    echo '<button>'.$flagship->getFlag('restaurant_cta_review_text', 'Leave a Review').'</button>';
} catch (\Throwable $th) {
    throw $th;
}
