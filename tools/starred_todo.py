#!/usr/bin/env python3
"""List starred notes (<sup>*</sup>) whose proofs have no R (reasoning) line yet.

Usage (repo root):
    ./tools/starred_todo.py              # count per page, then the total
    ./tools/starred_todo.py F_arborec    # the open notes on one page, with their @n
"""
import os, re, sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import build_pudding as bp

def todo(page):
    _, records = bp.build(page, write=False)
    out = []
    for k, (text, proofs) in enumerate(records):
        if text.rstrip().endswith("<sup>*</sup>") and not any(p[0] == "R" for p in proofs):
            out.append((k, text))
    return out

def note_keys(page):
    """records list Rules Reference items (@r0...) before notes (@0...); map record index to its @ key."""
    _, records = bp.build(page, write=False)
    s = open(os.path.join(bp.ROOT, page + ".php"), encoding="utf-8").read()
    first_note = s.find('<ol class="note">')
    rules = s[s.index("<article>"):first_note if first_note >= 0 else len(s)]
    nr = len(re.findall(r"<(li|p)[^>]*>((?:(?!</?(?:li|p)[ >]).)*?<sup>[^<]*</sup>)\s*</(?:li|p)>", rules, re.S))
    return [f"r{i}" if i < nr else str(i - nr) for i in range(len(records))]

if __name__ == "__main__":
    pages = sys.argv[1:] or sorted(f[:-4] for f in os.listdir(os.path.join(bp.ROOT, "tools", "pudding")) if f.endswith(".txt"))
    total = 0
    for page in pages:
        items = todo(page)
        total += len(items)
        if len(sys.argv) > 1:
            keys = note_keys(page)
            for k, text in items:
                print(f"@{keys[k]}\t{re.sub(r'<[^>]+>', '', text)}")
        elif items:
            print(f"{len(items):4}  {page}")
    print(f"{total:4}  total")
