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
$sources = [
    1 => '<a href="https://images-cdn.fantasyflightgames.com/filer_public/51/55/51552c7f-c05c-445b-84bf-4b073456d008/ti10_pok_living_rules_reference_20_web.pdf" target="_blank" rel="noopener">Living Rules Reference v2.0</a>',
    2 => '<a href="https://www.rulespal.com/twilight-imperium-4/faq" target="_blank" rel="noopener">RulesPal TI4 FAQ (community consensus and designer rulings)</a>',
    3 => '<a href="https://cdn.svc.asmodee.net/production-fantasyflightgames/uploads/2026/08/ti-k0289_learn_to_play_2020_web.pdf" target="_blank" rel="noopener">Learn to Play</a>',
    4 => '<a href="https://docs.google.com/document/d/1_93kw7KkzvAXoQAxRioxo7iAt-Ar6BMIxdNf4Lv25rI/edit" target="_blank" rel="noopener">The Unofficially Official FAQ and Errata (PoK v3.1.1)</a>',
    5 => '<a href="https://docs.google.com/document/d/1Bgl9OpP-162sifCcratI9RtbOSTNXtFGnKEU_-89rZI/edit" target="_blank" rel="noopener">Compiled Designer Rulings (2018&ndash;2019)</a>',
    6 => '<a href="https://docs.google.com/document/d/1Un6F7oDY-b5oUx2RIbGH7644HJvb9jF1wtM399FuFts/edit" target="_blank" rel="noopener">Rules-as-Written &amp; Community Consensus Rulings (unofficial)</a>',
    7 => '<a href="https://cdn.svc.asmodee.net/production-fantasyflightgames/uploads/2026/08/te_rulebook_web.pdf" target="_blank" rel="noopener">Thunder&rsquo;s Edge Rulebook</a>',
    8 => '<a href="https://cdn.svc.asmodee.net/production-fantasyflightgames/uploads/2026/08/ti10_rulebook_web-good.pdf" target="_blank" rel="noopener">Prophecy of Kings Rulebook</a>',
    9 => '<a href="https://docs.google.com/document/d/11_D2URJTHozgqnOUv8AKHmh4E5DFFaC-AALy3YhBqsc/edit" target="_blank" rel="noopener">Designer Q&amp;A</a>',
    10 => '<a href="https://cdn.svc.asmodee.net/production-fantasyflightgames/uploads/2026/08/ti_codex_1_rules_compressed.pdf" target="_blank" rel="noopener">Twilight Codex Vol. I</a>',
    11 => '<a href="https://cdn.svc.asmodee.net/production-fantasyflightgames/uploads/2026/08/ti_codex_4_rules.pdf" target="_blank" rel="noopener">Twilight Codex Vol. IV: Liberation</a>',
    12 => '<a href="https://cdn.svc.asmodee.net/production-fantasyflightgames/uploads/2026/08/ti_codex_volume_3_vigil_v2_1-compressed.pdf" target="_blank" rel="noopener">Twilight Codex Vol. III: Vigil</a>',
    13 => '<a href="https://twilight-imperium.fandom.com/wiki/Thunder%27s_Edge" target="_blank" rel="noopener">Thunder&rsquo;s Edge revised strategy cards (card text as transcribed on the Twilight Imperium Wiki)</a>',
    14 => '<a href="https://cdn.svc.asmodee.net/production-fantasyflightgames/uploads/2026/08/ti_codex_ii_rules_web.pdf" target="_blank" rel="noopener">Twilight Codex Vol. II: Affinity</a>',
    15 => '<a href="https://cdn.svc.asmodee.net/production-fantasyflightgames/uploads/2026/08/codex_45_technology_card_updates.pdf" target="_blank" rel="noopener">Codex 4.5 Technology Card Updates</a>',
];
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
        echo '<p class="sources"><sup>*</sup>&nbsp;I could not find a source for these rulings but kept them in as they fit with my understanding of the game.</p>' . "\n";
    }
}
?>
</footer>
</body>
</html>
