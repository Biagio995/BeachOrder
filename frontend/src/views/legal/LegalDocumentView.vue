<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  document: 'privacy' | 'terms' | 'cookies' | 'dpa' | 'data-processing-roles'
}>()

const { t, locale } = useI18n()

const content = ref('')
const loading = ref(true)
const error = ref('')

const title = computed(() => {
  const map: Record<string, string> = {
    privacy: t('legal.privacyTitle'),
    terms: t('legal.termsTitle'),
    cookies: t('legal.cookiesTitle'),
    dpa: t('legal.dpaTitle'),
    'data-processing-roles': t('legal.rolesTitle'),
  }
  return map[props.document] || props.document
})

function renderMarkdown(md: string): string {
  return md
    .replace(/^### (.+)$/gm, '<h3>$1</h3>')
    .replace(/^## (.+)$/gm, '<h2>$1</h2>')
    .replace(/^# (.+)$/gm, '<h1>$1</h1>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
    .replace(/^- (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>.*<\/li>\n?)+/g, (match) => `<ul>${match}</ul>`)
    .replace(/^\|(.+)\|$/gm, (line) => {
      const cells = line.split('|').filter(Boolean).map((c) => c.trim())
      if (cells.every((c) => /^[-:]+$/.test(c))) return ''
      return `<tr>${cells.map((c) => `<td>${c}</td>`).join('')}</tr>`
    })
    .replace(/(<tr>.*<\/tr>\n?)+/g, (match) => `<table>${match}</table>`)
    .replace(/\n\n/g, '</p><p>')
    .replace(/^(?!<[hulot]|<p|<li|<tr|<table)(.+)$/gm, '<p>$1</p>')
}

async function loadDocument() {
  loading.value = true
  error.value = ''
  try {
    const base = import.meta.env.VITE_API_URL || '/api'
    const res = await fetch(`${base}/legal/${props.document}?locale=${locale.value}`)
    if (!res.ok) throw new Error('not found')
    content.value = await res.text()
  } catch {
    error.value = t('legal.loadError')
  } finally {
    loading.value = false
  }
}

onMounted(loadDocument)
watch(locale, loadDocument)
</script>

<template>
  <div class="page-shell legal-doc">
    <router-link to="/" class="legal-doc__back">{{ t('common.back') }}</router-link>
    <h1 class="display-font legal-doc__title">{{ title }}</h1>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />
    <v-alert v-else-if="error" type="error" density="comfortable">{{ error }}</v-alert>
    <article v-else class="legal-doc__body" v-html="renderMarkdown(content)" />
  </div>
</template>

<style scoped>
.legal-doc {
  max-width: 720px;
  padding-bottom: 3rem;
}

.legal-doc__back {
  display: inline-block;
  margin-bottom: 1rem;
  color: var(--bo-teal-deep);
  text-decoration: none;
  font-weight: 600;
  font-size: 0.9rem;
}

.legal-doc__back:hover {
  text-decoration: underline;
}

.legal-doc__title {
  color: var(--bo-teal-deep);
  margin-bottom: 1.5rem;
  font-size: clamp(1.6rem, 4vw, 2.2rem);
}

.legal-doc__body :deep(h1) {
  font-size: 1.4rem;
  margin: 1.5rem 0 0.75rem;
  color: var(--bo-teal-deep);
}

.legal-doc__body :deep(h2) {
  font-size: 1.15rem;
  margin: 1.25rem 0 0.5rem;
  color: var(--bo-teal-deep);
}

.legal-doc__body :deep(h3) {
  font-size: 1rem;
  margin: 1rem 0 0.4rem;
}

.legal-doc__body :deep(p),
.legal-doc__body :deep(li) {
  line-height: 1.6;
  color: rgba(20, 54, 66, 0.85);
  margin-bottom: 0.5rem;
}

.legal-doc__body :deep(ul) {
  padding-left: 1.25rem;
  margin-bottom: 1rem;
}

.legal-doc__body :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 1rem 0;
  font-size: 0.9rem;
}

.legal-doc__body :deep(td) {
  border: 1px solid rgba(11, 110, 107, 0.15);
  padding: 0.4rem 0.6rem;
}

.legal-doc__body :deep(a) {
  color: var(--bo-teal-deep);
}
</style>
