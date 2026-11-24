#!/usr/bin/env python3
"""Look up the text that R (reasoning) proofs quote: LRR sections and Twilight Imperium Wiki card text.

Downloads are cached in tools/.cache/ (gitignored). Needs pdftotext (poppler) for the LRR.

Usage (repo root):
    ./tools/evidence.py lrr 67 68          # print LRR sections 67 and 68
    ./tools/evidence.py lrr-num 68.3       # the line of the layout text that starts with rule 68.3
    ./tools/evidence.py lrr-grep 'other players. ships'
    ./tools/evidence.py wiki The_Arborec [regex]   # wiki page text (links flattened), or matching lines

In the section text, sub-rule numbers (67.3, 67.4 ...) often sit on lines of their own above the
paragraphs, so confirm a sub-rule's number with lrr-num before citing it.
"""
import json, os, re, subprocess, sys, urllib.parse, urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CACHE = os.path.join(ROOT, "tools", ".cache")
LRR_URL = re.search(r"1 => '<a href=\"([^\"]+)\"", open(os.path.join(ROOT, "sources.php"), encoding="utf-8").read()).group(1)

def fetch(url, path):
    if not os.path.exists(path):
        os.makedirs(CACHE, exist_ok=True)
        req = urllib.request.Request(url, headers={"User-Agent": "tirules-evidence/1.0"})
        with urllib.request.urlopen(req, timeout=60) as r, open(path, "wb") as f:
            f.write(r.read())
    return path

def lrr_text():
    """(columns, layout): the LRR read one column at a time (paragraphs intact), and the layout text."""
    cols, lay = os.path.join(CACHE, "lrr_cols.txt"), os.path.join(CACHE, "lrr_layout.txt")
    if not os.path.exists(cols):
        pdf = fetch(LRR_URL, os.path.join(CACHE, "lrr.pdf"))
        pages = int(re.search(r"Pages:\s+(\d+)", subprocess.run(["pdfinfo", pdf], capture_output=True, text=True).stdout).group(1))
        out = []
        for p in range(1, pages + 1):
            for x in (0, 306):
                out.append(subprocess.run(["pdftotext", "-f", str(p), "-l", str(p), "-x", str(x), "-y", "0", "-W", "306", "-H", "792", pdf, "-"],
                                          capture_output=True, text=True).stdout)
        open(cols, "w", encoding="utf-8").write("".join(out))
        subprocess.run(["pdftotext", "-layout", pdf, lay], check=True)
    return open(cols, encoding="utf-8").read().split("\n"), open(lay, encoding="utf-8").read()

def sections(lines):
    starts = {}
    for i, l in enumerate(lines):
        m = re.match(r"^(\d{1,3})(?: ([A-Z][A-Z ,’'()-]+))?$", l.strip())
        if not m or int(m.group(1)) > 101 or i < 600:  # skip the contents pages
            continue
        title = m.group(2)
        if not title:
            nxt = [x.strip() for x in lines[i + 1:i + 4] if x.strip()]
            if nxt and re.match(r"^[A-Z][A-Z ,’'()-]{3,}$", nxt[0]):
                title = nxt[0]
        if title and int(m.group(1)) not in starts:
            starts[int(m.group(1))] = (i, title)
    return starts

def main():
    cmd, args = sys.argv[1], sys.argv[2:]
    if cmd == "lrr":
        lines, _ = lrr_text()
        starts = sections(lines)
        for a in args:
            i, title = starts[int(a)]
            j = min([v[0] for v in starts.values() if v[0] > i] + [len(lines)])
            print(f"######## {a} {title}")
            print("\n".join(x for x in lines[i + 1:j] if x.strip()))
    elif cmd == "lrr-num":
        _, lay = lrr_text()
        for a in args:
            for m in re.finditer(r"(?m)(?:^|\s{2,})" + re.escape(a) + r"\s{1,4}(\S.{0,100})", lay):
                print(a, m.group(1).split("   ")[0])
    elif cmd == "lrr-grep":
        lines, _ = lrr_text()
        for i, l in enumerate(lines):
            if re.search(args[0], l, re.I):
                print("\n".join(lines[max(0, i - 2):i + 4]) + "\n--")
    elif cmd == "wiki":
        page = args[0]
        path = os.path.join(CACHE, "wiki_" + re.sub(r"[^A-Za-z0-9_.-]", "_", page) + ".json")
        fetch("https://twilight-imperium.fandom.com/api.php?action=parse&format=json&prop=wikitext&page="
              + urllib.parse.quote(page), path)
        data = json.load(open(path, encoding="utf-8"))
        if "parse" not in data:
            os.remove(path)  # don't cache an error response
            sys.exit(f"wiki: no page {page!r} ({data.get('error', {}).get('info', 'unknown error')}); "
                     "titles are case-sensitive, e.g. The_Vuil'Raith_Cabal")
        t = data["parse"]["wikitext"]["*"]
        t = re.sub(r"<br ?/?>", " ", re.sub(r"\[\[(?:[^|\]]*\|)?([^\]]*)\]\]", r"\1", t))
        for l in t.split("\n"):
            if l.strip() and (len(args) < 2 or re.search(args[1], l, re.I)):
                print(l)
    else:
        sys.exit(__doc__)

if __name__ == "__main__":
    main()
