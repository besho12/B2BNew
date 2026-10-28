<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Check, Globe2, Palette, RotateCcw, Save, Settings2, ShieldCheck, Sparkles, Trash2, Upload, X } from '@lucide/vue'
import { api } from '../api'
import PortalOwnerLayout from '../components/PortalOwnerLayout.vue'
import { cloneLandingContent, defaultLandingContent, useSiteSettingsStore, type PortalSiteSettings } from '../stores/siteSettings'

const site = useSiteSettingsStore()
const form = reactive<PortalSiteSettings>({ ...site.settings, landing_content: cloneLandingContent(site.settings.landing_content) })
const loading = ref(true)
const saving = ref(false)
const deletingLogo = ref(false)
const error = ref('')
const notice = ref('')
const selectedLogo = ref<File | null>(null)
const previewUrl = ref('')
const logoInput = ref<HTMLInputElement | null>(null)
const updatedAtLabel = computed(() => form.updated_at ? new Intl.DateTimeFormat('en-EG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(form.updated_at)) : 'Not saved yet')
const previewLogo = computed(() => previewUrl.value || form.logo_url || '')

const colorFields = [
  { key: 'primary_color', label: 'Primary', hint: 'Navigation and main actions' },
  { key: 'secondary_color', label: 'Secondary', hint: 'Supporting interface elements' },
  { key: 'accent_color', label: 'Accent', hint: 'Highlights and selected states' },
  { key: 'surface_color', label: 'Page surface', hint: 'Background behind content' },
] as const

function copySettings(value: PortalSiteSettings) { Object.assign(form, { ...value, landing_content: cloneLandingContent(value.landing_content) }) }
function clearPreview() { if (previewUrl.value) URL.revokeObjectURL(previewUrl.value); previewUrl.value = ''; selectedLogo.value = null }
function chooseLogo(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] || null
  error.value = ''
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
    error.value = 'Choose a JPEG, PNG, or WebP logo up to 2 MB.'
    if (logoInput.value) logoInput.value.value = ''
    return
  }
  clearPreview()
  selectedLogo.value = file
  previewUrl.value = URL.createObjectURL(file)
}
function resetDefaults() {
  Object.assign(form, {
    site_name: 'B2B Portal', tagline: 'Business customer portal', header_announcement: '', announcement_enabled: false,
    welcome_heading: 'Every move brings you closer to home.', welcome_message: 'A simpler way to stay connected with your business customers.',
    header_style: 'light', primary_color: '#173B3A', secondary_color: '#648779', accent_color: '#C8A66D', surface_color: '#F4F5F1',
    footer_text: '', contact_email: '', contact_phone: '', address: '', facebook_url: '', instagram_url: '', linkedin_url: '',
    landing_content: cloneLandingContent(defaultLandingContent),
  })
  selectedLogo.value = null
  if (logoInput.value) logoInput.value.value = ''
  clearPreview()
  notice.value = 'Defaults are shown in the form. Save to apply them to the portal.'
}
async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<PortalSiteSettings>('/platform/site-settings')
    copySettings(data)
    site.apply(data)
  } catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to load portal settings.' }
  finally { loading.value = false }
}
async function save() {
  saving.value = true
  error.value = ''
  notice.value = ''
  const payload = new FormData()
  for (const key of [
    'site_name', 'tagline', 'welcome_heading', 'welcome_message', 'header_announcement', 'header_style', 'primary_color', 'secondary_color', 'accent_color', 'surface_color',
    'footer_text', 'contact_email', 'contact_phone', 'address', 'facebook_url', 'instagram_url', 'linkedin_url',
  ] as const) payload.append(key, String(form[key] ?? ''))
  payload.append('landing_content', JSON.stringify(form.landing_content))
  payload.append('announcement_enabled', form.announcement_enabled ? '1' : '0')
  if (selectedLogo.value) payload.append('logo', selectedLogo.value)
  try {
    const { data } = await api.post('/platform/site-settings', payload)
    copySettings(data.settings)
    site.apply(data.settings)
    clearPreview()
    if (logoInput.value) logoInput.value.value = ''
    notice.value = data.message || 'Portal settings saved.'
  } catch (exception: any) {
    error.value = Object.values(exception.response?.data?.errors || { settings: [exception.response?.data?.message || 'Unable to save portal settings.'] }).flat().join(' ')
  } finally { saving.value = false }
}
async function removeLogo() {
  deletingLogo.value = true
  error.value = ''
  notice.value = ''
  try {
    const { data } = await api.delete('/platform/site-settings/logo')
    copySettings(data.settings)
    site.apply(data.settings)
    clearPreview()
    notice.value = data.message || 'Logo removed.'
  } catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to remove the logo.' }
  finally { deletingLogo.value = false }
}
onMounted(load)
onBeforeUnmount(clearPreview)
</script>

