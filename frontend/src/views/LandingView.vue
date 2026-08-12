<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const visible = ref(false)
const stepsVisible = ref(false)
const stepsEl = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

onMounted(() => {
  requestAnimationFrame(() => {
    visible.value = true
  })

  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry?.isIntersecting) {
        stepsVisible.value = true
        observer?.disconnect()
      }
    },
    { threshold: 0.25 },
  )
  if (stepsEl.value) observer.observe(stepsEl.value)
})

onUnmounted(() => observer?.disconnect())
</script>

<template>
  <div class="landing">
    <section class="hero" :class="{ 'hero--ready': visible }">
      <div class="hero__media" aria-hidden="true" />
      <div class="hero__veil" aria-hidden="true" />
      <div class="hero__content page-shell">
        <p class="hero__brand display-font">{{ t('app.name') }}</p>
        <h1 class="hero__headline display-font">{{ t('landing.headline') }}</h1>
        <p class="hero__lead">{{ t('landing.lead') }}</p>
        <div class="hero__actions">
          <v-btn
            :to="'/t/azure-beach/q/umbrella12'"
            color="primary"
            size="large"
            class="hero__cta"
          >
            {{ t('landing.ctaDemo') }}
          </v-btn>
          <v-btn to="/login" variant="outlined" size="large" class="hero__cta hero__cta--ghost">
            {{ t('landing.ctaStaff') }}
          </v-btn>
          <v-btn to="/register" variant="outlined" size="large" class="hero__cta hero__cta--ghost">
            {{ t('landing.ctaRegister') }}
          </v-btn>
        </div>
      </div>
    </section>

    <section ref="stepsEl" class="section section--steps page-shell" :class="{ 'section--in': stepsVisible }">
      <h2 class="section__title display-font">{{ t('landing.howTitle') }}</h2>
      <p class="section__lead">{{ t('landing.howLead') }}</p>
      <ol class="steps">
        <li class="steps__item">
          <span class="steps__num display-font">01</span>
          <div>
            <h3 class="steps__title">{{ t('landing.step1Title') }}</h3>
            <p class="steps__text">{{ t('landing.step1Text') }}</p>
          </div>
        </li>
        <li class="steps__item">
          <span class="steps__num display-font">02</span>
          <div>
            <h3 class="steps__title">{{ t('landing.step2Title') }}</h3>
            <p class="steps__text">{{ t('landing.step2Text') }}</p>
          </div>
        </li>
        <li class="steps__item">
          <span class="steps__num display-font">03</span>
          <div>
            <h3 class="steps__title">{{ t('landing.step3Title') }}</h3>
            <p class="steps__text">{{ t('landing.step3Text') }}</p>
          </div>
        </li>
      </ol>
    </section>

    <section class="section section--for">
      <div class="page-shell section--for__inner">
        <h2 class="section__title display-font">{{ t('landing.forTitle') }}</h2>
        <p class="section__lead section__lead--wide">{{ t('landing.forLead') }}</p>
        <ul class="benefits">
          <li>{{ t('landing.benefit1') }}</li>
          <li>{{ t('landing.benefit2') }}</li>
          <li>{{ t('landing.benefit3') }}</li>
          <li>{{ t('landing.benefit4') }}</li>
        </ul>
      </div>
    </section>

    <section class="section section--cta page-shell">
      <h2 class="section__title display-font">{{ t('landing.finalTitle') }}</h2>
      <p class="section__lead">{{ t('landing.finalLead') }}</p>
      <div class="hero__actions">
        <v-btn :to="'/t/azure-beach/q/umbrella12'" color="primary" size="large">
          {{ t('landing.ctaDemo') }}
        </v-btn>
        <v-btn to="/login" variant="tonal" size="large" color="primary">
          {{ t('landing.ctaStaff') }}
        </v-btn>
        <v-btn to="/register" variant="outlined" size="large" color="primary">
          {{ t('landing.ctaRegister') }}
        </v-btn>
      </div>
    </section>

    <footer class="landing__footer page-shell">
      <div class="landing__footer-brand">
        <span class="display-font">{{ t('app.name') }}</span>
        <span class="landing__footer-note">{{ t('landing.footer') }}</span>
      </div>
      <nav class="landing__footer-links" :aria-label="t('legal.footerNav')">
        <router-link to="/privacy">{{ t('legal.privacyTitle') }}</router-link>
        <router-link to="/terms">{{ t('legal.termsTitle') }}</router-link>
        <router-link to="/cookies">{{ t('legal.cookiesTitle') }}</router-link>
      </nav>
    </footer>
  </div>
</template>

<style scoped>
.landing {
  margin: -8px -0px 0;
}

.hero {
  position: relative;
  min-height: calc(100dvh - 56px);
  min-height: calc(100svh - 56px);
  display: flex;
  align-items: flex-end;
  overflow: hidden;
  color: #f4faf9;
}

@media (min-width: 600px) {
  .hero {
    min-height: calc(100dvh - 64px);
    min-height: calc(100svh - 64px);
  }
}

.hero__media {
  position: absolute;
  inset: 0;
  background:
    url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2000&q=80')
      center / cover no-repeat;
  transform: scale(1.06);
  transition: transform 8s ease-out;
}

.hero--ready .hero__media {
  transform: scale(1);
}

.hero__veil {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(180deg, rgba(8, 40, 42, 0.35) 0%, rgba(8, 40, 42, 0.2) 35%, rgba(8, 40, 42, 0.78) 100%),
    linear-gradient(90deg, rgba(8, 78, 76, 0.45) 0%, transparent 55%);
}

.hero__content {
  position: relative;
  z-index: 1;
  width: 100%;
  padding-top: 2.5rem;
  padding-bottom: 2rem;
}

