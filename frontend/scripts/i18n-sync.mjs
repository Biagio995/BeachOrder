/**
 * i18n sync — `el.json` is the reference key tree, `en.json` is the fallback.
 *
 * Usage:
 *   node scripts/i18n-sync.mjs           # check + fill missing keys in en/it/de
 *   node scripts/i18n-sync.mjs --check   # CI: fail if en misses keys from el
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const localesDir = join(__dirname, '../src/locales')
const checkOnly = process.argv.includes('--check')

const REFERENCE = 'el'
const FALLBACK = 'en'
const OTHERS = ['it', 'de']

function load(name) {
  return JSON.parse(readFileSync(join(localesDir, `${name}.json`), 'utf8'))
}

function save(name, data) {
  writeFileSync(join(localesDir, `${name}.json`), `${JSON.stringify(data, null, 2)}\n`, 'utf8')
}

function flatten(obj, prefix = '', out = {}) {
  for (const [key, value] of Object.entries(obj)) {
    const path = prefix ? `${prefix}.${key}` : key
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      flatten(value, path, out)
    } else {
      out[path] = value
    }
  }
  return out
}

function setPath(obj, path, value) {
  const parts = path.split('.')
  let cur = obj
  for (let i = 0; i < parts.length - 1; i++) {
    const part = parts[i]
    if (!cur[part] || typeof cur[part] !== 'object' || Array.isArray(cur[part])) {
      cur[part] = {}
    }
    cur = cur[part]
  }
  cur[parts[parts.length - 1]] = value
}

function pruneToReference(target, reference) {
  const out = {}
  for (const key of Object.keys(reference)) {
    if (!(key in target)) continue
    const refVal = reference[key]
    const tgtVal = target[key]
    if (refVal && typeof refVal === 'object' && !Array.isArray(refVal)) {
      if (tgtVal && typeof tgtVal === 'object' && !Array.isArray(tgtVal)) {
        out[key] = pruneToReference(tgtVal, refVal)
      }
    } else {
      out[key] = tgtVal
    }
  }
  return out
}

const reference = load(REFERENCE)
const fallback = load(FALLBACK)
const refKeys = flatten(reference)
const fbKeys = flatten(fallback)

const missingInFallback = Object.keys(refKeys).filter((k) => !(k in fbKeys))
const extraInFallback = Object.keys(fbKeys).filter((k) => !(k in refKeys))

console.log(`[i18n] reference=${REFERENCE}.json (${Object.keys(refKeys).length} keys)`)
console.log(`[i18n] fallback=${FALLBACK}.json (${Object.keys(fbKeys).length} keys)`)

if (missingInFallback.length) {
  console.error(`[i18n] ${FALLBACK}.json missing ${missingInFallback.length} keys from ${REFERENCE}.json:`)
  for (const key of missingInFallback.slice(0, 40)) console.error(`  - ${key}`)
  if (missingInFallback.length > 40) console.error(`  … +${missingInFallback.length - 40} more`)

  if (checkOnly) process.exit(1)

  // Fill English gaps with Greek reference values as temporary placeholders.
  for (const key of missingInFallback) {
    setPath(fallback, key, refKeys[key])
  }
  save(FALLBACK, pruneToReference(fallback, reference))
  console.log(`[i18n] filled ${missingInFallback.length} missing keys into ${FALLBACK}.json (review translations)`)
} else if (extraInFallback.length && !checkOnly) {
  save(FALLBACK, pruneToReference(fallback, reference))
  console.log(`[i18n] pruned ${extraInFallback.length} extra keys from ${FALLBACK}.json`)
} else {
  console.log(`[i18n] ${FALLBACK}.json matches reference structure`)
}

if (checkOnly && extraInFallback.length) {
  console.warn(`[i18n] ${FALLBACK}.json has ${extraInFallback.length} keys not in ${REFERENCE}.json`)
}

const enFresh = load(FALLBACK)
const enFlat = flatten(enFresh)

for (const locale of OTHERS) {
  const data = load(locale)
  const flat = flatten(data)
  const missing = Object.keys(refKeys).filter((k) => !(k in flat))
  const extra = Object.keys(flat).filter((k) => !(k in refKeys))

  if (missing.length === 0 && extra.length === 0) {
    console.log(`[i18n] ${locale}.json OK (inherits missing → ${FALLBACK} at runtime)`)
    continue
  }

  if (checkOnly) {
    if (missing.length) {
      console.log(`[i18n] ${locale}.json missing ${missing.length} keys (runtime fallback → ${FALLBACK})`)
    }
    continue
  }

  // Keep same structure as el; fill gaps from English so files stay complete for editors,
  // but runtime also merges with en so partial files would work.
  for (const key of missing) {
    setPath(data, key, enFlat[key])
  }
  const pruned = pruneToReference(data, reference)
  save(locale, pruned)
  console.log(
    `[i18n] ${locale}.json: +${missing.length} from ${FALLBACK}, -${extra.length} extra → structure aligned to ${REFERENCE}`,
  )
}

if (checkOnly) {
  console.log('[i18n] check passed')
}
