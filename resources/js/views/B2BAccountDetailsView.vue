<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Building2, Check, Copy, Mail, Phone, RefreshCw, UserRound } from '@lucide/vue'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'

type AccountDetails = { customer_code: string; customer_name: string; first_name: string; surname: string; position: string; email: string; telephone: string; mobile: string }
const auth = useAuthStore()
const details = ref<AccountDetails | null>(null)
const accountDraft = ref(auth.selectedPortalAccount || '')
const loading = ref(false)
const error = ref('')
const codeCopied = ref(false)
const copyError = ref('')
const account = computed(() => auth.portalAccounts.find(item => item.code === auth.selectedPortalAccount) || null)
const customerCode = computed(() => details.value?.customer_code || account.value?.code || '')
const fullName = computed(() => [details.value?.first_name, details.value?.surname].filter(Boolean).join(' ') || auth.bootstrap?.user.name || '—')

async function load() {
  if (auth.portalDemoMode) return
  loading.value = true; error.value = ''; details.value = null
  try {
    const { data } = await api.get<AccountDetails>('/portal/account-details')
    details.value = data
  } catch (exception: any) {
    error.value = exception.response?.data?.message || 'Unable to load customer account details.'
  } finally { loading.value = false }
}
async function changeAccount() {
  if (!accountDraft.value || accountDraft.value === auth.selectedPortalAccount) return
  try { await auth.selectPortalAccount(accountDraft.value); await load() }
  catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to switch customer account.' }
}
async function copyCustomerCode() {
  if (!customerCode.value) return
  codeCopied.value = false
  copyError.value = ''
  try {
    if (!navigator.clipboard?.writeText) throw new Error('Clipboard access requires a secure browser connection.')
    await navigator.clipboard.writeText(customerCode.value)
    codeCopied.value = true
    window.setTimeout(() => { codeCopied.value = false }, 2000)
  } catch (exception: any) {
    copyError.value = exception.message || 'Unable to copy the customer code. Select the code to copy it manually.'
  }
}
onMounted(() => { if (!auth.portalDemoMode) void load() })
</script>

<template>
  <section class="page-heading"><div><span class="eyebrow">B2B ACCOUNT</span><h1>Customer account</h1><p>Contact and company information from your selected B2B account.</p></div><AppSelect v-if="auth.portalAccounts.length > 1" v-model="accountDraft" class="b2b-account-switch" aria-label="Customer account" @change="changeAccount"><option v-for="item in auth.portalAccounts" :key="item.code" :value="item.code">{{ item.code }} · {{ item.name }}</option></AppSelect><span v-else class="b2b-current-account"><Building2 :size="16"/> {{ account?.code || (auth.portalDemoMode ? 'DEMO ACCOUNT' : 'Customer account') }}</span></section>

  <div v-if="error" class="form-error b2b-account-error" role="alert">{{ error }} <button class="button secondary" @click="load">Retry</button></div>
  <section v-if="loading" class="b2b-account-skeleton" aria-label="Loading customer account" aria-busy="true"><div v-for="item in 2" :key="item" class="data-panel"><i></i><i></i><i></i><i></i></div></section>
  <div v-else-if="auth.portalDemoMode" class="b2b-demo-account-note"><span><Building2 :size="20"/></span><div><strong>Demo account</strong><p>ERP company details are shown after connecting the live B2B service. Your local workspace profile remains available in the profile menu.</p></div></div>
  <div v-else-if="details" class="b2b-account-details">
    <section class="data-panel b2b-account-card"><header><span><Building2 :size="18"/></span><div><small>COMPANY</small><h2>{{ details.customer_name || account?.name || 'Customer account' }}</h2></div></header><dl><div><dt>Customer code</dt><dd class="b2b-customer-code"><span>{{ customerCode || '—' }}</span><button v-if="customerCode" type="button" class="b2b-copy-code" :aria-label="codeCopied ? 'Customer code copied' : 'Copy customer code'" @click="copyCustomerCode"><Check v-if="codeCopied" :size="13"/><Copy v-else :size="13"/>{{ codeCopied ? 'Copied' : 'Copy' }}</button></dd></div><div><dt>Account name</dt><dd>{{ details.customer_name || account?.name || '—' }}</dd></div></dl><p v-if="codeCopied" class="b2b-copy-feedback" role="status">Customer code copied to clipboard.</p><p v-if="copyError" class="b2b-copy-error" role="alert">{{ copyError }}</p></section>
    <section class="data-panel b2b-account-card"><header><span><UserRound :size="18"/></span><div><small>PRIMARY CONTACT</small><h2>{{ fullName }}</h2></div></header><dl><div><dt>Position</dt><dd>{{ details.position || '—' }}</dd></div><div><dt>Email</dt><dd><a v-if="details.email || auth.bootstrap?.user.email" :href="`mailto:${details.email || auth.bootstrap?.user.email}`"><Mail :size="14"/> {{ details.email || auth.bootstrap?.user.email }}</a><span v-else>—</span></dd></div><div><dt>Telephone</dt><dd><a v-if="details.telephone" :href="`tel:${details.telephone}`"><Phone :size="14"/> {{ details.telephone }}</a><span v-else>—</span></dd></div><div><dt>Mobile</dt><dd><a v-if="details.mobile" :href="`tel:${details.mobile}`"><Phone :size="14"/> {{ details.mobile }}</a><span v-else>—</span></dd></div></dl></section>
  </div>
