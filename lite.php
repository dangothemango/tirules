<?php
// /lite: the home page's "lite site" / "full site" button posts here (a POST, so crawlers
// never follow it, and CloudFront forwards the body where it would drop a query string).
// lite=1 sets the lite cookie and goes to the lite home page; lite=0 clears it and goes
// to the main home page. A GET just returns to this host's home page.
require __DIR__ . '/lite_mode.php';
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');
if ($lite_base === null) {
    header('Location: /', true, 303);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['lite'])) {
    $on = $_POST['lite'] === '1';
    lite_set_cookie($on);
    if (!$on && $is_lite && $lite_base === 'localhost') {
        // Locally the cookie is per host: repeat the post on localhost to clear its copy too
        header('Location: ' . lite_url(false, '/lite'), true, 307);
        exit;
    }
    header('Location: ' . lite_url($on, '/'), true, 303);
    exit;
}
header('Location: /', true, 303);
