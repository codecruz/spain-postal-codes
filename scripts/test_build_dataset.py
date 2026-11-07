"""Tests for build_dataset.py and build_site.py. Run with: python3 -m unittest discover scripts"""

from __future__ import annotations

import csv
import datetime as dt
import io
import json
import tempfile
import unittest
import zipfile
from pathlib import Path

import build_dataset as b
import build_site


def tram_line(municipio_id: str, cod_postal: str) -> str:
    line = [" "] * 300
    line[0:5] = municipio_id
    line[42:47] = cod_postal
    line[257:262] = cod_postal
    return "".join(line)


class NormalizeNameTest(unittest.TestCase):
    def test_moves_trailing_articles_to_the_front(self):
        cases = {
            "Coruña, A": "A Coruña",
            "Rioja, La": "La Rioja",
            "Hospitalet de Llobregat, L'": "L'Hospitalet de Llobregat",
            "Orxa, l'/Lorcha": "L'Orxa/Lorcha",
            "Vila Joiosa, la/Villajoyosa": "La Vila Joiosa/Villajoyosa",
            "Palmas de Gran Canaria, Las": "Las Palmas de Gran Canaria",
            "Madrid": "Madrid",
            "Alicante/Alacant": "Alicante/Alacant",
        }
        for raw, expected in cases.items():
            with self.subTest(raw=raw):
                self.assertEqual(expected, b.normalize_name(raw))

    def test_keeps_commas_that_are_not_articles(self):
        name = "Cruïlles, Monells i Sant Sadurní de l'Heura"
        self.assertEqual(name, b.normalize_name(name))


class ParseTest(unittest.TestCase):
    def test_parse_tram_line(self):
        self.assertEqual(("28079", "28001"), b.parse_tram_line(tram_line("28079", "28001")))

    def test_rejects_short_lines(self):
        with self.assertRaises(b.BuildError):
            b.parse_tram_line("2807928001")

    def test_load_pairs_counts_segments(self):
        archive = io.BytesIO()
        with zipfile.ZipFile(archive, "w") as z:
            lines = [tram_line("28079", "28001"), tram_line("28079", "28001"), tram_line("28079", "28002"), ""]
            z.writestr("caj_esp_072026/TRAM.D260630.G260702", "\r\n".join(lines).encode("latin-1"))
            z.writestr("caj_esp_072026/VIAS.D260630.G260702", b"")
        self.assertEqual({("28079", "28001"): 2, ("28079", "28002"): 1}, b.load_pairs(archive.getvalue()))

    def test_candidate_periods(self):
        self.assertEqual(["072026", "012026", "072025", "012025"], b.candidate_periods(dt.date(2026, 11, 7)))
        self.assertEqual(["012026", "072025", "012025"], b.candidate_periods(dt.date(2026, 3, 1)))


class BuildRowsTest(unittest.TestCase):
    MUNICIPALITIES = {"28079": "Madrid", "09059": "Burgos", "09211": "Condado de Treviño"}

    def test_builds_sorted_rows(self):
        rows, discarded = b.build_rows({("28079", "28002"): 5, ("28079", "28001"): 3}, self.MUNICIPALITIES)
        self.assertEqual(["28001", "28002"], [r.cod_postal for r in rows])
        self.assertEqual(b.Row("28001", "28079", "Madrid", "28", "Madrid"), rows[0])
        self.assertEqual([], discarded)

    def test_discards_single_segment_cross_province_codes(self):
        pairs = {("09211", "01118"): 26, ("09059", "50001"): 1}
        rows, discarded = b.build_rows(pairs, self.MUNICIPALITIES)
        self.assertEqual([("01118", "09211")], [(r.cod_postal, r.municipio_id) for r in rows])
        self.assertEqual("50001", discarded[0]["cod_postal"])

    def test_discards_placeholder_codes(self):
        rows, discarded = b.build_rows({("28079", "28000"): 10, ("28079", "28001"): 1}, self.MUNICIPALITIES)
        self.assertEqual(["28001"], [r.cod_postal for r in rows])
        self.assertIn("placeholder", discarded[0]["reason"])

    def test_rejects_invalid_data(self):
        for pairs in ({("28079", "2800"): 1}, {("28079", "00000"): 1}, {("99001", "99001"): 1}, {("28999", "28001"): 1}):
            with self.subTest(pairs=pairs), self.assertRaises(b.BuildError):
                b.build_rows(pairs, self.MUNICIPALITIES)


class OutputsTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.out = Path(self.tmp.name)
        rows = [
            b.Row("09001", "09059", "Burgos", "09", "Burgos"),
            b.Row("28001", "28079", "Madrid", "28", "Madrid"),
            b.Row("28100", "28006", "Alcobendas", "28", "Madrid"),
        ]
        self.metadata = b.write_outputs(rows, [], "072026", self.out / "data")

    def tearDown(self):
        self.tmp.cleanup()

    def test_dataset_files(self):
        with open(self.out / "data" / "codigos_postales.csv", encoding="utf-8") as f:
            self.assertEqual("28079", list(csv.DictReader(f))[1]["municipio_id"])
        self.assertFalse((self.out / "data" / "es_cp.csv").exists())
        self.assertEqual({"rows": 3, "postal_codes": 3, "municipalities": 3, "provinces": 2}, self.metadata["counts"])
        self.assertEqual("2026-06-30", self.metadata["source"]["reference_date"])

    def test_static_site(self):
        site = self.out / "site"
        build_site.build(self.out / "data", site)

        cp = json.loads((site / "v1" / "cp" / "28001.json").read_text(encoding="utf-8"))
        self.assertEqual({"cod_postal": "28001", "municipios": [
            {"id": "28079", "nombre": "Madrid", "provincia_id": "28", "provincia": "Madrid"},
        ]}, cp)

        municipio = json.loads((site / "v1" / "municipios" / "28006.json").read_text(encoding="utf-8"))
        self.assertEqual(["28100"], municipio["codigos_postales"])

        provincias = json.loads((site / "v1" / "provincias.json").read_text(encoding="utf-8"))
        madrid = next(p for p in provincias if p["id"] == "28")
        self.assertEqual({"id": "28", "nombre": "Madrid", "municipios": 2, "codigos_postales": 2}, madrid)
        self.assertEqual(52, len(provincias))

        provincia = json.loads((site / "v1" / "provincias" / "28.json").read_text(encoding="utf-8"))
        self.assertEqual(["Alcobendas", "Madrid"], [m["nombre"] for m in provincia["municipios"]])

        self.assertTrue((site / "index.html").is_file())
        self.assertTrue((site / "data" / "codigos_postales.csv").is_file())
        self.assertTrue((site / ".nojekyll").is_file())


if __name__ == "__main__":
    unittest.main()
