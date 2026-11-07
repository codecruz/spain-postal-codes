import assert from "node:assert/strict";
import { test } from "node:test";

import {
  getMunicipio,
  getPostalCodes,
  getProvincia,
  getProvincias,
  isValidPostalCode,
  lookup,
  metadata,
  normalizePostalCode,
  searchMunicipios,
} from "../index.js";

test("normalizePostalCode restores leading zeros and rejects garbage", () => {
  assert.equal(normalizePostalCode("28001"), "28001");
  assert.equal(normalizePostalCode(" 1450 "), "01450");
  assert.equal(normalizePostalCode(1450), "01450");
  assert.equal(normalizePostalCode("abc"), null);
  assert.equal(normalizePostalCode("280011"), null);
  assert.equal(normalizePostalCode(14.5), null);
  assert.equal(normalizePostalCode(null), null);
});

test("lookup returns the municipalities of a postal code", () => {
  const [municipio, ...rest] = lookup("41700");
  assert.equal(rest.length, 0);
  assert.deepEqual(
    { id: municipio.id, nombre: municipio.nombre, provinciaId: municipio.provinciaId, provincia: municipio.provincia },
    { id: "41038", nombre: "Dos Hermanas", provinciaId: "41", provincia: "Sevilla" },
  );
  assert.ok(municipio.codigosPostales.includes("41700"));
});

test("lookup handles postal codes shared by several municipalities, across provinces", () => {
  const ids = lookup("01427").map((m) => m.id).sort();
  assert.ok(ids.length > 1);
  assert.ok(ids.includes("09050"), "Berberana (Burgos) uses an Álava postal code");
});

test("lookup of unknown or invalid codes returns an empty, frozen array", () => {
  assert.deepEqual(lookup("99999"), []);
  assert.deepEqual(lookup("hello"), []);
  assert.ok(Object.isFrozen(lookup("99999")));
  assert.ok(Object.isFrozen(lookup("28001")[0]));
});

test("isValidPostalCode", () => {
  assert.equal(isValidPostalCode("28001"), true);
  assert.equal(isValidPostalCode(8001), true);
  assert.equal(isValidPostalCode("99999"), false);
});

test("getMunicipio and getPostalCodes", () => {
  assert.equal(getMunicipio("28079")?.nombre, "Madrid");
  assert.equal(getMunicipio("00000"), undefined);
  const codes = getPostalCodes("28079");
  assert.ok(codes.length > 50);
  assert.deepEqual([...codes], [...codes].sort());
  assert.deepEqual(getPostalCodes("00000"), []);
});

test("getProvincias and getProvincia", () => {
  assert.equal(getProvincias().length, 52);
  assert.deepEqual(getProvincia("28"), { id: "28", nombre: "Madrid" });
  assert.deepEqual(getProvincia(8), { id: "08", nombre: "Barcelona" });
  assert.equal(getProvincia("41700")?.nombre, "Sevilla");
  assert.equal(getProvincia("99"), undefined);
  assert.equal(getProvincia("x"), undefined);
});

test("searchMunicipios ignores case and accents, prefix matches first", () => {
  const names = searchMunicipios("alcala", { limit: 50 }).map((m) => m.nombre);
  assert.ok(names.includes("Alcalá de Henares"));
  assert.ok(names.every((n) => /alcal/i.test(n.normalize("NFD").replace(/[̀-ͯ]/g, ""))));

  const [first] = searchMunicipios("hermanas");
  assert.equal(first.nombre, "Dos Hermanas");

  assert.equal(searchMunicipios("alacant")[0]?.id, "03014", "matches either part of bilingual names");
});

test("searchMunicipios options", () => {
  assert.equal(searchMunicipios("a", { limit: 3 }).length, 3);
  assert.ok(searchMunicipios("villa", { provinciaId: "28", limit: 100 }).every((m) => m.provinciaId === "28"));
  assert.deepEqual(searchMunicipios("   "), []);
});

test("metadata describes the source and license", () => {
  assert.match(metadata.period, /^\d{4}-(01|07)$/);
  assert.equal(metadata.license, "CC BY 4.0");
  assert.match(metadata.attribution, /INE/);
});
