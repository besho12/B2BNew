<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Check, FileText, RotateCcw, Save, ShieldCheck } from '@lucide/vue'
import { api } from '../api'
import PortalOwnerLayout from '../components/PortalOwnerLayout.vue'

type MailTemplate = { key: string; name: string; description: string; subject: string; body: string; placeholders: string[]; is_customized: boolean }
const templates = ref<MailTemplate[]>([])
const selectedKey = ref('ticket_reply')
const loading = ref(true)
const saving = ref(false)
const resetting = ref(false)
const error = ref('')
const notice = ref('')
const selected = computed(() => templates.value.find(item => item.key === selectedKey.value) || null)
const previewValues: Record<string, string> = {
  USER_NAME: 'Alex Morgan', COMPANY_NAME: 'Northstar Cycles', INVITER_NAME: 'Jamie Lee', ROLE: 'Agent', ACCEPT_URL: 'https://portal.example/accept-invite', EXPIRY_DATE: '28 October 2026',
  SUMMARY: 'Product registration question', CASE_REFERENCE: 'CASE-1042', CURRENT_REPLY: 'We have updated your product registration.', REPLIES: 'We have updated your product registration.', DESCRIPTION: 'Please help register my product.', STATUS: 'In progress', STRING_TOKEN: 'CASE-1042', PORTAL_NAME: 'B2B Portal', URL: 'https://portal.example/verify',
}
const previewSubject = computed(() => interpolate(selected.value?.subject || ''))
const previewBody = computed(() => interpolate(selected.value?.body || ''))
function interpolate(value: string) { return value.replace(/\{\{([A-Z_]+)\}\}/g, (_, key: string) => previewValues[key] || `{{${key}}}`) }
function placeholderLabel(key: string) { return `{{${key}}}` }
async function load() {
  loading.value = true; error.value = ''
  try { const { data } = await api.get('/platform/mail-templates'); templates.value = data.templates || [] }
  catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to load mail templates.' }
  finally { loading.value = false }
}
async function save() {
  saving.value = true; error.value = ''; notice.value = ''
  try {
    if (!selected.value) return
    const { data } = await api.put('/platform/mail-templates', { templates: [{ key: selected.value.key, subject: selected.value.subject, body: selected.value.body }] })
    templates.value = data.templates || []; notice.value = data.message || 'Templates saved.'
  } catch (exception: any) { error.value = Object.values(exception.response?.data?.errors || { templates: [exception.response?.data?.message || 'Unable to save mail templates.'] }).flat().join(' ') }
  finally { saving.value = false }
}
async function resetSelected() {
  if (!selected.value || resetting.value) return
  resetting.value = true; error.value = ''; notice.value = ''
  try { const { data } = await api.delete(`/platform/mail-templates/${selected.value.key}`); templates.value = data.templates || []; notice.value = 'Default template restored.' }
  catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to restore this template.' }
  finally { resetting.value = false }
}
onMounted(load)
</script>

