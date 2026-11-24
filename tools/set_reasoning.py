#!/usr/bin/env python3
"""Replace the N proof line(s) of starred notes with R (reasoning) lines.

Usage (repo root):
    ./tools/set_reasoning.py <page> <file.json>

file.json maps each note's @n (as listed by ./tools/starred_todo.py <page>) to its reasoning
text, without the leading "R ": {"3": "Stymie: \"...\" Activation alone triggers it."}.
Every key must have an N line. Then run ./tools/build_pudding.py <page>.
"""
import json, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def main():
    page, R = sys.argv[1], {str(k): v for k, v in json.load(open(sys.argv[2], encoding="utf-8")).items()}
    path = os.path.join(ROOT, "tools", "pudding", page + ".txt")
    out, cur, done = [], None, set()
    for line in open(path, encoding="utf-8").read().split("\n"):
        m = re.match(r"@(r?\d+)$", line)
        if m:
            cur = m.group(1)
        if cur in R and line.startswith("N"):
            if cur not in done:
                out.append("R " + R[cur])
                done.add(cur)
            continue
        out.append(line)
    missing = set(R) - done
    if missing:
        sys.exit(f"{page}: no N line for @{', @'.join(sorted(missing))}; nothing written")
    open(path, "w", encoding="utf-8").write("\n".join(out))
    print(f"{page}: {len(done)} reasoning lines")

if __name__ == "__main__":
    main()
