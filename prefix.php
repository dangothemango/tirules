<?php
require __DIR__ . '/redirect_php_urls.php';
require __DIR__ . '/lite_mode.php';
require __DIR__ . '/page_headers.php';
ob_start(); ?>
<!DOCTYPE html>
<html lang="en"<?php if ($is_lite) echo ' data-theme="light"'; ?>>
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<?php if (empty($page_noindex)) { // the 404 page sets $page_noindex: no canonical, not indexed ?>
<link rel="canonical" href="https://tirules2.com<?php echo htmlspecialchars(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'); ?>"/>
<?php } ?>
<?php $head_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '';
// Proof pages (/R_combat/pudding), source pages (/sources/3) and search results are not indexed
if (!empty($page_noindex) || str_ends_with($head_path, '/pudding') || str_starts_with($head_path, '/sources/') || $head_path === '/search') { ?>
<meta name="robots" content="noindex, follow"/>
<?php } ?>
<link rel="icon" href="/favicon.png"/>
<link rel="stylesheet" type="text/css" href="/style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>"/>
<link rel="preload" href="/fonts/helv.woff2" as="font" type="font/woff2" crossorigin/>
<link rel="preload" href="/fonts/slider.woff2" as="font" type="font/woff2" crossorigin/>
<?php
include __DIR__ . '/theme.php';
$content = ob_get_clean();
ob_start();
register_shutdown_function(function() use ($content, $theme_toggle_button, $report_button, $is_lite) {
    $page_content = ob_get_clean();
    // Lite site: strip citations, in-text links and red from rule, faction and component pages
    if ($is_lite && preg_match('#^/[RFC]_[A-Za-z0-9_]+$#', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '')) {
        $page_content = lite_strip($page_content);
    }
    
    // Extract the header text
    $page_title = 'Twilight Imperium Rule Help';
    if (preg_match('/<header>(.*?)<\/header>/i', $page_content, $matches)) {
        // Header markup -> plain text: <br> becomes " / ", entities are decoded
        // (htmlspecialchars below re-escapes once), decorative symbols and aria-hidden spans are dropped.
        $text = preg_replace(['/<span aria-hidden="true">.*?<\/span>/i', '/<br\s*\/?>/i'], ['', ' / '], $matches[1]);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace(['/\p{So}/u', '/\s+/u'], ['', ' '], $text));
        $page_title = $text . ' - Twilight Imperium Rules';
    }
    
    // Meta description: a rules page's opening Rules Reference paragraph; otherwise a generic line
    $page_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $heading = isset($text) ? $text : 'Twilight Imperium';
    $description = '';
    if (preg_match('#^/R_[A-Za-z0-9_]+$#', $page_path) && preg_match('/<article>.*?<p[^>]*>(.*?)<\/p>(?:\s*<ol(?![^>]*\bclass=)[^>]*>(.*?)<\/ol>)?/is', $page_content, $pm)) {
        // A paragraph ending in a colon introduces the plain list after it (R_game_round): include its items
        if (str_ends_with(trim($pm[1]), ':') && !empty($pm[2])) {
            $items = array_filter(array_map(fn($li) => trim(strip_tags($li)), preg_split('/<li[^>]*>/i', $pm[2])), 'strlen');
            $pm[1] .= ' ' . implode(', ', $items) . '.';
        }
        $description = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($pm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (str_starts_with($description, 'There currently does not exist')) {
            $description = '';
        }
    }
    if ($description === '') {
        $description = 'Notes and rulings on ' . $heading . ' for Twilight Imperium Fourth Edition, with sources.';
    }
    if (mb_strlen($description) > 160) {
        $cut = mb_substr($description, 0, 157);
        $description = rtrim(mb_substr($cut, 0, mb_strrpos($cut, ' ')), " ,;:") . '…';
    }

    // Output the complete page with dynamic title
    echo $content;
    echo '<meta name="description" content="' . htmlspecialchars($description) . '"/>' . "\n";
    echo '<title>' . htmlspecialchars($page_title) . '</title>';
    echo "\n</head>\n<body>\n";
    $page_content = preg_replace('/<\/header>/i', $report_button . $theme_toggle_button . '</header>', $page_content, 1);
    // Wiki links open in a new tab (skips any link that already sets a target)
    $page_content = preg_replace('/<a href="(https:\/\/twilight-imperium\.fandom\.com[^"]*)"(?![^>]*\btarget=)/', '<a href="$1" target="_blank"', $page_content);
    // data-src (the sources of each Rules Reference item) stays in the page files as a record
    // for editors; suffix.php has already read it to choose the footer sources, so drop it here.
    echo preg_replace('/ data-src="[^"]*"/', '', $page_content);
});
?>
