#!/usr/bin/env python3
"""File hand made demo images into place.

    python3 docs/tools/importimg.py ~/Downloads/demo-shots [manifest]

Each file only has to start with its shot number in the manifest
(docs/tools/store-demo-shots.json unless another is given), so "07.png",
"07 (1).jpeg" and "07-hero.jpg" all work. The importer moves it to the
path the manifest names and reports anything it could not place.
"""
import json, pathlib, re, shutil, sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
manifest = pathlib.Path(sys.argv[2]).expanduser() if len(sys.argv) > 2 else ROOT / "docs/tools/store-demo-shots.json"
data = json.loads(manifest.read_text())
shots = data["shots"] if isinstance(data, dict) else data

src = pathlib.Path(sys.argv[1]).expanduser() if len(sys.argv) > 1 else None
if not src or not src.is_dir():
    sys.exit("give the folder holding the downloaded images")

placed, skipped = 0, []
for f in sorted(src.iterdir()):
    if f.is_dir() or f.suffix.lower() not in {".png", ".jpg", ".jpeg", ".webp"}:
        continue
    m = re.match(r"0*(\d+)", f.name)
    if not m or not (1 <= int(m.group(1)) <= len(shots)):
        skipped.append(f"{f.name}: no shot number at the start")
        continue
    shot = shots[int(m.group(1)) - 1]
    dest = ROOT / shot["path"]
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest = dest.with_suffix(f.suffix.lower().replace(".jpeg", ".jpg"))
    shutil.copy2(f, dest)
    placed += 1
    print(f"  {f.name}  ->  {str(dest.relative_to(ROOT)).split('/demo/')[1]}")

print(f"\nplaced {placed}")
for s in skipped:
    print("  skipped:", s)
have = sum(1 for s in shots if any((ROOT / s["path"]).with_suffix(e).exists() for e in (".jpg", ".png", ".webp")))
print(f"{have} of {len(shots)} shots now present")
