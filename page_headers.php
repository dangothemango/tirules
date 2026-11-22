<?php
// Response headers for every PHP page. Required near the top of prefix.php and index.php,
// after anything that may redirect and exit.
//
// Content-Length: PHP otherwise streams pages chunked, and CloudFront only compresses
// responses that carry a Content-Length, so without it pages go out uncompressed.
// The whole page is buffered here and measured once it is complete; prefix.php's own
// buffer is emptied into this one by its shutdown function, which runs first.
ob_start(function (string $out, int $phase): string {
    if (($phase & PHP_OUTPUT_HANDLER_FINAL) && !headers_sent()) {
        header('Content-Length: ' . strlen($out));
    }
    return $out;
});

// CloudFront keeps a page for 24 hours (s-maxage; the site changes rarely), so readers are
// answered from a nearby edge location. Browsers keep it for 5 minutes (max-age), then ask
// CloudFront again. After a deploy, invalidate /* in CloudFront and every reader sees the edit
// within 5 minutes; without an invalidation it can take up to 24 hours. If the (single) server
// is down or erroring, CloudFront keeps serving its copy for up to another day (stale-if-error).
// Pages that set $page_noindex (report form, 404) are not cached.
if (empty($page_noindex) && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Cache-Control: public, max-age=300, s-maxage=86400, stale-if-error=86400');
}
