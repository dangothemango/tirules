#!/usr/bin/env python3
"""Write sources/<n>.php (served at /sources/<n>, not indexed) for each source in sources.php:
every Rules Reference item and note that source proves, with its proof lines, grouped under an
<h2> linking to each page in home page nav order. Run from the repo root:
./tools/build_sources.py (build_pudding.py also runs it after building proof pages).
"""
import os, re, sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import build_pudding as bp

ROOT = bp.ROOT


def main():
    src = open(os.path.join(ROOT, "sources.php"), encoding="utf-8").read()
    sources = {int(n): (link, name) for n, link, name in
               re.findall(r"(\d+) => '(<a href=\"[^\"]+\"[^>]*>(.*?)</a>)'", src)}
    nav = open(os.path.join(ROOT, "index.php"), encoding="utf-8").read()
    pages = [p for p in dict.fromkeys(re.findall(r'href="/([RFC]_[A-Za-z0-9_]+)"', nav))
             if os.path.isfile(os.path.join(ROOT, "tools", "pudding", p + ".txt"))]
    built = [(p, *bp.build(p, write=False)) for p in pages]
    os.makedirs(os.path.join(ROOT, "sources"), exist_ok=True)
    for n, (link, name) in sources.items():
        body, count = [], 0
        for page, title, records in built:
            items = []
            for text, proofs in records:
                mine = [bp.proof_li(*pr) for pr in proofs if pr[1] == str(n)]
                if mine:
                    items.append("    <li>" + text + "\n        <ul>\n        "
                                 + "\n        ".join(mine) + "\n        </ul></li>")
            if items:
                count += len(items)
                body.append(f'<h2><a href="/{page}">{title}</a></h2>\n<ul>\n' + "\n".join(items) + "\n</ul>")
        out = ('<?php include __DIR__ . "/../prefix.php"?>\n'
               f"<header><h1>{name}: Notes</h1></header>\n<article>\n"
               f"<p>Every note on this site that {link} supports, with the text it is quoted for, "
               "grouped by page. <b>Explicit</b> sources state the note directly. "
               "<b>Implicit</b> sources support it without stating it.</p>\n"
               + "\n".join(body) + '\n</article>\n<?php include __DIR__ . "/../suffix.php"?>\n')
        open(os.path.join(ROOT, "sources", f"{n}.php"), "w", encoding="utf-8").write(out)
        print(f"sources/{n}.php: {count} notes")


if __name__ == "__main__":
    main()
