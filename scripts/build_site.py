#!/usr/bin/env python3
"""Build the static JSON API (served from GitHub Pages) from data/codigos_postales.csv.

Layout of the generated site:

    index.html                     documentation and live lookup
    v1/meta.json                   source period, license and counts
    v1/cp/{cod_postal}.json        municipalities served by a postal code
    v1/municipios.json             every municipality (id, name, province)
    v1/municipios/{id}.json        a municipality and its postal codes
    v1/provincias.json             the 52 provinces with counts
    v1/provincias/{id}.json        a province and its municipalities
    data/*.csv, data/*.json        the raw dataset

Usage: python3 scripts/build_site.py [--data data] [--out site]
"""

from __future__ import annotations

import argparse
import csv
import html
import json
import shutil
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TEMPLATE = Path(__file__).resolve().parent / "templates" / "index.html"


def write_json(path: Path, payload: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")


def build(data_dir: Path, out_dir: Path) -> dict:
    with open(data_dir / "codigos_postales.csv", encoding="utf-8", newline="") as f:
        rows = list(csv.DictReader(f))
    with open(data_dir / "provincias.csv", encoding="utf-8", newline="") as f:
        provinces = {r["provincia_id"]: r["provincia"] for r in csv.DictReader(f)}
    metadata = json.loads((data_dir / "metadata.json").read_text(encoding="utf-8"))

    if out_dir.exists():
        shutil.rmtree(out_dir)
    api = out_dir / "v1"

    by_cp: dict[str, list[dict]] = defaultdict(list)
    municipalities: dict[str, dict] = {}
    for r in rows:
        by_cp[r["cod_postal"]].append({
            "id": r["municipio_id"], "nombre": r["municipio"],
            "provincia_id": r["provincia_id"], "provincia": r["provincia"],
        })
        m = municipalities.setdefault(r["municipio_id"], {
            "id": r["municipio_id"], "nombre": r["municipio"],
            "provincia": {"id": r["provincia_id"], "nombre": r["provincia"]},
            "codigos_postales": [],
        })
        m["codigos_postales"].append(r["cod_postal"])

    for cod_postal, items in by_cp.items():
        write_json(api / "cp" / f"{cod_postal}.json", {"cod_postal": cod_postal, "municipios": sorted(items, key=lambda i: i["id"])})

    for m in municipalities.values():
        m["codigos_postales"].sort()
        write_json(api / "municipios" / f"{m['id']}.json", m)

    write_json(api / "municipios.json", [
        {"id": m["id"], "nombre": m["nombre"], "provincia_id": m["provincia"]["id"]}
        for m in sorted(municipalities.values(), key=lambda m: m["id"])
    ])

    summary = []
    for provincia_id, nombre in provinces.items():
        members = sorted((m for m in municipalities.values() if m["provincia"]["id"] == provincia_id), key=lambda m: m["nombre"])
        codes = {cp for m in members for cp in m["codigos_postales"]}
        summary.append({"id": provincia_id, "nombre": nombre, "municipios": len(members), "codigos_postales": len(codes)})
        write_json(api / "provincias" / f"{provincia_id}.json", {
            "id": provincia_id,
            "nombre": nombre,
            "municipios": [{"id": m["id"], "nombre": m["nombre"], "codigos_postales": m["codigos_postales"]} for m in members],
        })
    write_json(api / "provincias.json", summary)
    write_json(api / "meta.json", metadata)

    downloads = out_dir / "data"
    downloads.mkdir(parents=True)
    for name in ("codigos_postales.csv", "provincias.csv", "metadata.json"):
        shutil.copy2(data_dir / name, downloads / name)

    counts = metadata["counts"]
    page = TEMPLATE.read_text(encoding="utf-8")
    for key, value in {
        "PERIOD": metadata["source"]["period"],
        "REFERENCE_DATE": metadata["source"]["reference_date"],
        "ROWS": f"{counts['rows']:,}".replace(",", "."),
        "POSTAL_CODES": f"{counts['postal_codes']:,}".replace(",", "."),
        "MUNICIPALITIES": f"{counts['municipalities']:,}".replace(",", "."),
        "ATTRIBUTION": metadata["attribution"],
    }.items():
        page = page.replace("{{" + key + "}}", html.escape(str(value)))
    (out_dir / "index.html").write_text(page, encoding="utf-8")
    (out_dir / ".nojekyll").write_text("", encoding="utf-8")  # serve files as-is

    return {"postal_codes": len(by_cp), "municipalities": len(municipalities)}


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Build the static JSON API.")
    parser.add_argument("--data", type=Path, default=ROOT / "data")
    parser.add_argument("--out", type=Path, default=ROOT / "site")
    args = parser.parse_args(argv)

    result = build(args.data, args.out)
    print(f"Built {result['postal_codes']} postal code and {result['municipalities']} municipality endpoints in {args.out}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
