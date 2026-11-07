export interface Provincia {
  /** 2-digit province code, e.g. "28". */
  readonly id: string;
  /** Official name, e.g. "Madrid" or "Valencia/València". */
  readonly nombre: string;
}

export interface Municipio {
  /** 5-digit INE municipality code, e.g. "28079". */
  readonly id: string;
  /** Official name, e.g. "Alcalá de Henares" or "Alicante/Alacant". */
  readonly nombre: string;
  /** 2-digit code of the province the municipality belongs to. */
  readonly provinciaId: string;
  /** Name of the province the municipality belongs to. */
  readonly provincia: string;
  /** Postal codes of the municipality, sorted. */
  readonly codigosPostales: readonly string[];
}

export interface Metadata {
  /** Period of the INE source data, "YYYY-MM". */
  readonly period: string;
  /** Date the data refers to, "YYYY-MM-DD". */
  readonly referenceDate: string;
  readonly source: string;
  readonly license: string;
  readonly attribution: string;
}

export interface SearchOptions {
  /** Only return municipalities of this province (2-digit code). */
  provinciaId?: string;
  /** Maximum number of results. Default: 10. */
  limit?: number;
}

/** Data source and license. */
export const metadata: Metadata;

/** Normalizes to a 5-digit string ("1450" or 1450 -> "01450"); null if it cannot be a postal code. */
export function normalizePostalCode(input: string | number): string | null;

/** Municipalities served by a postal code; empty if unknown. */
export function lookup(postalCode: string | number): readonly Municipio[];

/** Whether the postal code exists in the dataset. */
export function isValidPostalCode(postalCode: string | number): boolean;

/** A municipality by its INE code. */
export function getMunicipio(id: string): Municipio | undefined;

/** Postal codes of a municipality; empty if unknown. */
export function getPostalCodes(municipioId: string): readonly string[];

/** The 52 provinces, sorted by code. */
export function getProvincias(): readonly Provincia[];

/** A province by its 2-digit code ("28") or by a postal code ("28001"). */
export function getProvincia(code: string | number): Provincia | undefined;

/** Municipalities whose name contains the query, ignoring case and accents. Prefix matches first. */
export function searchMunicipios(query: string, options?: SearchOptions): Municipio[];
