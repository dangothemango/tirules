#!/bin/sh
# Rebuild the web fonts in fonts/ from the masters in tools/fonts/.
# Needs fontTools with brotli (pip install fonttools brotli); a dev tool only, the site
# serves the committed .woff2 files.
# The subset keeps ASCII, Latin-1 punctuation and symbols, Greek, general punctuation,
# the trademark sign and the minus sign. The site uses no accented letters; if a page
# starts to, add their range (for example U+00C0-00FF) here and rebuild.
# fonts/ is cached for a year (immutable), so give a changed font a new file name and
# update its url in style.css.
set -e
cd "$(dirname "$0")/.."
UNICODES="U+0020-007E,U+00A0-00BF,U+00D7,U+00F7,U+0370-03FF,U+2000-206F,U+2122,U+2212"
for pair in Helv.otf:helv HelvIt.otf:helv-italic HelvBd.otf:helv-bold Slider.otf:slider LazBold.ttf:lazax; do
    pyftsubset "tools/fonts/${pair%%:*}" --unicodes="$UNICODES" --layout-features='*' \
        --flavor=woff2 --output-file="fonts/${pair##*:}.woff2"
done
ls -l fonts
