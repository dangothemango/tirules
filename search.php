<?php
// Search (/search?q=...): finds rules, notes and headings on every rule, faction and component
// page, server-side. A small inline script updates the results as the reader types, by fetching
// this same page; without JavaScript the form submits normally. Every word of the query must appear in an item
// (put a phrase in "double quotes" to match it exactly). Case, curly quotes and dashes are ignored.
// Results link to the page, with a text fragment (#:~:text=) that browsers which support it
// scroll to and highlight. The page is not indexed (prefix.php); CloudFront caches each query.
include "prefix.php";

const SEARCH_MAX_RESULTS = 300;

// Lowercase, with curly quotes and dashes made plain, so "jol-nar" matches "Jol&ndash;Nar"
function search_fold(string $s): string
{
    return mb_strtolower(str_replace(['‘', '’', '“', '”', '–', '—'], ["'", "'", '"', '"', '-', '-'], $s));
}

// Markup -> plain text, without citation numbers
function search_text(string $html): string
{
    // A line break shows as " / ", unless the heading already has a slash before it ("Ta Zern /<br/>Agnlan Oln")
    $html = preg_replace(['#<sup>[^<]*</sup>#', '#<span aria-hidden="true">.*?</span>#', '#/\s*<br\s*/?>#', '#<br\s*/?>#'], ['', '', '/ ', ' / '], $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text));
}

// One page's title and searchable items: each heading, paragraph and list item (its own text,
// without nested lists). Related Topics (links to other pages) and HTML comments are left out.
function search_page(string $file): array
{
    $src = file_get_contents($file);
    $src = preg_replace(['#<\?php.*?\?>#s', '#<style\b.*?</style>#s', '#<!--.*?-->#s'], '', $src);
    $title = preg_match('#<h1>(.*?)</h1>#s', $src, $m) ? search_text(preg_replace('#<sub>.*?</sub>#s', '', $m[1])) : basename($file, '.php');
    $article = preg_match('#<article\b[^>]*>(.*)</article>#s', $src, $m) ? $m[1] : '';
    $article = preg_replace('#<h2>Related Topics</h2>.*?(?=<h2|$)#s', '', $article);

    $items = [];
    $section = '';
    $section_removed = false;
    $parts = preg_split('#(<h2\b[^>]*>.*?</h2>|</?(?:li|ol|ul|p)\b[^>]*>)#s', $article, -1, PREG_SPLIT_DELIM_CAPTURE);
    $open = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            if (str_starts_with($part, '<h2')) {
                // Keeps a heading's label, e.g. "Ta Zern (Commander)"
                $section = search_text($part);
                $section_removed = str_contains($part, 'class="removed"');
                if (!in_array($section, ['Rules Reference', 'Notes'], true)) {
                    $items[] = ['section' => '', 'text' => $section, 'heading' => true, 'removed' => $section_removed];
                }
                $open = '';
            } else {
                $open = $part;
            }
            continue;
        }
        $text = search_text($part);
        if ($text !== '' && $open !== '' && $open[1] !== '/') {
            $items[] = [
                'section' => $section,
                'text' => $text,
                'heading' => false,
                'removed' => $section_removed || str_contains($open, 'class="removed"') || str_contains($part, 'class="removed"'),
            ];
        }
    }
    return ['title' => $title, 'items' => $items];
}

// The query's words, and "quoted phrases", folded
function search_terms(string $q): array
{
    preg_match_all('/"([^"]+)"|(\S+)/u', str_replace(['“', '”'], '"', $q), $m, PREG_SET_ORDER);
    $terms = [];
    foreach ($m as $t) {
        $term = search_fold(trim($t[1] !== '' ? $t[1] : $t[2], " \t.,;:!?"));
        if ($term !== '' && !in_array($term, $terms, true)) {
            $terms[] = $term;
        }
    }
    return array_slice($terms, 0, 8);
}

// The text, escaped, with each term wrapped in <mark>
function search_highlight(string $text, array $terms): string
{
    $alts = [];
    foreach ($terms as $term) {
        $alts[] = strtr(preg_quote($term, '/'), ["'" => "['‘’]", '"' => '["“”]', '\-' => '[\-–—]']);
    }
    $parts = preg_split('/(' . implode('|', $alts) . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $i => $part) {
        $out .= $i % 2 ? '<mark>' . htmlspecialchars($part) . '</mark>' : htmlspecialchars($part);
    }
    return $out;
}

// Text fragment that scrolls to the item: its whole text, or its first and last few words
function search_fragment(string $text): string
{
    $enc = fn($s) => str_replace('-', '%2D', rawurlencode($s));
    $words = explode(' ', $text);
    if (count($words) <= 10) {
        return '#:~:text=' . $enc($text);
    }
    return '#:~:text=' . $enc(implode(' ', array_slice($words, 0, 5))) . ',' . $enc(implode(' ', array_slice($words, -5)));
}

