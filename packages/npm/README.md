# spain-postal-codes

[![npm](https://img.shields.io/npm/v/spain-postal-codes.svg)](https://www.npmjs.com/package/spain-postal-codes)
[![bundle size](https://img.shields.io/badge/gzip-~110%20KB-informational)](https://www.npmjs.com/package/spain-postal-codes)

This package lets you look up and search **Spanish postal codes, municipalities and provinces** without network access. It has no dependencies and works in Node.js and in the browser.

- 10,791 postal codes and 8,132 municipalities, built from official **INE** data. The source data is CC BY 4.0, and the package records its period in `metadata`.
- Restores lost leading zeros (`1450` → `01450`). Searches ignore accents and case.
- Plain ESM with TypeScript types.

Need it without bundling ~110 KB? Use the [static JSON API](https://codecruz.github.io/spain-postal-codes/) instead.

## Install

```bash
npm install spain-postal-codes
```

## Usage

```js
import {
  lookup,
  isValidPostalCode,
  getProvincia,
  getMunicipio,
  getPostalCodes,
  searchMunicipios,
} from "spain-postal-codes";

lookup("41700");
// [{ id: "41038", nombre: "Dos Hermanas", provinciaId: "41", provincia: "Sevilla",
//    codigosPostales: ["41089", "41700", "41701", "41702", "41703", "41704"] }]

lookup("01427").map((m) => m.nombre);
// ["Valdegovía/Gaubea", "Berberana", "Jurisdicción de San Zadornil"]  (one code, several municipalities)

isValidPostalCode(8001);       // true ("08001")
getProvincia("28001");         // { id: "28", nombre: "Madrid" }
getMunicipio("28079")?.nombre; // "Madrid"
getPostalCodes("28079");       // ["28001", "28002", ...]

searchMunicipios("alcala", { provinciaId: "28", limit: 5 });
// [{ nombre: "Alcalá de Henares", ... }]
```

### Autofill a form

```js
postalCodeInput.addEventListener("input", () => {
  const [municipio] = lookup(postalCodeInput.value);
  if (municipio) {
    cityInput.value = municipio.nombre;
    provinceInput.value = municipio.provincia;
  }
});
```

## API

| Function | Returns |
|----------|---------|
| `lookup(postalCode)` | `Municipio[]`: municipalities served by the code (empty if unknown) |
| `isValidPostalCode(postalCode)` | `boolean`: whether the code exists |
| `normalizePostalCode(input)` | `string \| null`: 5-digit form, or `null` if malformed |
| `getMunicipio(id)` | `Municipio \| undefined`: by 5-digit INE code |
| `getPostalCodes(municipioId)` | `string[]`: postal codes of a municipality |
| `getProvincia(code)` | `Provincia \| undefined`: by province code (`"28"`) or postal code (`"28001"`) |
| `getProvincias()` | `Provincia[]`: the 52 provinces |
| `searchMunicipios(query, { provinciaId?, limit? })` | `Municipio[]`: name search; prefix matches first, `limit` defaults to 10 |
| `metadata` | `{ period, referenceDate, source, license, attribution }` |

```ts
interface Municipio {
  id: string;                 // INE code, e.g. "28079"
  nombre: string;
  provinciaId: string;        // e.g. "28"
  provincia: string;
  codigosPostales: readonly string[];
}
interface Provincia { id: string; nombre: string }
```

All returned objects are frozen.

## Data and license

The postal codes come from the INE *Callejero del Censo Electoral* and the names from the INE municipality list. Postal codes that don't belong to a street address, such as PO boxes, are not included. See the [repository](https://github.com/codecruz/spain-postal-codes) for how the dataset is built.

- **Code:** MIT.
- **Data:** CC BY 4.0. Credit the source: *"Fuente: Instituto Nacional de Estadística (INE)"*.
