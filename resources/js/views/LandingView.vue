<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ArrowDownRight, ArrowRight, Check, Menu, PackageSearch, ShieldCheck, ShoppingBasket, Sparkles, UserRound } from '@lucide/vue'
import { useSiteSettingsStore } from '../stores/siteSettings'

const site = useSiteSettingsStore()
const content = computed(() => site.settings.landing_content)
const year = new Date().getFullYear()
const menuOpen = ref(false)
const prefersReducedMotion = ref(false)
const categoryImages = ['/images/demo-products/product-01.svg', '/images/demo-products/product-08.svg', '/images/demo-products/product-09.svg']
let motionQuery: MediaQueryList | null = null
const videoType = computed(() => content.value.promo.video_url.toLowerCase().split('?')[0].endsWith('.webm') ? 'video/webm' : 'video/mp4')
function syncMotionPreference(event?: MediaQueryListEvent) { prefersReducedMotion.value = event?.matches ?? motionQuery?.matches ?? false }
onMounted(() => {
  motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
  syncMotionPreference()
  motionQuery.addEventListener?.('change', syncMotionPreference)
})
onBeforeUnmount(() => motionQuery?.removeEventListener?.('change', syncMotionPreference))
</script>

<template>
  <main class="portal-landing">
    <div v-if="site.settings.announcement_enabled && site.settings.header_announcement" class="portal-landing-announcement">
      <Sparkles :size="13"/><span>{{ site.settings.header_announcement }}</span>
    </div>

    <header class="portal-landing-nav" :class="`portal-header-${site.settings.header_style}`">
      <RouterLink to="/" class="portal-landing-brand" :aria-label="`${site.settings.site_name} home`">
        <img v-if="site.settings.logo_url" :src="site.settings.logo_url" :alt="`${site.settings.site_name} logo`">
        <span v-else class="portal-landing-mark">{{ site.settings.site_name.slice(0, 1).toUpperCase() || 'B' }}</span>
        <span><strong>{{ site.settings.site_name }}</strong><small>{{ site.settings.tagline }}</small></span>
      </RouterLink>
      <button class="portal-landing-menu" type="button" :aria-expanded="menuOpen" aria-label="Toggle navigation" @click="menuOpen = !menuOpen"><Menu :size="20"/></button>
      <nav :class="{ open: menuOpen }" aria-label="Main navigation">
        <a href="#catalogue" @click="menuOpen = false">Catalogue</a>
        <a href="#benefits" @click="menuOpen = false">Why the portal</a>
        <RouterLink to="/login" class="portal-nav-signin" @click="menuOpen = false"><UserRound :size="15"/> Sign in</RouterLink>
        <RouterLink to="/register" class="portal-nav-join" @click="menuOpen = false">Request access <ArrowRight :size="15"/></RouterLink>
      </nav>
    </header>

    <section v-if="content.promo.enabled && content.promo.video_url" class="portal-pinned-promo" aria-label="Featured promotion">
      <video class="portal-pinned-video" :poster="content.promo.poster_url || undefined" :autoplay="!prefersReducedMotion" muted loop playsinline preload="metadata" aria-hidden="true" tabindex="-1">
        <source :src="content.promo.video_url" :type="videoType">
      </video>
      <div class="portal-promo-shade"></div>
      <div class="portal-promo-copy">
        <span class="portal-promo-eyebrow"><i></i>{{ content.promo.eyebrow }}</span>
        <h2>{{ content.promo.heading }}</h2>
        <p>{{ content.promo.body }}</p>
      </div>
      <RouterLink class="portal-promo-action" :to="content.promo.button_path">{{ content.promo.button_label }} <ArrowRight :size="16"/></RouterLink>
      <span class="portal-promo-index">FEATURED <b>01</b></span>
    </section>

    <section class="portal-landing-hero">
      <div class="portal-hero-copy">
        <span class="portal-section-eyebrow"><i></i> {{ site.settings.site_name }} <b>·</b> BUSINESS PORTAL</span>
        <h1>{{ site.settings.welcome_heading }}</h1>
        <p>{{ site.settings.welcome_message }}</p>
        <div class="portal-hero-actions">
          <RouterLink to="/login" class="portal-button-primary">{{ content.hero.primary_label }} <ArrowRight :size="17"/></RouterLink>
          <a href="#catalogue" class="portal-link-arrow">{{ content.hero.secondary_label }} <ArrowDownRight :size="16"/></a>
        </div>
        <div class="portal-hero-assurance"><span><ShieldCheck :size="16"/></span><p>Built for business customers<br><strong>with account-specific access.</strong></p></div>
      </div>
      <div class="portal-hero-art" aria-hidden="true">
        <div class="portal-art-ring ring-one"></div><div class="portal-art-ring ring-two"></div>
        <div class="portal-art-glow"></div>
        <div class="portal-product-feature"><img :src="'/images/demo-products/product-01.svg'" alt=""><span>CARE <b>01</b></span></div>
        <div class="portal-product-mini mini-one"><img :src="'/images/demo-products/product-08.svg'" alt=""></div>
        <div class="portal-product-mini mini-two"><img :src="'/images/demo-products/product-09.svg'" alt=""></div>
        <span class="portal-art-note note-one">CLEAN <i></i> PROTECT <i></i> RIDE</span>
        <span class="portal-art-note note-two">YOUR TRADE, IN MOTION</span>
        <div class="portal-art-stamp"><PackageSearch :size="17"/><span>PRODUCT<br>DISCOVERY</span></div>
      </div>
      <a href="#catalogue" class="portal-scroll-cue"><span>SCROLL TO EXPLORE</span><i></i></a>
    </section>

    <section v-if="content.catalog.enabled" id="catalogue" class="portal-catalogue-section">
      <header class="portal-section-heading">
        <div><span class="portal-section-eyebrow"><i></i>{{ content.catalog.eyebrow }}</span><h2>{{ content.catalog.heading }}</h2></div>
        <div class="portal-section-aside"><p>{{ content.catalog.body }}</p><RouterLink to="/login">Browse products <ArrowRight :size="15"/></RouterLink></div>
      </header>
      <div class="portal-range-grid">
        <RouterLink v-for="(tile, index) in content.catalog.tiles" :key="index" to="/login" class="portal-range-card" :class="`range-${index + 1}`">
          <div class="portal-range-image"><img :src="categoryImages[index]" alt=""></div><span class="portal-range-number">{{ tile.eyebrow }}</span><h3>{{ tile.title }}</h3><span class="portal-range-link">Explore range <ArrowRight :size="14"/></span>
        </RouterLink>
      </div>
      <p class="portal-range-note"><Check :size="14"/> Product availability and pricing are shown after you sign in to your customer account.</p>
    </section>

    <section v-if="content.benefits.enabled" id="benefits" class="portal-benefits-section">
      <header class="portal-benefits-heading"><span class="portal-section-eyebrow"><i></i>{{ content.benefits.eyebrow }}</span><h2>{{ content.benefits.heading }}</h2><span class="portal-heading-rule"></span></header>
      <div class="portal-benefit-grid">
        <article v-for="(card, index) in content.benefits.cards" :key="`${index}-${card.title}`" class="portal-benefit-card">
          <span class="portal-benefit-number">0{{ index + 1 }}</span>
          <span class="portal-benefit-icon"><ShoppingBasket v-if="index === 1" :size="21"/><ShieldCheck v-else-if="index === 2" :size="21"/><PackageSearch v-else :size="21"/></span>
          <h3>{{ card.title }}</h3><p>{{ card.body }}</p><span class="portal-benefit-bottom"><i></i> BUSINESS PORTAL</span>
        </article>
      </div>
    </section>

    <section v-if="content.cta.enabled" class="portal-landing-cta">
      <div class="portal-cta-decoration"><i></i><i></i><i></i><i></i></div>
      <span class="portal-section-eyebrow"><i></i>{{ content.cta.eyebrow }}</span>
      <h2>{{ content.cta.heading }}</h2><p>{{ content.cta.body }}</p>
      <RouterLink to="/login" class="portal-button-light">{{ content.cta.button_label }} <ArrowRight :size="16"/></RouterLink>
      <span class="portal-cta-signup">New to the portal? <RouterLink to="/register">Request access</RouterLink></span>
    </section>

    <footer class="portal-landing-footer">
      <RouterLink to="/" class="portal-landing-brand footer-brand"><img v-if="site.settings.logo_url" :src="site.settings.logo_url" :alt="`${site.settings.site_name} logo`"><span v-else class="portal-landing-mark">{{ site.settings.site_name.slice(0, 1).toUpperCase() || 'B' }}</span><span><strong>{{ site.settings.site_name }}</strong><small>{{ site.settings.tagline }}</small></span></RouterLink>
      <p>{{ site.settings.footer_text || 'A better way to do business together.' }}</p>
      <div class="portal-footer-links"><span v-if="site.settings.address">{{ site.settings.address }}</span><a v-if="site.settings.contact_email" :href="`mailto:${site.settings.contact_email}`">{{ site.settings.contact_email }}</a><a v-if="site.settings.contact_phone" :href="`tel:${site.settings.contact_phone}`">{{ site.settings.contact_phone }}</a><a v-if="site.settings.facebook_url" :href="site.settings.facebook_url" target="_blank" rel="noopener noreferrer">Facebook</a><a v-if="site.settings.instagram_url" :href="site.settings.instagram_url" target="_blank" rel="noopener noreferrer">Instagram</a><a v-if="site.settings.linkedin_url" :href="site.settings.linkedin_url" target="_blank" rel="noopener noreferrer">LinkedIn</a></div>
      <small>© {{ year }} {{ site.settings.site_name }}</small>
    </footer>
  </main>
</template>