<template>
  <PortalOwnerLayout>
    <main class="mail-templates-page">
      <header class="mail-templates-heading">
        <div><span class="mail-templates-eyebrow"><FileText :size="14"/> COMMUNICATIONS</span><h1>Mail templates</h1><p>Manage the shared messages sent by account verification, workspace invitations, and support replies.</p></div>
        <span class="mail-templates-global"><ShieldCheck :size="15"/> Shared portal templates</span>
      </header>
      <div v-if="error" class="mail-templates-alert error" role="alert">{{ error }} <button type="button" @click="load">Reload</button></div>
      <div v-if="notice" class="mail-templates-alert success" role="status"><Check :size="15"/>{{ notice }}</div>
      <div v-if="loading" class="mail-templates-loading" aria-busy="true"><i v-for="n in 5" :key="n"></i></div>
      <div v-else class="mail-templates-layout">
        <nav class="mail-template-list" aria-label="Email template types">
          <button v-for="item in templates" :key="item.key" type="button" :class="{ active: item.key === selectedKey }" @click="selectedKey = item.key">
            <span class="mail-template-list-icon"><FileText :size="16"/></span><span><strong>{{ item.name }}</strong><small>{{ item.description }}</small></span><i :class="{ customized: item.is_customized }" :title="item.is_customized ? 'Customized' : 'Using default'"></i>
          </button>
          <p><i class="default-dot"></i> Default <i class="custom-dot"></i> Customized</p>
        </nav>

        <form v-if="selected" class="mail-template-editor" @submit.prevent="save">
          <header><div><span class="mail-templates-eyebrow">EDIT TEMPLATE</span><h2>{{ selected.name }}</h2><p>{{ selected.description }}</p></div><button class="mail-reset-button" type="button" :disabled="resetting || !selected.is_customized" @click="resetSelected"><RotateCcw :size="14"/>{{ resetting ? 'Restoring…' : 'Restore default' }}</button></header>
          <label>Subject<input v-model.trim="selected.subject" maxlength="190" required placeholder="Email subject"><small>Placeholders are replaced when the email is sent.</small></label>
          <label>Message<textarea v-model="selected.body" rows="12" maxlength="10000" required placeholder="Write the message body"></textarea></label>
          <div class="mail-placeholder-area"><strong>Available placeholders</strong><div><code v-for="placeholder in selected.placeholders" :key="placeholder">{{ placeholderLabel(placeholder) }}</code></div><small>Use only the placeholders listed for this message. Message content is sent as plain text.</small></div>
          <footer><span>Saving applies this template to future emails.</span><button class="mail-save-button" type="submit" :disabled="saving"><Save :size="15"/>{{ saving ? 'Saving…' : 'Save template' }}</button></footer>
        </form>

        <aside v-if="selected" class="mail-template-preview">
          <header><span class="mail-templates-eyebrow">LIVE PREVIEW</span><strong>{{ selected.name }}</strong><small>Example values are shown below.</small></header>
          <article><span>SUBJECT</span><strong>{{ previewSubject }}</strong><hr><pre>{{ previewBody }}</pre></article>
          <p><ShieldCheck :size="14"/> Template edits never change recipients or account permissions.</p>
        </aside>
      </div>
    </main>
  </PortalOwnerLayout>
</template>

