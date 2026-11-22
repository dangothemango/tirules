<?php
// Dark mode toggle, shared by prefix.php and index.php. Echoes the <head> script;
// the caller places $theme_toggle_button inside <header>.
$theme_toggle_button = '<button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle dark mode" title="Toggle dark mode">&#9680;</button>'
    // Lightweight mode: plain text rulings, no links, citations, red, strike-through or sources
    . '<button type="button" class="lightweight-toggle" onclick="toggleLightweight()" aria-label="Toggle lightweight mode" title="Toggle lightweight mode">'
    . '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . '<path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"/><path d="M16 8 2 22M17.5 15H9"/>'
    . '</svg></button>';
// Bug-shaped "report a problem" link, top left of the header (opposite the toggle).
// Plain link, so it works without JavaScript.
$report_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$report_button = '<a class="report-button" href="/report'
    . ($report_path !== '/report' ? '?page=' . rawurlencode($report_path) : '')
    . '" aria-label="Report a problem with this page" title="Report a problem with this page">'
    . '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">'
    . '<path d="M9 6.5a3 3 0 0 1 6 0"/><rect x="7" y="7" width="10" height="13" rx="5"/>'
    . '<path d="M12 11v9M7 11H3.5M17 11h3.5M7 15.5H4M17 15.5h3M7.5 19 5 21.5M16.5 19l2.5 2.5M10 4.5 8.5 3M14 4.5 15.5 3"/>'
    . '</svg></a>';
?>
<script>
try { var t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
try { if (localStorage.getItem('mode') == 'lightweight') document.documentElement.dataset.mode = 'lightweight'; } catch (e) {}
function toggleTheme()
{
    var d = document.documentElement;
    var c = d.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    d.dataset.theme = c == 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('theme', d.dataset.theme); } catch (e) {}
}
function toggleLightweight()
{
    var d = document.documentElement;
    if (d.dataset.mode == 'lightweight') delete d.dataset.mode; else d.dataset.mode = 'lightweight';
    try { localStorage.setItem('mode', d.dataset.mode || ''); } catch (e) {}
}
</script>
<noscript><style>.theme-toggle, .lightweight-toggle { display: none; }</style></noscript>