@media (min-width: 600px) {
  .hero__content {
    padding-top: 4rem;
    padding-bottom: 3.5rem;
  }
}

.hero__brand {
  margin: 0 0 0.75rem;
  font-size: clamp(2.6rem, 8vw, 4.75rem);
  font-weight: 700;
  line-height: 0.95;
  letter-spacing: -0.03em;
  opacity: 0;
  transform: translateY(18px);
  transition: opacity 0.7s ease, transform 0.7s ease;
}

.hero__headline {
  margin: 0 0 0.85rem;
  max-width: 18ch;
  font-size: clamp(1.35rem, 5.5vw, 2.35rem);
  font-weight: 500;
  line-height: 1.2;
  opacity: 0;
  transform: translateY(18px);
  transition: opacity 0.7s ease 0.12s, transform 0.7s ease 0.12s;
}

.hero__lead {
  margin: 0 0 1.75rem;
  max-width: 36rem;
  font-size: clamp(1rem, 2.2vw, 1.15rem);
  line-height: 1.5;
  color: rgba(244, 250, 249, 0.88);
  opacity: 0;
  transform: translateY(18px);
  transition: opacity 0.7s ease 0.22s, transform 0.7s ease 0.22s;
}

.hero--ready .hero__brand,
.hero--ready .hero__headline,
.hero--ready .hero__lead,
.hero--ready .hero__actions {
  opacity: 1;
  transform: none;
}

.hero__actions {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  opacity: 0;
  transform: translateY(18px);
  transition: opacity 0.7s ease 0.32s, transform 0.7s ease 0.32s;
}

.hero__actions .v-btn {
  width: 100%;
  min-height: 48px;
}

@media (min-width: 600px) {
  .hero__actions {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 0.75rem;
  }

  .hero__actions .v-btn {
    width: auto;
  }
}

.hero__cta--ghost {
  color: #f4faf9 !important;
  border-color: rgba(244, 250, 249, 0.55) !important;
}

.section {
  padding-top: 2.75rem;
  padding-bottom: 1rem;
}

@media (min-width: 600px) {
  .section {
    padding-top: 4.5rem;
    padding-bottom: 1rem;
  }
}

.section__title {
  margin: 0 0 0.65rem;
  color: var(--bo-teal-deep);
  font-size: clamp(1.7rem, 3.5vw, 2.3rem);
  line-height: 1.15;
}

.section__lead {
  margin: 0 0 2.5rem;
  max-width: 34rem;
  color: rgba(20, 54, 66, 0.78);
  font-size: 1.05rem;
  line-height: 1.55;
}

.section__lead--wide {
  max-width: 40rem;
}

.steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 1.75rem;
}

@media (min-width: 900px) {
  .steps {
    grid-template-columns: repeat(3, 1fr);
    gap: 2.5rem;
  }
}

.steps__item {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.9rem;
  opacity: 0;
  transform: translateY(16px);
  transition: opacity 0.55s ease, transform 0.55s ease;
}

.section--in .steps__item {
  opacity: 1;
  transform: none;
}

.section--in .steps__item:nth-child(2) {
  transition-delay: 0.1s;
}

.section--in .steps__item:nth-child(3) {
  transition-delay: 0.2s;
}

.steps__num {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--bo-teal);
  line-height: 1;
}

.steps__title {
  margin: 0 0 0.35rem;
  font-size: 1.15rem;
  font-weight: 600;
  color: var(--bo-ink);
}

.steps__text {
  margin: 0;
  color: rgba(20, 54, 66, 0.75);
  line-height: 1.5;
}

.section--for {
  margin-top: 2rem;
  padding-top: 2.5rem;
  padding-bottom: 2.5rem;
  background:
    linear-gradient(135deg, rgba(11, 110, 107, 0.1), rgba(244, 201, 95, 0.12)),
    url('https://images.unsplash.com/photo-1519046904884-4511a7e7cbfd?auto=format&fit=crop&w=1800&q=70')
      center / cover no-repeat;
  background-blend-mode: soft-light, normal;
  position: relative;
}

@media (min-width: 600px) {
  .section--for {
    margin-top: 3rem;
    padding-top: 4rem;
    padding-bottom: 4rem;
  }
}

.section--for::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(232, 244, 243, 0.92), rgba(247, 251, 251, 0.96));
}

.section--for__inner {
  position: relative;
}

.benefits {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.85rem;
  max-width: 36rem;
}

.benefits li {
  position: relative;
  padding-left: 1.25rem;
  color: var(--bo-ink);
  line-height: 1.45;
  font-size: 1.05rem;
}

.benefits li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.55em;
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 50%;
  background: var(--bo-teal);
}

.section--cta {
  padding-bottom: 3rem;
  text-align: left;
}

.landing .page-shell {
  padding-left: max(1.5rem, env(safe-area-inset-left, 0px));
  padding-right: max(1.5rem, env(safe-area-inset-right, 0px));
}

@media (min-width: 600px) {
  .landing .page-shell {
    padding-left: 1.75rem;
    padding-right: 1.75rem;
  }
}

.landing__footer {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-end;
  gap: 1rem;
  padding-top: 1rem;
  padding-bottom: 2.5rem;
  border-top: 1px solid rgba(11, 110, 107, 0.14);
  color: var(--bo-teal-deep);
  font-weight: 600;
}

.landing__footer-brand {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.landing__footer-links {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  font-size: 0.875rem;
  font-weight: 500;
}

.landing__footer-links a {
  color: var(--bo-teal-deep);
  text-decoration: none;
}

.landing__footer-links a:hover {
  text-decoration: underline;
}

.landing__footer-note {
  font-weight: 400;
  color: rgba(20, 54, 66, 0.65);
  font-size: 0.95rem;
}
</style>
