<footer>
<p><a href="/./" style="font-size:2em;">Home</a></p>
<?php
// Link each page to its note proofs (/R_combat/pudding), and each proof page back.
$footer_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
// The lite site has no citations, so no Proof link and no sources.
if (empty($is_lite) && preg_match('#^/([RFC]_[A-Za-z0-9_]+)$#', $footer_path, $m) && is_file(__DIR__ . '/pudding/' . $m[1] . '.php')) {
    echo "<p><a href=\"/{$m[1]}/pudding\">Proof</a>: the source text behind each note on this page.</p>\n";
} elseif (preg_match('#^/([RFC]_[A-Za-z0-9_]+)/pudding$#', $footer_path, $m)) {
    echo "<p><a href=\"/{$m[1]}\">Back to the page</a></p>\n";
}
// Report a problem with this page (the form opens a GitHub issue for the reader).
if (empty($is_lite) && $footer_path !== '/report') {
    echo "<p><a href=\"/report?page=" . htmlspecialchars(rawurlencode($footer_path)) . "\">Report a problem with this page</a></p>\n";
}
?>
<p><i>Twilight Imperium</i> &copy; Fantasy Flight Games. Not Affiliated.</p>
<p><a href="https://github.com/dangothemango/tirules">Github</a></p>
<p>This site costs $50+ a month to host. If you find it useful, consider <a href="https://buymeacoffee.com/dangorman">buying me a coffee</a>.</p>
<?php
// Sources. A rule, faction or component page lists only the sources it cites (note <sup>
// numbers and the data-src of its Rules Reference items, which prefix.php strips after this);
// every other page lists them all. The lite site has no citations, so no sources.
require_once __DIR__ . '/sources.php';
if (empty($is_lite)) {
    $cited = null;
    if (preg_match('#^/[RFC]_[A-Za-z0-9_]+$#', $footer_path)) {
        $cited = [];
        preg_match_all('#<sup>([^<]*)</sup>|data-src="([^"]*)"#', ob_get_contents(), $cm, PREG_SET_ORDER);
        foreach ($cm as $c) {
            foreach (explode(',', ($c[1] ?? '') . ',' . ($c[2] ?? '')) as $n) {
                $cited[trim($n)] = true;
            }
        }
    }
    $lines = [];
    foreach ($sources as $n => $link) {
        if ($cited === null || isset($cited[(string) $n])) {
            $lines[] = "<sup>$n</sup>&nbsp;$link";
        }
    }
    if ($lines) {
        echo '<p class="sources">Sources:<br/>' . "\n" . implode("<br/>\n", $lines) . "</p>\n";
    }
    if ($cited === null || isset($cited['*'])) {
        // Once a page's proof page reasons out every starred note, say so (build_pudding.py writes the marker).
        $reasoned = '';
        if (preg_match('#^/([RFC]_[A-Za-z0-9_]+)$#', $footer_path, $m) && is_file($pf = __DIR__ . '/pudding/' . $m[1] . '.php')
            && strpos(file_get_contents($pf), '<?php /* starred notes reasoned */ ?>') !== false) {
            $reasoned = " The <a href=\"/{$m[1]}/pudding\">proof page</a> gives the reasoning under each one.";
        }
        echo '<p class="sources"><sup>*</sup>&nbsp;I could not find a source for these rulings but kept them in as they fit with my understanding of the game.' . $reasoned . '</p>' . "\n";
    }
}
?>
</footer>
</body>
</html>