<style scoped>
.mail-templates-page{max-width:1460px;margin:auto;color:#243a33}.mail-templates-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:22px}.mail-templates-eyebrow{display:inline-flex;align-items:center;gap:6px;color:#92794c;font-size:9px;font-weight:800;letter-spacing:.13em}.mail-templates-heading h1{margin:8px 0 5px;font:600 clamp(25px,3vw,34px)/1.1 "Manrope",sans-serif;letter-spacing:-.04em}.mail-templates-heading p,.mail-template-editor header p{margin:0;color:#798780;font-size:11px;line-height:1.6}.mail-templates-global{display:flex;align-items:center;gap:7px;padding:9px 11px;border:1px solid #dfe8e1;border-radius:8px;background:#fff;color:#60766a;font-size:9px;white-space:nowrap}.mail-templates-layout{display:grid;grid-template-columns:minmax(210px,255px) minmax(360px,1fr) minmax(230px,300px);align-items:start;gap:14px}.mail-template-list,.mail-template-editor,.mail-template-preview{border:1px solid #e2e8e1;border-radius:11px;background:#fff;box-shadow:0 5px 18px rgba(27,48,38,.025)}.mail-template-list{padding:8px}.mail-template-list>button{width:100%;display:flex;align-items:flex-start;gap:9px;padding:11px 8px;border:0;border-radius:7px;background:transparent;color:inherit;text-align:left;cursor:pointer}.mail-template-list>button:hover,.mail-template-list>button.active{background:#f0f5f0}.mail-template-list-icon{width:29px;height:29px;display:grid;place-items:center;flex:none;border-radius:8px;background:#eaf1eb;color:#587765}.mail-template-list>button>span:nth-child(2){display:grid;gap:4px;min-width:0;flex:1}.mail-template-list strong{color:#354a3e;font-size:10px}.mail-template-list small{color:#88938d;font-size:8px;line-height:1.4}.mail-template-list>button>i{width:7px;height:7px;flex:none;margin-top:5px;border-radius:50%;background:#a7b2ab}.mail-template-list>button>i.customized,.custom-dot{background:#4d956b}.mail-template-list>p{display:flex;align-items:center;gap:5px;margin:7px 5px 2px;padding-top:9px;border-top:1px solid #edf0ec;color:#89948d;font-size:8px}.default-dot,.custom-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:#a7b2ab}.custom-dot{margin-left:7px}.mail-template-editor{padding:19px}.mail-template-editor>header{display:flex;justify-content:space-between;gap:10px;margin-bottom:18px}.mail-template-editor h2{margin:5px 0;color:#30463b;font-size:16px}.mail-template-editor label{display:grid;gap:7px;margin:13px 0;color:#596a61;font-size:9px;font-weight:700}.mail-template-editor input,.mail-template-editor textarea{width:100%;padding:10px 11px;border:1px solid #dfe6df;border-radius:7px;outline:none;background:#fff;color:#32453b;font:400 11px/1.6 inherit}.mail-template-editor input:focus,.mail-template-editor textarea:focus{border-color:#849d8c;box-shadow:0 0 0 3px #eaf1eb}.mail-template-editor label small,.mail-placeholder-area small{color:#8a958e;font-size:8px;font-weight:400}.mail-template-editor textarea{resize:vertical;font-family:inherit;white-space:pre-wrap}.mail-reset-button{height:32px;display:inline-flex;align-items:center;gap:6px;padding:0 9px;border:1px solid #e0e7e0;border-radius:7px;background:#fff;color:#697970;font-size:8px;white-space:nowrap;cursor:pointer}.mail-reset-button:disabled{opacity:.5;cursor:not-allowed}.mail-placeholder-area{padding:12px;border-radius:8px;background:#f6f8f5}.mail-placeholder-area>strong{color:#56695e;font-size:9px}.mail-placeholder-area>div{display:flex;flex-wrap:wrap;gap:5px;margin:9px 0}.mail-placeholder-area code{padding:4px 6px;border:1px solid #e0e8df;border-radius:5px;background:#fff;color:#456551;font-size:8px}.mail-template-editor>footer{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:17px;padding-top:14px;border-top:1px solid #edf0ec}.mail-template-editor>footer>span{color:#87928b;font-size:8px}.mail-save-button{height:37px;display:inline-flex;align-items:center;gap:7px;padding:0 12px;border:0;border-radius:7px;background:#173b35;color:#fff;font-size:9px;font-weight:700;cursor:pointer}.mail-save-button:disabled{opacity:.6;cursor:wait}.mail-template-preview{padding:15px}.mail-template-preview>header{display:grid;gap:5px;margin-bottom:13px}.mail-template-preview>header strong{color:#344a3e;font-size:11px}.mail-template-preview>header small{color:#88938d;font-size:8px}.mail-template-preview article{padding:13px;border:1px solid #edf0ec;border-radius:8px;background:#fcfdfb}.mail-template-preview article>span{display:block;color:#929b95;font-size:7px;font-weight:800;letter-spacing:.1em}.mail-template-preview article>strong{display:block;margin:6px 0;color:#34483d;font-size:10px;line-height:1.5}.mail-template-preview hr{height:1px;margin:12px 0;border:0;background:#e9eee8}.mail-template-preview pre{margin:0;color:#627269;font:400 9px/1.7 inherit;white-space:pre-wrap;overflow-wrap:anywhere}.mail-template-preview>p{display:flex;align-items:flex-start;gap:6px;margin:11px 0 0;color:#7b8980;font-size:8px;line-height:1.5}.mail-template-preview>p svg{flex:none;color:#638872}.mail-templates-alert{display:flex;align-items:center;gap:8px;margin-bottom:12px;padding:11px 12px;border-radius:8px;font-size:10px}.mail-templates-alert button{margin-left:auto;border:0;background:transparent;color:inherit;text-decoration:underline;cursor:pointer}.mail-templates-alert.error{background:#fff0ec;color:#a0463a}.mail-templates-alert.success{background:#edf7ef;color:#467754}.mail-templates-loading{display:grid;gap:8px}.mail-templates-loading i{height:44px;border-radius:8px;background:linear-gradient(100deg,#edf1ed 20%,#f8f9f7 38%,#edf1ed 58%);background-size:220% 100%;animation:mail-shimmer 1.35s infinite}@keyframes mail-shimmer{to{background-position-x:-220%}}@media(max-width:1100px){.mail-templates-layout{grid-template-columns:220px minmax(0,1fr)}.mail-template-preview{grid-column:2}}@media(max-width:700px){.mail-templates-heading{align-items:flex-start;flex-direction:column}.mail-templates-layout{grid-template-columns:1fr}.mail-template-preview{grid-column:auto}.mail-template-list{display:grid;grid-template-columns:1fr}.mail-template-editor{padding:14px}.mail-template-editor>header,.mail-template-editor>footer{align-items:flex-start;flex-direction:column}.mail-save-button{justify-content:center;width:100%}}@media(prefers-reduced-motion:reduce){.mail-templates-loading i{animation:none}}
</style>