$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$terms = search_terms($q);

$results = [];
$total = 0;
if ($terms) {
    $files = array_merge(glob(__DIR__ . '/R_*.php'), glob(__DIR__ . '/F_*.php'), glob(__DIR__ . '/C_*.php'));
    foreach ($files as $order => $file) {
        $page = search_page($file);
        $title_match = true;
        foreach ($terms as $term) {
            $title_match = $title_match && str_contains(search_fold($page['title']), $term);
        }
        $hits = [];
        $score = $title_match ? 100 : 0;
        $folded_title = search_fold($page['title']);
        foreach ($page['items'] as $item) {
            // Every term must be in the item, its heading or the page title ("jol-nar commander"),
            // and at least one in the item itself, so a page-title search doesn't list every item
            $folded = search_fold($item['text']);
            $context = search_fold($item['section']) . ' ' . $folded_title;
            $own = false;
            foreach ($terms as $term) {
                if (str_contains($folded, $term)) {
                    $own = true;
                } elseif (!str_contains($context, $term)) {
                    continue 2;
                }
            }
            if (!$own) {
                continue;
            }
            $hits[] = $item;
            $score += $item['heading'] ? 10 : 1;
        }
        if ($hits || $title_match) {
            $results[] = ['path' => '/' . basename($file, '.php'), 'title' => $page['title'], 'hits' => $hits, 'score' => $score, 'order' => $order];
            $total += count($hits);
        }
    }
    usort($results, fn($a, $b) => [$b['score'], $a['order']] <=> [$a['score'], $b['order']]);
}
?>
<header><h1>Search</h1></header>
<article>
<form class="search" method="get" action="/search" role="search">
<label for="search-q">Search the rules, notes and card names</label>
<input type="search" id="search-q" name="q" value="<?php echo htmlspecialchars($q); ?>" maxlength="100"<?php if ($q === '') echo ' autofocus'; ?>/>
<button type="submit">Search</button>
</form>
<div id="search-results">
<?php if ($q === '') { ?>
<p>Every word must appear in a rule or note for it to match. Put a phrase in &ldquo;double quotes&rdquo; to match it exactly.</p>
<?php } elseif (!$results) { ?>
<p>Nothing matches &ldquo;<?php echo htmlspecialchars($q); ?>&rdquo;. Try fewer or shorter words, or browse the <a href="/">rule topics</a>.</p>
<?php } else {
    echo '<p>' . $total . ' match' . ($total === 1 ? '' : 'es') . ' on ' . count($results) . ' page' . (count($results) === 1 ? '' : 's') . '.'
        . ($total > SEARCH_MAX_RESULTS ? ' Showing the first ' . SEARCH_MAX_RESULTS . '; add words to narrow the search.' : '') . "</p>\n";
    $shown = 0;
    foreach ($results as $r) {
        if ($shown >= SEARCH_MAX_RESULTS) {
            break;
        }
        echo '<h2><a href="' . $r['path'] . '">' . search_highlight($r['title'], $terms) . "</a></h2>\n";
        if (!$r['hits']) {
            continue;
        }
        echo "<ul class=\"search-results\">\n";
        foreach ($r['hits'] as $hit) {
            if ($shown++ >= SEARCH_MAX_RESULTS) {
                break;
            }
            $where = $hit['heading'] ? '' : ($hit['section'] !== '' ? '<span class="search-where">' . htmlspecialchars($hit['section']) . ':</span> ' : '');
            $text = search_highlight($hit['text'], $terms);
            echo '    <li' . ($hit['removed'] && !$is_lite ? ' class="removed"' : '') . '>' . $where
                // A two-line heading ("Ta Zern / Agnlan Oln"): a text fragment can't span the line break, so use the first line
                . '<a href="' . $r['path'] . htmlspecialchars(search_fragment($hit['heading'] ? explode(' / ', $hit['text'])[0] : $hit['text'])) . '">'
                . ($hit['heading'] ? '<b>' . $text . '</b>' : $text) . "</a></li>\n";
        }
        echo "</ul>\n";
    }
} ?>
</div>
<?php if (!$is_lite) { // live results as the reader types; without JavaScript the form submits as usual (the lite site has no script) ?>
<script>
(function () {
    var input = document.getElementById('search-q'), timer, ctrl;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var q = input.value.trim(), url = '/search' + (q ? '?q=' + encodeURIComponent(q) : '');
            if (ctrl) ctrl.abort();
            ctrl = new AbortController();
            fetch(url, {signal: ctrl.signal}).then(function (r) { return r.text(); }).then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('search-results');
                if (fresh) {
                    document.getElementById('search-results').replaceWith(fresh);
                    history.replaceState(null, '', url);
                }
            }).catch(function () {});
        }, 150);
    });
})();
</script>
<?php } ?>
</article>
<?php include "suffix.php"?>