</template>

<style scoped>
.b2b-customer-code{display:flex;align-items:center;gap:10px}.b2b-customer-code>span{user-select:all}.b2b-copy-code{display:inline-flex;align-items:center;gap:4px;padding:4px 6px;border:1px solid #dfe7e0;border-radius:6px;background:#f8faf7;color:#4b6e5e;font-size:8px;font-weight:700;cursor:pointer}.b2b-copy-code:hover{background:#edf4ee}.b2b-copy-feedback,.b2b-copy-error{margin:0 20px 12px;font-size:9px}.b2b-copy-feedback{color:#46775a}.b2b-copy-error{color:#a54840}
.b2b-account-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.b2b-account-card{overflow:hidden;border:1px solid var(--line);border-radius:12px;background:#fff}.b2b-account-card>header{display:flex;align-items:center;gap:12px;padding:20px;border-bottom:1px solid var(--line)}.b2b-account-card>header>span,.b2b-demo-account-note>span{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#eaf0eb;color:#557a69}.b2b-account-card header small{color:#8b968f;font-size:8px;font-weight:700;letter-spacing:.13em}.b2b-account-card h2{margin:4px 0 0;color:#253a32;font-size:15px}.b2b-account-card dl{margin:0;padding:4px 20px}.b2b-account-card dl>div{display:grid;grid-template-columns:minmax(100px,.75fr) minmax(0,1.25fr);gap:12px;padding:14px 0;border-bottom:1px solid #eff1ef}.b2b-account-card dl>div:last-child{border-bottom:0}.b2b-account-card dt{color:#7d8983;font-size:10px}.b2b-account-card dd{margin:0;color:#344940;font-size:10px;font-weight:600;overflow-wrap:anywhere}.b2b-account-card dd a{display:inline-flex;align-items:center;gap:6px;color:inherit;text-decoration:none}.b2b-demo-account-note{display:flex;align-items:center;gap:14px;padding:22px;border:1px solid #e5eae5;border-radius:12px;background:#fff}.b2b-demo-account-note strong{color:#30463c;font-size:12px}.b2b-demo-account-note p{margin:5px 0 0;color:var(--muted);font-size:10px;line-height:1.6}.b2b-account-error{margin-bottom:16px}.b2b-account-error .button{margin-left:8px}.b2b-account-skeleton{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.b2b-account-skeleton .data-panel{min-height:250px;display:grid;align-content:start;gap:18px;padding:24px}.b2b-account-skeleton i{height:12px;border-radius:8px;background:linear-gradient(100deg,#dfe5df 18%,#f7f9f6 40%,#dfe5df 62%);background-size:220% 100%;animation:account-shimmer 1.15s ease-in-out infinite}.b2b-account-skeleton i:first-child{width:48%;height:18px}.b2b-account-skeleton i:nth-child(2){width:84%;margin-top:15px}.b2b-account-skeleton i:nth-child(3){width:72%}.b2b-account-skeleton i:nth-child(4){width:60%}@keyframes account-shimmer{to{background-position-x:-220%}}@media(max-width:760px){.b2b-account-details,.b2b-account-skeleton{grid-template-columns:1fr}}@media(prefers-reduced-motion:reduce){.b2b-account-skeleton i{animation:none}}
</style>