<template>
  <PortalOwnerLayout>
    <div class="master-settings-page">
      <header class="master-settings-heading">
        <div><span class="master-eyebrow"><Settings2 :size="14"/> PORTAL CONFIGURATION</span><h1>Master settings</h1><p>Set the shared brand, header, and contact details shown across the customer portal.</p></div>
        <span class="master-save-state"><Check :size="14"/> Last saved {{ updatedAtLabel }}</span>
      </header>

      <div v-if="error" class="master-message master-error" role="alert">{{ error }}<button type="button" @click="load">Retry</button></div>
      <div v-if="notice" class="master-message master-success" role="status">{{ notice }}<button type="button" aria-label="Dismiss message" @click="notice=''"><X :size="15"/></button></div>
      <div v-if="loading" class="master-loading" aria-busy="true"><i v-for="item in 6" :key="item"></i></div>

      <form v-else class="master-settings-grid" @submit.prevent="save">
        <div class="master-settings-main">
          <section class="master-panel">
            <header class="master-panel-heading"><span class="master-panel-icon"><Globe2 :size="17"/></span><div><h2>Site identity</h2><p>Name and description shown in the portal header.</p></div></header>
            <div class="master-form-grid">
              <label>Website name<input v-model.trim="form.site_name" required maxlength="80" placeholder="B2B Portal"></label>
              <label>Tagline<input v-model.trim="form.tagline" required maxlength="140" placeholder="Business customer portal"></label>
              <div class="master-logo-field"><span class="master-field-label">Portal logo</span><div class="master-logo-control"><span class="master-logo-preview"><img v-if="previewLogo" :src="previewLogo" :alt="`${form.site_name} logo preview`"><span v-else>{{ form.site_name.slice(0, 1).toUpperCase() || 'B' }}</span></span><span class="master-logo-copy"><strong>{{ selectedLogo?.name || (form.logo_url ? 'Current logo' : 'No logo uploaded') }}</strong><small>SVG is not accepted. Use JPEG, PNG, or WebP up to 2 MB.</small><span><input ref="logoInput" type="file" accept="image/jpeg,image/png,image/webp" @change="chooseLogo"><button type="button" class="master-small-button" @click="logoInput?.click()"><Upload :size="14"/> Choose image</button><button v-if="previewLogo" type="button" class="master-remove-logo" :disabled="deletingLogo" @click="selectedLogo ? clearPreview() : removeLogo()"><Trash2 :size="14"/> {{ selectedLogo ? 'Discard' : 'Remove' }}</button></span></span></div></div>
            </div>
          </section>

          <section class="master-panel">
            <header class="master-panel-heading"><span class="master-panel-icon"><Palette :size="17"/></span><div><h2>Brand and interface colors</h2><p>Accessible color tokens used across the customer workspace.</p></div></header>
            <div class="master-color-grid"><label v-for="color in colorFields" :key="color.key" class="master-color-field"><span><strong>{{ color.label }}</strong><small>{{ color.hint }}</small></span><span class="master-color-control"><input v-model="form[color.key]" type="color" :aria-label="`${color.label} color picker`"><input v-model.trim="form[color.key]" pattern="#[0-9A-Fa-f]{6}" maxlength="7" :aria-label="`${color.label} hex value`" required></span></label></div>
          </section>

          <section class="master-panel">
            <header class="master-panel-heading"><span class="master-panel-icon"><Sparkles :size="17"/></span><div><h2>Welcome page content</h2><p>Shape the first message customers see on the public welcome page.</p></div></header>
            <div class="master-form-grid"><label class="master-full-field">Main heading<input v-model.trim="form.welcome_heading" required maxlength="160" placeholder="Welcome to your business portal"></label><label class="master-full-field">Introduction<textarea v-model.trim="form.welcome_message" required maxlength="500" rows="3" placeholder="Briefly explain what customers can do here"></textarea></label><label>Primary button<input v-model.trim="form.landing_content.hero.primary_label" maxlength="80" required></label><label>Secondary link<input v-model.trim="form.landing_content.hero.secondary_label" maxlength="80" required></label></div>
          </section>

          <section class="master-panel master-landing-panel">
            <header class="master-panel-heading"><span class="master-panel-icon"><Sparkles :size="17"/></span><div><h2>Landing page promotion</h2><p>Pin a short muted promo video below the navigation, with a clear route into the portal.</p></div></header>
            <div class="master-form-grid">
              <label class="master-toggle-field master-full-field"><input v-model="form.landing_content.promo.enabled" type="checkbox"><span><strong>Show promotional video</strong><small>The video banner remains pinned below the site navigation while visitors browse.</small></span></label>
              <template v-if="form.landing_content.promo.enabled">
                <label class="master-full-field">MP4 or WebM video URL<input v-model.trim="form.landing_content.promo.video_url" type="url" required maxlength="2048" placeholder="https://cdn.example.com/promo.mp4"><small>Use a direct HTTPS MP4/WebM link. The video plays muted and respects reduced-motion settings.</small></label>
                <label class="master-full-field">Poster image URL<input v-model.trim="form.landing_content.promo.poster_url" type="url" maxlength="2048" placeholder="https://cdn.example.com/promo-poster.jpg"></label>
                <label>Promo eyebrow<input v-model.trim="form.landing_content.promo.eyebrow" maxlength="80" required></label>
                <label>Promo heading<input v-model.trim="form.landing_content.promo.heading" maxlength="160" required></label>
                <label class="master-full-field">Promo message<textarea v-model.trim="form.landing_content.promo.body" rows="2" maxlength="400" required></textarea></label>
                <label>Button label<input v-model.trim="form.landing_content.promo.button_label" maxlength="80" required></label>
                <label>Button destination<AppSelect v-model="form.landing_content.promo.button_path"><option value="/products">Product catalogue</option><option value="/register">Create an account</option><option value="/login">Sign in</option></AppSelect></label>
              </template>
            </div>
          </section>

          <section class="master-panel master-landing-panel">
            <header class="master-panel-heading"><span class="master-panel-icon"><Globe2 :size="17"/></span><div><h2>Landing page sections</h2><p>Choose which customer-portal sections appear and tune their content.</p></div></header>
            <div class="master-form-grid">
              <label class="master-toggle-field master-full-field"><input v-model="form.landing_content.catalog.enabled" type="checkbox"><span><strong>Show catalogue introduction</strong><small>Explain account-specific products and availability.</small></span></label>
              <template v-if="form.landing_content.catalog.enabled">
                <label>Eyebrow<input v-model.trim="form.landing_content.catalog.eyebrow" maxlength="80" required></label>
                <label>Heading<input v-model.trim="form.landing_content.catalog.heading" maxlength="160" required></label>
                <label class="master-full-field">Message<textarea v-model.trim="form.landing_content.catalog.body" rows="2" maxlength="500" required></textarea></label>
                <label v-for="(tile, index) in form.landing_content.catalog.tiles" :key="index" class="master-full-field master-landing-card-field">Catalogue card {{ index + 1 }} label<input v-model.trim="tile.eyebrow" maxlength="50" required><input v-model.trim="tile.title" maxlength="100" required :aria-label="`Catalogue card ${index + 1} title`"></label>
              </template>
              <label class="master-toggle-field master-full-field"><input v-model="form.landing_content.benefits.enabled" type="checkbox"><span><strong>Show portal benefits</strong><small>Three editable cards describing key customer workflows.</small></span></label>
              <template v-if="form.landing_content.benefits.enabled">
                <label class="master-full-field">Section heading<input v-model.trim="form.landing_content.benefits.heading" maxlength="160" required></label>
                <label v-for="(card, index) in form.landing_content.benefits.cards" :key="index" class="master-full-field master-landing-card-field">Card {{ index + 1 }} title<input v-model.trim="card.title" maxlength="100" required><textarea v-model.trim="card.body" rows="2" maxlength="300" required :aria-label="`Card ${index + 1} description`"></textarea></label>
              </template>
              <label class="master-toggle-field master-full-field"><input v-model="form.landing_content.cta.enabled" type="checkbox"><span><strong>Show closing call to action</strong><small>Give visitors a clear next step at the end of the landing page.</small></span></label>
              <template v-if="form.landing_content.cta.enabled">
                <label class="master-full-field">Heading<input v-model.trim="form.landing_content.cta.heading" maxlength="160" required></label>
                <label class="master-full-field">Message<textarea v-model.trim="form.landing_content.cta.body" rows="2" maxlength="400" required></textarea></label>
                <label>Button label<input v-model.trim="form.landing_content.cta.button_label" maxlength="80" required></label>
              </template>
            </div>
          </section>

          <section class="master-panel">
            <header class="master-panel-heading"><span class="master-panel-icon"><Sparkles :size="17"/></span><div><h2>Header and announcement</h2><p>Choose a header treatment and publish a short portal notice.</p></div></header>
            <div class="master-form-grid">
              <label>Header appearance<AppSelect v-model="form.header_style"><option value="light">Light</option><option value="dark">Dark</option><option value="brand">Secondary brand color</option></AppSelect></label>
              <label class="master-toggle-field"><input v-model="form.announcement_enabled" type="checkbox"><span><strong>Show announcement</strong><small>Display a message above the portal navigation.</small></span></label>
              <label class="master-full-field">Announcement text<textarea v-model.trim="form.header_announcement" :required="form.announcement_enabled" maxlength="220" rows="2" placeholder="Share a service notice or a short update"></textarea><small>{{ form.header_announcement?.length || 0 }} / 220</small></label>
            </div>
          </section>

          <section class="master-panel">
            <header class="master-panel-heading"><span class="master-panel-icon"><Globe2 :size="17"/></span><div><h2>Contact and footer</h2><p>Public contact details and social profiles for the site footer.</p></div></header>
            <div class="master-form-grid">
              <label>Contact email<input v-model.trim="form.contact_email" type="email" maxlength="255" placeholder="hello@example.com"></label>
              <label>Contact phone<input v-model.trim="form.contact_phone" type="tel" maxlength="40" placeholder="+20 2 0000 0000"></label>
              <label class="master-full-field">Business address<input v-model.trim="form.address" maxlength="255" placeholder="City, country"></label>
              <label>Facebook URL<input v-model.trim="form.facebook_url" type="url" placeholder="https://facebook.com/…"></label>
              <label>Instagram URL<input v-model.trim="form.instagram_url" type="url" placeholder="https://instagram.com/…"></label>
              <label>LinkedIn URL<input v-model.trim="form.linkedin_url" type="url" placeholder="https://linkedin.com/company/…"></label>
              <label class="master-full-field">Footer note<textarea v-model.trim="form.footer_text" maxlength="500" rows="2" placeholder="A short company or support note"></textarea></label>
            </div>
          </section>
          <div class="master-form-actions"><button type="button" class="master-reset-button" @click="resetDefaults"><RotateCcw :size="15"/> Restore defaults</button><button type="submit" class="master-save-button" :disabled="saving"><Save :size="16"/>{{ saving ? 'Saving settings…' : 'Save changes' }}</button></div>
        </div>

        <aside class="master-preview-column">
          <section class="master-preview-panel">
            <header><span><ShieldCheck :size="15"/> LIVE PREVIEW</span><small>Updates as you edit</small></header>
            <div class="master-site-preview" :style="{'--preview-primary': form.primary_color, '--preview-secondary': form.secondary_color, '--preview-accent': form.accent_color, '--preview-surface': form.surface_color}">
              <div v-if="form.announcement_enabled && form.header_announcement" class="master-preview-announcement">{{ form.header_announcement }}</div>
              <div class="master-preview-header" :class="`preview-header-${form.header_style}`"><span class="master-preview-logo"><img v-if="previewLogo" :src="previewLogo" alt=""><i v-else>{{ form.site_name.slice(0,1).toUpperCase() || 'B' }}</i></span><span class="master-preview-name"><strong>{{ form.site_name || 'Website name' }}</strong><small>{{ form.tagline || 'Short website tagline' }}</small></span><button type="button">Products</button></div>
              <div v-if="form.landing_content.promo.enabled" class="master-preview-promo"><div><span>{{ form.landing_content.promo.eyebrow || 'PROMOTION' }}</span><strong>{{ form.landing_content.promo.heading || 'Promo heading' }}</strong><small>{{ form.landing_content.promo.body || 'Promo description' }}</small></div><i>▶</i></div>
              <div class="master-preview-body"><span>BUSINESS PORTAL</span><h3>{{ form.welcome_heading || 'Your welcome heading' }}</h3><p>{{ form.welcome_message || 'Your welcome introduction' }}</p><button type="button">Browse products</button><div class="master-preview-card"><i></i><span><b></b><small></small></span></div></div>
              <div class="master-preview-section-list"><span v-for="section in [{label:'Catalogue introduction',enabled:form.landing_content.catalog.enabled},{label:'Customer benefits',enabled:form.landing_content.benefits.enabled},{label:'Closing call to action',enabled:form.landing_content.cta.enabled}]" :key="section.label" :class="{disabled:!section.enabled}"><i>{{ section.enabled ? '✓' : '–' }}</i>{{ section.label }}</span></div>
              <div class="master-preview-catalog"><div><strong>Categories</strong><span>Products</span></div><ul><li><b>−</b> Cleaning<ul><li><i></i> Bike cleaners</li><li><i></i> Lubricants</li></ul></li><li><b>+</b> Workshop</li></ul><div class="master-preview-product"><i></i><span><b></b><small></small></span></div></div>
            </div>
            <p class="master-preview-note">Review the shared header, welcome page, and nested catalog filters. Account data and prices remain account-specific.</p>
          </section>
          <section class="master-guidance"><span><Check :size="15"/></span><div><strong>One shared identity</strong><p>These settings apply across customer workspaces. Company logos and data stay scoped to their own accounts.</p></div></section>
        </aside>
      </form>
    </div>
  </PortalOwnerLayout>
