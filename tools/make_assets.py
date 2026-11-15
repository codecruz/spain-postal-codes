#!/usr/bin/env python3
"""Generate the images used when the site is shared: Open Graph card, GitHub
social preview and icons.

    python3 tools/make_assets.py

Needs Google Chrome or Chromium (headless) to render, and Pillow to check the
sizes. The output goes to scripts/templates/assets/, which build_site.py copies
to site/assets/. The counts come from data/metadata.json, so run it again after
a data update that changes them.
"""

from __future__ import annotations

import csv
import html
import json
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "scripts" / "templates" / "assets"

TITLE = "Códigos postales de España"
SUBTITLE = "Dataset abierto, API JSON estática, npm y PHP. Con datos oficiales del INE."
URL = "codecruz.github.io/spain-postal-codes"

# The same palette as scripts/templates/index.html (light theme).
BG = "#fbfaf8"
SURFACE = "#ffffff"
TEXT = "#1d1d1f"
MUTED = "#5f6368"
BORDER = "#e3e1dc"
ACCENT = "#b3261e"
CODE_BG = "#f3f1ed"
MONO = '"DejaVu Sans Mono", "Ubuntu Mono", ui-monospace, monospace'
SANS = '"Ubuntu Sans", "Noto Sans", system-ui, sans-serif'

# The icon: a white map pin on the accent colour.
ICON_SVG = f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <rect width="64" height="64" rx="14" fill="{ACCENT}"/>
  <path d="M32 10c-9.4 0-17 7.3-17 16.4C15 38.6 32 54 32 54s17-15.4 17-27.6C49 17.3 41.4 10 32 10z" fill="#fff"/>
  <circle cx="32" cy="26.5" r="6.5" fill="{ACCENT}"/>
