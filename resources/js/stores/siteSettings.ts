import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../api'

export type LandingCard = { title: string; body: string }
export type LandingTile = { eyebrow: string; title: string }
export type LandingContent = {
  hero: { primary_label: string; secondary_label: string }
  promo: { enabled: boolean; video_url: string; poster_url: string; eyebrow: string; heading: string; body: string; button_label: string; button_path: '/products' | '/register' | '/login' }
  catalog: { enabled: boolean; eyebrow: string; heading: string; body: string; tiles: LandingTile[] }
  benefits: { enabled: boolean; eyebrow: string; heading: string; cards: LandingCard[] }
  cta: { enabled: boolean; eyebrow: string; heading: string; body: string; button_label: string }
}

export const defaultLandingContent: LandingContent = {
  hero: { primary_label: 'Enter your account', secondary_label: 'Explore the range' },
  promo: {
    enabled: true,
    video_url: 'https://cdn.shopify.com/videos/c/o/v/23691f5531374e26b516e8b037792756.mp4',
    poster_url: '', eyebrow: 'BUILT FOR EVERY RIDE', heading: 'The next ride starts here.',
    body: 'Discover the products, support and tools that keep your business moving.',
    button_label: 'Explore the catalogue', button_path: '/products',
  },
  catalog: {
    enabled: true, eyebrow: 'YOUR BUSINESS CATALOGUE', heading: 'Everything your customers need, in one place.',
    body: 'Browse the full product range, see account-specific availability and find the right items faster.',
    tiles: [
      { eyebrow: '01 / CARE', title: 'Cleaning & care' },
      { eyebrow: '02 / WORKSHOP', title: 'Tools & workshop' },
      { eyebrow: '03 / RIDE', title: 'Everyday essentials' },
    ],
  },
  benefits: {
    enabled: true, eyebrow: 'A BETTER WAY TO ORDER', heading: 'Built around the way your business works.',
    cards: [
      { title: 'Find the right products', body: 'Search and filter the catalogue, then save favourites for the next order.' },
      { title: 'Order at your pace', body: 'Build a basket line by line or enter multiple product codes at once.' },
      { title: 'Stay in control', body: 'Switch customer accounts and review account details, addresses and order history.' },
    ],
  },
  cta: {
    enabled: true, eyebrow: 'READY WHEN YOU ARE', heading: 'A simpler way to keep business moving.',
    body: 'Sign in to your account to browse products and prepare your next order.', button_label: 'Sign in to the portal',
  },
}

export function cloneLandingContent(value: LandingContent): LandingContent {
  return JSON.parse(JSON.stringify(value)) as LandingContent
}

export type PortalSiteSettings = {
  site_name: string
  tagline: string
  welcome_heading: string
  welcome_message: string
  header_announcement: string | null
  announcement_enabled: boolean
  header_style: 'light' | 'dark' | 'brand'
  primary_color: string
  secondary_color: string
  accent_color: string
  surface_color: string
  footer_text: string | null
  contact_email: string | null
  contact_phone: string | null
  address: string | null
  facebook_url: string | null
  instagram_url: string | null
  linkedin_url: string | null
  landing_content: LandingContent
  logo_url: string | null
  updated_at: string | null
}

const defaults: PortalSiteSettings = {
  site_name: 'B2B Portal', tagline: 'Business customer portal',
  welcome_heading: 'A clearer view of your business.', welcome_message: 'A simpler way to stay connected with your business customers.', header_announcement: '',
  announcement_enabled: false, header_style: 'light', primary_color: '#173B3A',
  secondary_color: '#648779', accent_color: '#C8A66D', surface_color: '#F4F5F1',
  footer_text: '', contact_email: '', contact_phone: '', address: '', facebook_url: '',
  instagram_url: '', linkedin_url: '', logo_url: null, updated_at: null,
  landing_content: cloneLandingContent(defaultLandingContent),
}

export const useSiteSettingsStore = defineStore('site-settings', () => {
  const settings = ref<PortalSiteSettings>({ ...defaults })
  const loading = ref(false)
  const loaded = ref(false)

  function apply(value: Partial<PortalSiteSettings>) {
    settings.value = { ...settings.value, ...value }
    const root = document.documentElement
    root.style.setProperty('--portal-primary', settings.value.primary_color)
    root.style.setProperty('--portal-secondary', settings.value.secondary_color)
    root.style.setProperty('--portal-accent', settings.value.accent_color)
    root.style.setProperty('--portal-surface', settings.value.surface_color)
    root.style.setProperty('--ink', settings.value.primary_color)
    root.style.setProperty('--gold', settings.value.accent_color)
  }

  async function load() {
    if (loading.value) return
    loading.value = true
    try {
      const { data } = await api.get<PortalSiteSettings>('/site-settings')
      apply(data)
      loaded.value = true
    } catch {
      apply(defaults)
    } finally { loading.value = false }
  }

  return { settings, loading, loaded, load, apply }
})