</template>

<style scoped>
.master-settings-page{max-width:1440px;margin:auto;color:#243a33}.master-settings-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:24px}.master-eyebrow{display:inline-flex;align-items:center;gap:7px;color:#8d754b;font-size:9px;font-weight:800;letter-spacing:.13em}.master-settings-heading h1{margin:9px 0 6px;font:600 clamp(25px,3vw,34px)/1.1 "Manrope",sans-serif;letter-spacing:-.045em}.master-settings-heading p{margin:0;color:#798780;font-size:11px;line-height:1.6}.master-save-state{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border:1px solid #e1e8e0;border-radius:8px;background:#fff;color:#75837a;font-size:9px;white-space:nowrap}.master-save-state svg{color:#5c9576}.master-settings-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(260px,340px);align-items:start;gap:18px}.master-settings-main{display:grid;gap:13px;min-width:0}.master-panel,.master-preview-panel,.master-guidance{border:1px solid #e2e8e1;border-radius:12px;background:#fff;box-shadow:0 5px 18px rgba(27,48,38,.025)}.master-panel{padding:19px}.master-panel-heading{display:flex;align-items:flex-start;gap:11px;margin-bottom:18px}.master-panel-icon{width:34px;height:34px;display:grid;place-items:center;border-radius:9px;background:#eff4ef;color:#537769}.master-panel-heading h2{margin:2px 0 4px;color:#2d4339;font-size:13px;font-weight:700}.master-panel-heading p{margin:0;color:#8a958e;font-size:9px;line-height:1.5}.master-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.master-form-grid>label,.master-full-field{display:grid;align-content:start;gap:6px;color:#596a61;font-size:9px;font-weight:700}.master-form-grid input:not([type=checkbox]):not([type=file]),.master-form-grid select,.master-form-grid textarea{width:100%;min-height:38px;padding:9px 10px;border:1px solid #dfe6df;border-radius:7px;outline:none;background:#fff;color:#34493f;font:500 10px "DM Sans",sans-serif}.master-form-grid textarea{resize:vertical;line-height:1.5}.master-form-grid input:focus,.master-form-grid select:focus,.master-form-grid textarea:focus{border-color:#8ca99a;box-shadow:0 0 0 3px rgba(92,129,109,.1)}.master-full-field{grid-column:1/-1}.master-full-field>small{justify-self:end;color:#929c95;font-size:8px;font-weight:500}.master-logo-field{grid-column:1/-1;display:grid;gap:8px}.master-field-label{color:#596a61;font-size:9px;font-weight:700}.master-logo-control{display:flex;align-items:center;gap:12px;padding:11px;border:1px dashed #d6e0d7;border-radius:9px;background:#fbfcfa}.master-logo-preview{width:52px;height:52px;display:grid;place-items:center;overflow:hidden;border:1px solid #e3e9e2;border-radius:10px;background:#f2f5f0;color:#55786b;font-size:18px;font-weight:700}.master-logo-preview img{width:100%;height:100%;object-fit:contain}.master-logo-copy{display:grid;gap:5px;min-width:0}.master-logo-copy>strong{overflow:hidden;color:#44584c;font-size:9px;text-overflow:ellipsis;white-space:nowrap}.master-logo-copy>small{color:#929b94;font-size:8px}.master-logo-copy>span{display:flex;gap:9px;align-items:center}.master-logo-copy input[type=file]{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.master-small-button,.master-remove-logo{display:inline-flex;align-items:center;gap:5px;padding:0;border:0;background:transparent;color:#426d5d;font-size:8px;font-weight:700;cursor:pointer}.master-remove-logo{color:#a45148}.master-remove-logo:disabled{opacity:.5}.master-color-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.master-color-field{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px;border:1px solid #e8ede7;border-radius:9px;background:#fcfdfb}.master-color-field>span:first-child{display:grid;gap:4px}.master-color-field strong{color:#44584c;font-size:9px}.master-color-field small{color:#929c95;font-size:8px}.master-color-control{display:flex;align-items:center;gap:6px}.master-color-control input[type=color]{width:30px;height:30px;padding:2px;border:1px solid #dfe6df;border-radius:7px;background:#fff;cursor:pointer}.master-color-control input[type=text]{width:76px!important;min-height:30px!important;padding:5px 6px!important;font:600 9px ui-monospace,monospace!important;text-transform:uppercase}.master-toggle-field{display:flex!important;align-items:center;gap:9px!important;padding:7px 0}.master-toggle-field>input{width:16px;height:16px;accent-color:#4e7968}.master-toggle-field>span{display:grid;gap:4px}.master-toggle-field strong{color:#50645a;font-size:9px}.master-toggle-field small{color:#929c95;font-size:8px;font-weight:500}.master-form-actions{position:sticky;bottom:10px;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px;border:1px solid #e1e8e0;border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 10px 30px rgba(30,48,39,.09);backdrop-filter:blur(12px)}.master-reset-button,.master-save-button{min-height:36px;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:8px 12px;border-radius:7px;font-size:9px;font-weight:700;cursor:pointer}.master-reset-button{border:1px solid #dfe5df;background:#fff;color:#66766c}.master-save-button{border:1px solid #1e413b;background:#1e413b;color:#fff}.master-save-button:disabled{opacity:.6;cursor:wait}.master-preview-column{position:sticky;top:15px;display:grid;gap:12px}.master-preview-panel{overflow:hidden}.master-preview-panel>header{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:13px;border-bottom:1px solid #edf0ed}.master-preview-panel>header>span{display:inline-flex;align-items:center;gap:6px;color:#557967;font-size:8px;font-weight:800;letter-spacing:.1em}.master-preview-panel>header>small{color:#9aa39d;font-size:8px}.master-site-preview{overflow:hidden;margin:12px;border:1px solid #e2e7e1;border-radius:8px;background:var(--preview-surface)}.master-preview-announcement{padding:7px 9px;background:var(--preview-accent);color:#253c36;text-align:center;font-size:7px;font-weight:700}.master-preview-header{min-height:49px;display:flex;align-items:center;gap:7px;padding:8px;background:#fff;color:#32483d}.preview-header-dark{background:var(--preview-primary);color:#fff}.preview-header-brand{background:var(--preview-secondary);color:#fff}.master-preview-logo{width:23px;height:23px;display:grid;place-items:center;overflow:hidden;border-radius:6px;background:#f1f4f0}.master-preview-logo img{width:100%;height:100%;object-fit:contain}.master-preview-logo i{color:var(--preview-primary);font-size:9px;font-style:normal;font-weight:800}.master-preview-name{display:grid;gap:2px;min-width:0;flex:1}.master-preview-name strong{overflow:hidden;font-size:8px;text-overflow:ellipsis;white-space:nowrap}.master-preview-name small{overflow:hidden;opacity:.7;font-size:6px;text-overflow:ellipsis;white-space:nowrap}.master-preview-header>button,.master-preview-body>button{padding:5px 7px;border:0;border-radius:5px;background:var(--preview-primary);color:#fff;font-size:6px}.preview-header-dark>button,.preview-header-brand>button{background:rgba(255,255,255,.18)}.master-preview-body{min-height:175px;padding:18px 14px;background:linear-gradient(145deg,#fff,var(--preview-surface));}.master-preview-body>span{color:var(--preview-secondary);font-size:6px;font-weight:800;letter-spacing:.12em}.master-preview-body h3{margin:9px 0 5px;color:var(--preview-primary);font:600 17px/1.1 "Manrope",sans-serif;letter-spacing:-.05em}.master-preview-body>p{margin:0 0 12px;color:#7c8981;font-size:7px}.master-preview-body>button{padding:7px 9px;background:var(--preview-primary);font-weight:700}.master-preview-card{display:flex;align-items:center;gap:8px;margin-top:17px;padding:8px;border:1px solid #e8ece7;border-radius:7px;background:#fff}.master-preview-card>i{width:28px;height:25px;border-radius:5px;background:linear-gradient(140deg,var(--preview-secondary),var(--preview-accent))}.master-preview-card>span{display:grid;gap:5px;flex:1}.master-preview-card b,.master-preview-card small{height:4px;border-radius:4px;background:#dae1da}.master-preview-card small{width:60%;height:3px;background:#edf0ed}.master-preview-note{margin:0;padding:0 13px 13px;color:#909a93;font-size:8px;line-height:1.55}.master-guidance{display:flex;align-items:flex-start;gap:9px;padding:13px}.master-guidance>span{width:24px;height:24px;display:grid;place-items:center;flex:none;border-radius:50%;background:#eaf3ec;color:#54866a}.master-guidance strong{color:#496254;font-size:9px}.master-guidance p{margin:4px 0 0;color:#89948d;font-size:8px;line-height:1.55}.master-message{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;padding:10px 12px;border-radius:8px;font-size:9px}.master-message button{display:inline-flex;align-items:center;gap:5px;border:0;background:transparent;color:inherit;font-size:9px;font-weight:700;cursor:pointer}.master-error{background:#fff0ed;color:#9c4138}.master-success{background:#eaf4ec;color:#3d7656}.master-loading{display:grid;gap:10px;max-width:900px}.master-loading i{height:75px;border-radius:10px;background:linear-gradient(100deg,#e8ede7 20%,#f8faf7 45%,#e8ede7 70%);background-size:200% 100%;animation:master-shimmer 1.1s infinite}@keyframes master-shimmer{to{background-position-x:-200%}}@media(max-width:1050px){.master-settings-grid{grid-template-columns:minmax(0,1fr) 270px;gap:12px}.master-panel{padding:15px}.master-color-grid{grid-template-columns:1fr}}@media(max-width:820px){.master-settings-grid{grid-template-columns:1fr}.master-preview-column{position:static;grid-row:1}.master-preview-panel{max-width:none}.master-site-preview{max-width:420px;margin-inline:auto}.master-preview-panel>header{padding-inline:15px}}@media(max-width:560px){.master-settings-heading{align-items:flex-start;flex-direction:column}.master-save-state{white-space:normal}.master-form-grid,.master-color-grid{grid-template-columns:1fr}.master-full-field,.master-logo-field{grid-column:auto}.master-panel{padding:13px}.master-logo-copy>small{line-height:1.45}.master-form-actions{bottom:6px}.master-reset-button,.master-save-button{padding-inline:9px;font-size:8px}}
.master-preview-catalog{margin:0 9px 10px;padding:9px;border:1px solid #e4e9e3;border-radius:7px;background:#fff;color:#56665d}.master-preview-catalog>div:first-child{display:flex;justify-content:space-between;padding-bottom:6px;border-bottom:1px solid #edf0ed;font-size:6px}.master-preview-catalog>div:first-child strong{color:var(--preview-primary)}.master-preview-catalog>div:first-child span{color:#89968e}.master-preview-catalog ul{margin:6px 0;padding-left:8px;list-style:none;font-size:6px}.master-preview-catalog li{padding:3px 0}.master-preview-catalog li>b{margin-right:4px;color:var(--preview-secondary)}.master-preview-catalog ul ul{margin:2px 0 0;padding-left:12px;color:#78877e}.master-preview-catalog li i{display:inline-block;width:5px;height:5px;margin-right:3px;border:1px solid var(--preview-accent);border-radius:1px}.master-preview-product{display:flex;align-items:center;gap:7px;margin-top:7px;padding:6px;border:1px solid #edf0ed;border-radius:5px}.master-preview-product>i{width:24px;height:21px;border-radius:4px;background:linear-gradient(140deg,var(--preview-secondary),var(--preview-accent))}.master-preview-product>span{display:grid;gap:4px;flex:1}.master-preview-product b,.master-preview-product small{height:3px;border-radius:3px;background:#dce3dc}.master-preview-product small{width:55%;background:#edf0ed}
.master-landing-card-field{padding:11px;border:1px solid #e8ede7;border-radius:8px;background:#fbfcfa}.master-preview-promo{position:relative;min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:8px;overflow:hidden;padding:10px 11px;background:linear-gradient(110deg,var(--preview-primary),var(--preview-secondary));color:#fff}.master-preview-promo>div{display:grid;gap:4px;min-width:0}.master-preview-promo span{color:var(--preview-accent);font-size:5px;font-weight:800;letter-spacing:.12em}.master-preview-promo strong{overflow:hidden;font-size:9px;text-overflow:ellipsis;white-space:nowrap}.master-preview-promo small{overflow:hidden;color:#e1e9e2;font-size:6px;text-overflow:ellipsis;white-space:nowrap}.master-preview-promo>i{width:24px;height:24px;display:grid;place-items:center;flex:none;border:1px solid #ffffff70;border-radius:50%;font-size:8px;font-style:normal}
.master-preview-section-list{display:grid;grid-template-columns:1fr 1fr;gap:4px;padding:8px 9px 0}.master-preview-section-list>span{display:flex;align-items:center;gap:4px;color:#64756b;font-size:5px}.master-preview-section-list>span i{color:#4d8a67;font-size:7px;font-style:normal}.master-preview-section-list>span.disabled{color:#a8b0aa}.master-preview-section-list>span.disabled i{color:#a8b0aa}
</style>
