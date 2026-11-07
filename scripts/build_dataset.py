#!/usr/bin/env python3
"""Build the postal code dataset from official INE (Instituto Nacional de Estadística) sources.

Sources (CC BY 4.0, https://www.ine.es/dyngs/AYU/index.htm?cid=125):

* Callejero del Censo Electoral: every street segment ("tramo") in Spain with the
  postal code that serves it. Published every January and July.
  https://www.ine.es/dyngs/DAB/es/index.htm?cid=1390
* Relación de municipios y sus códigos: official municipality names.
  https://www.ine.es/dyngs/INEbase/es/operacion.htm?c=Estadistica_C&cid=1254736177031

Outputs (in data/):

* codigos_postales.csv  cod_postal,municipio_id,municipio,provincia_id,provincia
* provincias.csv        provincia_id,provincia
* metadata.json         source period, counts, attribution and discarded pairs

Only the Python standard library is used. Usage:

    python3 scripts/build_dataset.py                    # latest published period
    python3 scripts/build_dataset.py --period 072026    # a specific one (MMYYYY)
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import html
import io
import json
import re
import sys
import urllib.error
import urllib.request
import zipfile
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DATA_DIR = ROOT / "data"

CALLEJERO_URL = "https://www.ine.es/prodyser/callejero/caj_esp/caj_esp_{period}.zip"
MUNICIPIOS_URL = "https://www.ine.es/daco/daco42/codmun/diccionario{yy}.xlsx"
USER_AGENT = "spain-postal-codes-dataset-builder (+https://github.com/codecruz/spain-postal-codes)"

LICENSE = "CC BY 4.0"
ATTRIBUTION = "Fuente: Instituto Nacional de Estadística (INE). Callejero del Censo Electoral y Relación de municipios."

PROVINCES = {
    "01": "Araba/Álava", "02": "Albacete", "03": "Alicante/Alacant", "04": "Almería",
    "05": "Ávila", "06": "Badajoz", "07": "Illes Balears", "08": "Barcelona",
    "09": "Burgos", "10": "Cáceres", "11": "Cádiz", "12": "Castellón/Castelló",
    "13": "Ciudad Real", "14": "Córdoba", "15": "A Coruña", "16": "Cuenca",
    "17": "Girona", "18": "Granada", "19": "Guadalajara", "20": "Gipuzkoa",
    "21": "Huelva", "22": "Huesca", "23": "Jaén", "24": "León",
    "25": "Lleida", "26": "La Rioja", "27": "Lugo", "28": "Madrid",
    "29": "Málaga", "30": "Murcia", "31": "Navarra", "32": "Ourense",
    "33": "Asturias", "34": "Palencia", "35": "Las Palmas", "36": "Pontevedra",
    "37": "Salamanca", "38": "Santa Cruz de Tenerife", "39": "Cantabria", "40": "Segovia",
    "41": "Sevilla", "42": "Soria", "43": "Tarragona", "44": "Teruel",
    "45": "Toledo", "46": "Valencia/València", "47": "Valladolid", "48": "Bizkaia",
    "49": "Zamora", "50": "Zaragoza", "51": "Ceuta", "52": "Melilla",
}

# Articles the INE writes after the name ("Coruña, A"). Castilian, Catalan/Valencian,
# Balearic and Galician forms; matched case-sensitively as they appear in the source.
ARTICLES = {
    "El", "La", "Los", "Las", "el", "la",
    "L'", "l'", "Els", "els", "Les", "les",
    "Es", "Sa", "Ses",
    "A", "O", "As", "Os",
}

# TRAM record layout ("Dis_nuevo.xlsx"): 0-based [start, end) slices.
TRAM_CPRO = slice(0, 2)
TRAM_CMUN = slice(2, 5)
TRAM_CPOS = slice(257, 262)  # postal code of the segment, current situation

# A postal code from another province backed by a single street segment is almost
# always a typo in the source (e.g. "03020" in Fuente Álamo de Murcia, instead of
# "30320"). Genuine cases, such as enclaves like Condado de Treviño, span many segments.
MIN_SEGMENTS_CROSS_PROVINCE = 2


@dataclass(frozen=True, order=True)
class Row:
    cod_postal: str
    municipio_id: str
    municipio: str
    provincia_id: str
    provincia: str


class BuildError(Exception):
    pass


def normalize_name(name: str) -> str:
    """Move trailing articles to the front: "Coruña, A" -> "A Coruña",
    "Orxa, l'/Lorcha" -> "L'Orxa/Lorcha". Each part of bilingual names is handled separately."""
    parts = []
    for part in name.strip().split("/"):
        base, sep, article = part.rpartition(", ")
        if sep and article in ARTICLES:
            article = article[0].upper() + article[1:]
            part = f"{article}{base}" if article.endswith("'") else f"{article} {base}"
        parts.append(part.strip())
    return "/".join(parts)


def parse_tram_line(line: str) -> tuple[str, str]:
    """Returns (municipio_id, cod_postal) from a fixed-width TRAM record."""
    if len(line) < TRAM_CPOS.stop:
        raise BuildError(f"TRAM record too short ({len(line)} chars)")
    municipio_id = line[TRAM_CPRO] + line[TRAM_CMUN]
    cod_postal = line[TRAM_CPOS].strip()
    return municipio_id, cod_postal


def fetch(url: str, cache_dir: Path | None) -> bytes:
    if cache_dir is not None:
        cached = cache_dir / url.rsplit("/", 1)[-1]
        if cached.exists():
            return cached.read_bytes()
    print(f"Downloading {url}", file=sys.stderr)
    request = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(request, timeout=300) as response:
        body = response.read()
    if cache_dir is not None:
        cache_dir.mkdir(parents=True, exist_ok=True)
        (cache_dir / url.rsplit("/", 1)[-1]).write_bytes(body)
    return body


def exists(url: str) -> bool:
    request = urllib.request.Request(url, method="HEAD", headers={"User-Agent": USER_AGENT})
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return response.status == 200
    except urllib.error.HTTPError:
        return False


def candidate_periods(today: dt.date) -> list[str]:
    """Most recent first: July and January of this year and the previous one."""
    periods = []
    for year in (today.year, today.year - 1):
        for month in ("07", "01"):
            if (year, int(month)) <= (today.year, today.month):
                periods.append(f"{month}{year}")
    return periods


def latest_period(today: dt.date) -> str:
    for period in candidate_periods(today):
        if exists(CALLEJERO_URL.format(period=period)):
            return period
    raise BuildError("No published callejero found for the last two years")


def read_xlsx_rows(content: bytes) -> list[dict[str, str]]:
    """Minimal reader for the first sheet of an .xlsx file (column letter -> value)."""
    with zipfile.ZipFile(io.BytesIO(content)) as xlsx:
        shared = xlsx.read("xl/sharedStrings.xml").decode("utf-8")
        strings = [html.unescape(re.sub(r"<[^>]+>", "", si)) for si in re.findall(r"<si>(.*?)</si>", shared, re.S)]
        sheet = xlsx.read("xl/worksheets/sheet1.xml").decode("utf-8")

    rows = []
    for row in re.findall(r"<row[^>]*>(.*?)</row>", sheet, re.S):
        cells = {}
        for column, attrs, value in re.findall(r'<c r="([A-Z]+)\d+"([^>]*)>(?:<f>.*?</f>)?<v>(.*?)</v>', row, re.S):
            cells[column] = strings[int(value)] if 't="s"' in attrs else html.unescape(value)
        rows.append(cells)
    return rows


def load_municipalities(content: bytes) -> dict[str, str]:
    rows = read_xlsx_rows(content)
    header_index = next((i for i, r in enumerate(rows) if r.get("B") == "CPRO" and r.get("C") == "CMUN"), None)
    if header_index is None:
        raise BuildError("Unexpected municipality file layout: CPRO/CMUN header not found")

    municipalities = {}
    for r in rows[header_index + 1:]:
        cpro, cmun, nombre = r.get("B", "").zfill(2), r.get("C", "").zfill(3), r.get("E", "").strip()
        if cpro.strip("0") and nombre:
            municipalities[cpro + cmun] = normalize_name(nombre)
    if len(municipalities) < 8000:
        raise BuildError(f"Only {len(municipalities)} municipalities found, expected more than 8000")
    return municipalities


def load_pairs(content: bytes) -> dict[tuple[str, str], int]:
    """(municipio_id, cod_postal) -> number of street segments."""
    pairs: dict[tuple[str, str], int] = {}
    with zipfile.ZipFile(io.BytesIO(content)) as archive:
        tram = next((n for n in archive.namelist() if n.rsplit("/", 1)[-1].startswith("TRAM.")), None)
        if tram is None:
            raise BuildError("TRAM file not found in the callejero archive")
        with archive.open(tram) as raw:
            for number, line in enumerate(io.TextIOWrapper(raw, encoding="latin-1"), start=1):
                if not line.strip():
                    continue
                try:
                    key = parse_tram_line(line.rstrip("\r\n"))
                except BuildError as e:
                    raise BuildError(f"{tram}:{number}: {e}") from e
                pairs[key] = pairs.get(key, 0) + 1
    return pairs


def discard_reason(cod_postal: str, provincia_id: str, segments: int) -> str | None:
    """Why a (postal code, municipality) pair from the source is not trusted, or None."""
    if cod_postal.endswith("000"):
        return "placeholder code (xx000 is not assigned to delivery addresses)"
    if cod_postal[:2] != provincia_id and segments < MIN_SEGMENTS_CROSS_PROVINCE:
        return f"code from another province in fewer than {MIN_SEGMENTS_CROSS_PROVINCE} street segments (probable typo)"
    return None


def build_rows(pairs: dict[tuple[str, str], int], municipalities: dict[str, str]) -> tuple[list[Row], list[dict]]:
    """Returns the dataset rows and the pairs discarded as probable source errors."""
    rows, discarded = [], []
    for (municipio_id, cod_postal), segments in pairs.items():
        if not re.fullmatch(r"\d{5}", cod_postal) or cod_postal == "00000":
            raise BuildError(f"Invalid postal code {cod_postal!r} for municipality {municipio_id}")
        provincia_id = municipio_id[:2]
        if provincia_id not in PROVINCES:
            raise BuildError(f"Unknown province code {provincia_id!r}")
        if municipio_id not in municipalities:
            raise BuildError(f"Municipality {municipio_id} is not in the INE municipality list")
        row = Row(cod_postal, municipio_id, municipalities[municipio_id], provincia_id, PROVINCES[provincia_id])
        reason = discard_reason(cod_postal, provincia_id, segments)
        if reason is not None:
            discarded.append({"cod_postal": cod_postal, "municipio_id": municipio_id, "municipio": row.municipio, "segments": segments, "reason": reason})
            continue
        rows.append(row)
    return sorted(rows), sorted(discarded, key=lambda d: (d["cod_postal"], d["municipio_id"]))


def write_outputs(rows: list[Row], discarded: list[dict], period: str, out_dir: Path) -> dict:
    out_dir.mkdir(parents=True, exist_ok=True)

    with open(out_dir / "codigos_postales.csv", "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f, lineterminator="\n")
        writer.writerow(["cod_postal", "municipio_id", "municipio", "provincia_id", "provincia"])
        writer.writerows((r.cod_postal, r.municipio_id, r.municipio, r.provincia_id, r.provincia) for r in rows)

    with open(out_dir / "provincias.csv", "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f, lineterminator="\n")
        writer.writerow(["provincia_id", "provincia"])
        writer.writerows(PROVINCES.items())

    month, year = period[:2], period[2:]
    reference = f"{year}-06-30" if month == "07" else f"{int(year) - 1}-12-31"
    metadata = {
        "source": {
            "name": "INE - Callejero del Censo Electoral",
            "url": "https://www.ine.es/dyngs/DAB/es/index.htm?cid=1390",
            "period": f"{year}-{month}",
            "reference_date": reference,
        },
        "license": LICENSE,
        "attribution": ATTRIBUTION,
        "counts": {
            "rows": len(rows),
            "postal_codes": len({r.cod_postal for r in rows}),
            "municipalities": len({r.municipio_id for r in rows}),
            "provinces": len({r.provincia_id for r in rows}),
        },
        "discarded": discarded,
    }
    (out_dir / "metadata.json").write_text(json.dumps(metadata, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return metadata


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    parser.add_argument("--period", help="Callejero period as MMYYYY (01 or 07), e.g. 072026. Default: latest.")
    parser.add_argument("--out", type=Path, default=DATA_DIR, help="Output directory (default: data/)")
    parser.add_argument("--cache-dir", type=Path, help="Reuse downloaded files from this directory")
    args = parser.parse_args(argv)

    try:
        period = args.period or latest_period(dt.date.today())
        if not re.fullmatch(r"(01|07)\d{4}", period):
            raise BuildError(f"Invalid period {period!r}: expected 01YYYY or 07YYYY")

        pairs = load_pairs(fetch(CALLEJERO_URL.format(period=period), args.cache_dir))

        # The municipality list is published as of 1 January of each year; use that
        # year's list, falling back to the previous one if it is not out yet.
        year = int(period[2:])
        for yy in (year % 100, (year - 1) % 100):
            try:
                municipalities = load_municipalities(fetch(MUNICIPIOS_URL.format(yy=f"{yy:02d}"), args.cache_dir))
                break
            except urllib.error.HTTPError:
                continue
        else:
            raise BuildError("INE municipality list not found")

        rows, discarded = build_rows(pairs, municipalities)
        metadata = write_outputs(rows, discarded, period, args.out)
    except (BuildError, urllib.error.URLError) as e:
        print(f"error: {e}", file=sys.stderr)
        return 1

    counts = metadata["counts"]
    print(
        f"Period {metadata['source']['period']}: {counts['rows']} rows, {counts['postal_codes']} postal codes, "
        f"{counts['municipalities']} municipalities -> {args.out}",
        file=sys.stderr,
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