</svg>
"""

# A postal code that serves municipalities in two provinces: the case the
# dataset handles and most lists get wrong.
EXAMPLE_CP = "01427"


def thousands(n: int) -> str:
    return f"{n:,}".replace(",", ".")


def example() -> str:
    with open(ROOT / "data" / "codigos_postales.csv", encoding="utf-8", newline="") as f:
        rows = [r for r in csv.DictReader(f) if r["cod_postal"] == EXAMPLE_CP]
    items = [(r["municipio"], r["provincia"]) for r in sorted(rows, key=lambda r: r["municipio_id"])]
    lines = [
        f'<span class="p">$</span> curl …/v1/cp/{EXAMPLE_CP}.json',
        "",
        f'<span class="k">"cod_postal"</span>: <span class="s">"{EXAMPLE_CP}"</span>',
        '<span class="k">"municipios"</span>: [',
    ]
    for name, province in items:
        lines.append(f'  <span class="s">{html.escape(name)}</span> <span class="m">· {html.escape(province)}</span>')
    lines.append("]")
    return "\n".join(lines)


def card_page(width: int, height: int, counts: dict) -> str:
    pad = 72
    stats = "".join(
        f"<div><b>{thousands(value)}</b><span>{label}</span></div>"
        for value, label in (
            (counts["postal_codes"], "códigos postales"),
            (counts["municipalities"], "municipios"),
            (counts["provinces"], "provincias"),
        )
    )
    return f"""<!doctype html><html><head><meta charset="utf-8"><style>
  * {{ box-sizing: border-box; margin: 0; }}
  html, body {{ width: {width}px; height: {height}px; overflow: hidden; }}
  body {{ background: {BG}; color: {TEXT}; font-family: {SANS}; position: relative; }}
  body::before {{ content: ""; position: absolute; inset: 0 0 auto 0; height: 10px; background: {ACCENT}; }}
  .main {{ position: absolute; inset: {pad}px {pad}px {pad + 8}px; display: flex; align-items: center; gap: 52px; }}
  .text {{ width: 50%; flex: none; }}
  .brand {{ display: flex; align-items: center; gap: 14px; font: 600 26px {MONO}; margin-bottom: 28px; }}
  .brand svg {{ width: 44px; height: 44px; }}
  h1 {{ font-size: {56 if width < 1250 else 60}px; line-height: 1.08; letter-spacing: -0.02em; font-weight: 700; }}
  p {{ font-size: 23px; line-height: 1.45; color: {MUTED}; margin-top: 20px; }}
  .stats {{ display: flex; gap: 34px; margin-top: 30px; }}
  .stats b {{ display: block; font-size: 34px; font-variant-numeric: tabular-nums; color: {ACCENT}; }}
  .stats span {{ font-size: 18px; color: {MUTED}; }}
  .card {{ flex: 1; min-width: 0; background: {SURFACE}; border: 1px solid {BORDER}; border-radius: 18px;
          overflow: hidden; box-shadow: 0 18px 40px -24px rgba(29, 29, 31, .35); }}
  .head {{ display: flex; gap: 8px; padding: 14px 18px; border-bottom: 1px solid {BORDER}; }}
  .head i {{ width: 12px; height: 12px; border-radius: 50%; background: {BORDER}; }}
  pre {{ font: 17px/1.8 {MONO}; padding: 22px 24px; background: {CODE_BG}; white-space: pre; overflow: hidden; }}
  .p, .m {{ color: {MUTED}; }} .k {{ color: {TEXT}; }} .s {{ color: {ACCENT}; }}
  .url {{ position: absolute; left: {pad}px; bottom: {pad // 2 - 6}px; font: 20px {MONO}; color: {MUTED}; }}
  .url b {{ color: {ACCENT}; font-weight: 600; }}
</style></head><body>
  <div class="main">
    <div class="text">
      <div class="brand">{ICON_SVG}spain-postal-codes</div>
      <h1>{html.escape(TITLE)}</h1>
      <p>{html.escape(SUBTITLE)}</p>
      <div class="stats">{stats}</div>
    </div>
    <div class="card"><div class="head"><i></i><i></i><i></i></div><pre>{example()}</pre></div>
  </div>
  <div class="url"><b>→</b> {URL}</div>
</body></html>"""


def icon_page(size: int) -> str:
    return f"""<!doctype html><html><head><meta charset="utf-8"><style>
  * {{ margin: 0; }} html, body {{ width: {size}px; height: {size}px; overflow: hidden; background: transparent; }}
  svg {{ display: block; width: {size}px; height: {size}px; }}
</style></head><body>{ICON_SVG}</body></html>"""


def find_chrome() -> str:
    for name in ("google-chrome", "google-chrome-stable", "chromium", "chromium-browser", "chrome"):
        path = shutil.which(name)
        if path:
            return path
    sys.exit("Google Chrome or Chromium is needed to render the images.")


def render(chrome: str, page: str, out: Path, width: int, height: int, tmp: Path) -> None:
    src = tmp / (out.stem + ".html")
    src.write_text(page, encoding="utf-8")
    subprocess.run(
        [
            chrome,
            "--headless=new",
            "--disable-gpu",
            "--hide-scrollbars",
            "--force-device-scale-factor=1",
            "--default-background-color=00000000",
            f"--window-size={width},{height}",
            f"--screenshot={out}",
            src.as_uri(),
        ],
        check=True,
        capture_output=True,
    )


def main() -> None:
    from PIL import Image

    counts = json.loads((ROOT / "data" / "metadata.json").read_text(encoding="utf-8"))["counts"]
    chrome = find_chrome()
    OUT.mkdir(parents=True, exist_ok=True)
    jobs = [
        ("og-image.png", card_page(1200, 630, counts), 1200, 630),
        ("social-preview.png", card_page(1280, 640, counts), 1280, 640),
        ("apple-touch-icon.png", icon_page(180), 180, 180),
        ("icon-192.png", icon_page(192), 192, 192),
        ("icon-512.png", icon_page(512), 512, 512),
    ]
    with tempfile.TemporaryDirectory() as tmp:
        for name, page, width, height in jobs:
            out = OUT / name
            render(chrome, page, out, width, height, Path(tmp))
            with Image.open(out) as img:
                if img.size != (width, height):
                    sys.exit("%s is %dx%d, expected %dx%d" % (name, *img.size, width, height))
                if name.startswith(("og-", "social-")):
                    img.convert("RGB").save(out, optimize=True)
            print("%-22s %dx%d" % (name, width, height))
    (OUT / "icon.svg").write_text(ICON_SVG, encoding="utf-8")
    print("icon.svg")


if __name__ == "__main__":
    main()
