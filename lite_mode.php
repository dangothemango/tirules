<?php
// Lite site (lite.tirules2.com): the same pages without citations, sources, in-text links
// (Related Topics and other bulleted lists keep theirs), red or strike-through. It is not
// indexed: every lite response sends X-Robots-Tag: noindex, and canonicals point to the main site.
// A reader opts in with the button on the home page (lite.php), which sets the lite cookie on
// the parent domain; the main site then sends that reader to the same page on the lite site.
// Search engines never carry the cookie, so they are never redirected.
// Required by prefix.php and index.php before any output.

const LITE_COOKIE = 'lite';
const LITE_BASE_HOSTS = ['tirules2.com', 'localhost']; // localhost: php -S, lite.localhost:8000

$lite_host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$lite_port = '';
if (preg_match('/^(.*?)(:\d+)$/', $lite_host, $hm)) {
    [, $lite_host, $lite_port] = $hm;
}
$is_lite = false;
$lite_base = null;
foreach (LITE_BASE_HOSTS as $base) {
    if ($lite_host === 'lite.' . $base) {
        $is_lite = true;
        $lite_base = $base;
    } elseif ($lite_host === $base || $lite_host === 'www.' . $base) {
        $lite_base = $base;
    }
}

// Absolute URL on the lite or main site, e.g. lite_url(true, '/R_combat')
function lite_url(bool $lite, string $path): string
{
    global $lite_base, $lite_port;
    $scheme = $lite_base === 'localhost' ? 'http' : 'https';
    return $scheme . '://' . ($lite ? 'lite.' : '') . $lite_base . $lite_port . $path;
}

// Set (true) or clear (false) the lite choice for the main site and the lite site
function lite_set_cookie(bool $on): void
{
    global $lite_base;
    $options = ['path' => '/', 'samesite' => 'Lax', 'httponly' => true];
    if ($lite_base !== 'localhost') {
        // localhost can't share a cookie with lite.localhost, so locally it stays per host
        $options['domain'] = $lite_base;
        $options['secure'] = true;
    }
    $options['expires'] = $on ? time() + 365 * 24 * 3600 : time() - 3600;
    setcookie(LITE_COOKIE, $on ? '1' : '', $options);
}

if ($is_lite) {
    header('X-Robots-Tag: noindex');
} elseif ($lite_base !== null && ($_COOKIE[LITE_COOKIE] ?? '') === '1'
          && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Cache-Control: private, no-store');
    header('Location: ' . lite_url(true, $_SERVER['REQUEST_URI'] ?? '/'), true, 302);
    exit;
}

// Lite page body: drop citations, unwrap links outside <ul> lists, and drop the red
// (class "removed") and strike-through (<del>) styling. The footer leaves out its own
// Proof link and sources (suffix.php).
function lite_strip(string $html): string
{
    $html = preg_replace('#<sup>[^<]*</sup>#', '', $html);
    $html = preg_replace('#</?del>#', '', $html);
    $html = preg_replace('#\s+class="removed"#', '', $html);
    return preg_replace_callback('#<article\b[^>]*>.*?</article>#s', function ($article) {
        $parts = preg_split('#(<ul\b.*?</ul>)#s', $article[0], -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = preg_replace('#<a\b[^>]*>(.*?)</a>#s', '$1', $part);
            }
        }
        return implode('', $parts);
    }, $html);
}
