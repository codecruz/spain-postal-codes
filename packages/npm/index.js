import { META, MUNICIPIOS, PROVINCIAS } from "./data.js";

/** @typedef {import("./index.d.ts").Municipio} Municipio */
/** @typedef {import("./index.d.ts").Provincia} Provincia */

/** @type {Map<string, Municipio>} */
const byId = new Map();
/** @type {Map<string, Municipio[]>} */
const byPostalCode = new Map();
/** @type {Provincia[]} */
const provinceList = Object.freeze(Object.entries(PROVINCIAS).map(([id, nombre]) => Object.freeze({ id, nombre })));
const provinceById = new Map(provinceList.map((p) => [p.id, p]));

for (const [id, nombre, codes] of MUNICIPIOS) {
  const provinciaId = id.slice(0, 2);
  const codigosPostales = Object.freeze(codes.split(" "));
  const municipio = Object.freeze({ id, nombre, provinciaId, provincia: PROVINCIAS[provinciaId], codigosPostales });
  byId.set(id, municipio);
  for (const code of codigosPostales) {
    const list = byPostalCode.get(code);
    if (list) list.push(municipio);
    else byPostalCode.set(code, [municipio]);
  }
}
for (const list of byPostalCode.values()) Object.freeze(list);

const EMPTY = Object.freeze([]);

/** Data source and license. */
export const metadata = Object.freeze({ ...META });

/**
 * Normalizes a postal code to its 5-digit string form, restoring a lost leading
 * zero ("1450" or 1450 -> "01450"). Returns null if it cannot be a postal code.
 * @param {string | number} input
 * @returns {string | null}
 */
export function normalizePostalCode(input) {
  if (typeof input === "number") {
    if (!Number.isInteger(input) || input < 1000 || input > 99999) return null;
    input = String(input);
  }
  if (typeof input !== "string") return null;
  const value = input.trim();
  if (/^\d{4}$/.test(value)) return "0" + value;
  return /^\d{5}$/.test(value) ? value : null;
}

/**
 * Municipalities served by a postal code (empty array if unknown).
 * @param {string | number} postalCode
 * @returns {readonly Municipio[]}
 */
export function lookup(postalCode) {
  const code = normalizePostalCode(postalCode);
  return (code && byPostalCode.get(code)) || EMPTY;
}

/**
 * Whether the postal code exists in the dataset.
 * @param {string | number} postalCode
 */
export function isValidPostalCode(postalCode) {
  return lookup(postalCode).length > 0;
}

/**
 * A municipality by its 5-digit INE code (e.g. "28079" for Madrid).
 * @param {string} id
 * @returns {Municipio | undefined}
 */
export function getMunicipio(id) {
  return byId.get(String(id).padStart(5, "0"));
}

/**
 * Postal codes of a municipality (empty array if unknown).
 * @param {string} municipioId
 * @returns {readonly string[]}
 */
export function getPostalCodes(municipioId) {
  return getMunicipio(municipioId)?.codigosPostales ?? EMPTY;
}

/** @returns {readonly Provincia[]} the 52 provinces, sorted by code */
export function getProvincias() {
  return provinceList;
}

/**
 * A province by its 2-digit code ("28") or by a postal code ("28001"): in Spain
 * the first two digits of a postal code are its province code.
 * @param {string | number} code
 * @returns {Provincia | undefined}
 */
export function getProvincia(code) {
  let value = typeof code === "number" ? String(code) : String(code ?? "").trim();
  if (/^\d{1,2}$/.test(value)) value = value.padStart(2, "0");
  else {
    const postalCode = normalizePostalCode(value);
    if (!postalCode) return undefined;
    value = postalCode.slice(0, 2);
  }
  return provinceById.get(value);
}

/** @param {string} value */
function fold(value) {
  return value.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().trim();
}

/** @type {Array<[string, Municipio]> | undefined} */
let searchIndex;

/**
 * Municipalities whose name contains the query, ignoring case and accents.
 * Names starting with the query come first.
 * @param {string} query
 * @param {{ provinciaId?: string, limit?: number }} [options]
 * @returns {Municipio[]}
 */
export function searchMunicipios(query, { provinciaId, limit = 10 } = {}) {
  const needle = fold(String(query ?? ""));
  if (needle.length === 0) return [];
  searchIndex ??= [...byId.values()].map((m) => [fold(m.nombre), m]);

  const starts = [];
  const contains = [];
  for (const [name, municipio] of searchIndex) {
    if (provinciaId !== undefined && municipio.provinciaId !== provinciaId) continue;
    // Bilingual names ("Alicante/Alacant") match on either part.
    if (name.startsWith(needle) || name.split("/").some((part) => part.startsWith(needle))) starts.push(municipio);
    else if (name.includes(needle)) contains.push(municipio);
  }
  const byName = (a, b) => a.nombre.localeCompare(b.nombre, "es");
  return [...starts.sort(byName), ...contains.sort(byName)].slice(0, Math.max(0, limit));
}
