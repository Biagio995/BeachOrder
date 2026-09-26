/**
 * Public product name shown in user-visible frontend texts.
 *
 * Override it without code changes via the `VITE_APP_NAME` environment
 * variable (see `frontend/.env.example`). Falls back to 'Dalposto'.
 */
export const APP_NAME = import.meta.env.VITE_APP_NAME?.trim() || 'Dalposto'

/** Filesystem-friendly variant of {@link APP_NAME} (e.g. export filenames). */
export const APP_SLUG =
  APP_NAME.toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'dalposto'
