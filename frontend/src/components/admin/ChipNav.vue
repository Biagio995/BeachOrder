<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

export type ChipNavLink = {
  to: string
  label: string
  exact?: boolean
}

defineProps<{
  links: ChipNavLink[]
}>()

const route = useRoute()
const router = useRouter()
const { smAndDown } = useDisplay()

function isActive(to: string, exact?: boolean) {
  if (exact) return route.path === to
  return route.path === to || route.path.startsWith(`${to}/`)
}

function go(to: string) {
  if (route.path !== to) router.push(to)
}
</script>

<template>
  <nav
    class="chip-nav mb-5"
    :class="{ 'chip-scroll': smAndDown, 'chip-nav--wrap': !smAndDown }"
    aria-label="Admin"
  >
    <button
      v-for="link in links"
      :key="link.to"
      type="button"
      class="period-pills__btn chip-nav__btn"
      :class="{ 'period-pills__btn--active': isActive(link.to, link.exact) }"
      :aria-current="isActive(link.to, link.exact) ? 'page' : undefined"
      @click="go(link.to)"
    >
      {{ link.label }}
    </button>
  </nav>
</template>

<style scoped>
.chip-nav--wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.chip-nav__btn {
  flex: 0 0 auto;
  text-transform: none;
}
</style>
