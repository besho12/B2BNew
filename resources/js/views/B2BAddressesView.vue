<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Building2, MapPinned, RefreshCw } from '@lucide/vue'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'

type Address = { address_id: string; company_name: string; address_line_1: string; address_line_2: string; city: string; postcode: string; county: string; country: string }
type AddressBook = { bill_addresses: Address[]; ship_addresses: Address[] }
const auth = useAuthStore()
const addressBook = ref<AddressBook>({ bill_addresses: [], ship_addresses: [] })
const accountDraft = ref(auth.selectedPortalAccount || '')
const loading = ref(false)
const error = ref('')
const account = computed(() => auth.portalAccounts.find(item => item.code === auth.selectedPortalAccount) || null)
const addressGroups = computed(() => [
  { id: 'billing', title: 'Invoice addresses', rows: addressBook.value.bill_addresses },
  { id: 'shipping', title: 'Shipping addresses', rows: addressBook.value.ship_addresses },
])

async function load() {
  if (auth.portalDemoMode) return
  loading.value = true; error.value = ''
  try { const { data } = await api.get<AddressBook>('/portal/addresses'); addressBook.value = { bill_addresses: data.bill_addresses || [], ship_addresses: data.ship_addresses || [] } }
  catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to load customer addresses.' }
  finally { loading.value = false }
}
async function changeAccount() {
  if (!accountDraft.value || accountDraft.value === auth.selectedPortalAccount) return
  try { await auth.selectPortalAccount(accountDraft.value); await load() }
  catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to switch customer account.' }
}
onMounted(() => { if (!auth.portalDemoMode) void load() })
</script>

<template>
  <section class="page-heading"><div><span class="eyebrow">B2B ACCOUNT</span><h1>Address book</h1><p>Invoice and shipping addresses registered to your customer account.</p></div><AppSelect v-if="auth.portalAccounts.length > 1" v-model="accountDraft" class="b2b-account-switch" aria-label="Customer account" @change="changeAccount"><option v-for="item in auth.portalAccounts" :key="item.code" :value="item.code">{{ item.code }} · {{ item.name }}</option></AppSelect><span v-else class="b2b-current-account"><Building2 :size="16"/> {{ account?.code || (auth.portalDemoMode ? 'DEMO ACCOUNT' : 'Customer account') }}</span></section>

  <div v-if="error" class="form-error b2b-address-error" role="alert">{{ error }} <button class="button secondary" @click="load">Retry</button></div>
  <section v-if="loading" class="b2b-address-skeleton" aria-label="Loading addresses" aria-busy="true"><article v-for="item in 4" :key="item" class="data-panel"><i></i><i></i><i></i><i></i></article></section>
  <div v-else-if="auth.portalDemoMode" class="b2b-address-empty"><span><MapPinned :size="21"/></span><strong>Demo account has no saved addresses.</strong><p>Address details will appear here after connecting the live B2B account.</p></div>
  <div v-else class="b2b-address-groups">
    <section v-for="group in addressGroups" :key="group.id" class="b2b-address-section">
      <header><div><span class="eyebrow">{{ group.id === 'billing' ? 'INVOICING' : 'DELIVERY' }}</span><h2>{{ group.title }}</h2></div><span class="b2b-address-count">{{ group.rows.length }}</span></header>
      <div v-if="group.rows.length" class="b2b-address-grid"><article v-for="address in group.rows" :key="`${group.id}-${address.address_id}`" class="data-panel b2b-address-card"><div class="b2b-address-card-top"><span><MapPinned :size="17"/></span><small>ADDRESS {{ address.address_id || '—' }}</small></div><strong>{{ address.company_name || account?.name || 'Customer address' }}</strong><address><span v-if="address.address_line_1">{{ address.address_line_1 }}</span><span v-if="address.address_line_2">{{ address.address_line_2 }}</span><span>{{ [address.city, address.county, address.postcode].filter(Boolean).join(', ') || '—' }}</span><span v-if="address.country">{{ address.country }}</span></address></article></div>
      <div v-else class="b2b-address-no-results">No {{ group.id === 'billing' ? 'invoice' : 'shipping' }} addresses are registered for this account.</div>
    </section>
    <p v-if="addressBook.bill_addresses.length || addressBook.ship_addresses.length" class="b2b-address-note">To request an address change, contact your B2B account representative and include the customer code.</p>
  </div>
</template>

<style scoped>
.b2b-address-groups{display:grid;gap:22px}.b2b-address-section>header{display:flex;align-items:end;justify-content:space-between;margin-bottom:12px}.b2b-address-section h2{margin:4px 0 0;color:#233a31;font-size:16px}.b2b-address-count{min-width:28px;height:28px;display:grid;place-items:center;border-radius:9px;background:#eaf0eb;color:#557a69;font-size:10px;font-weight:700}.b2b-address-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:13px}.b2b-address-card{min-height:190px;padding:17px;border:1px solid var(--line);border-radius:11px;background:#fff}.b2b-address-card-top{display:flex;align-items:center;gap:8px;margin-bottom:14px}.b2b-address-card-top>span,.b2b-address-empty>span{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#eaf0eb;color:#557a69}.b2b-address-card-top small{color:#8a958f;font-size:8px;font-weight:700;letter-spacing:.1em}.b2b-address-card>strong{display:block;color:#2c4339;font-size:12px}.b2b-address-card address{display:grid;gap:4px;margin-top:7px;color:#6d7973;font-size:10px;font-style:normal;line-height:1.55}.b2b-address-no-results{padding:18px;border:1px dashed #dce3dd;border-radius:10px;background:#fbfcfa;color:#7b8780;font-size:10px}.b2b-address-note{margin:2px 0 0;padding:14px 16px;border-left:3px solid #c49342;border-radius:3px 9px 9px 3px;background:#fff8eb;color:#776345;font-size:10px;line-height:1.6}.b2b-address-empty{min-height:300px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;border:1px solid var(--line);border-radius:12px;background:#fff;text-align:center}.b2b-address-empty>span{width:48px;height:48px;border-radius:14px}.b2b-address-empty strong{margin-top:13px;color:#344940;font-size:12px}.b2b-address-empty p{margin:6px 0 0;color:var(--muted);font-size:10px}.b2b-address-error{margin-bottom:16px}.b2b-address-error .button{margin-left:8px}.b2b-address-skeleton{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:13px}.b2b-address-skeleton article{min-height:190px;display:grid;align-content:start;gap:16px;padding:18px}.b2b-address-skeleton i{height:11px;border-radius:7px;background:linear-gradient(100deg,#dfe5df 18%,#f7f9f6 40%,#dfe5df 62%);background-size:220% 100%;animation:address-shimmer 1.15s ease-in-out infinite}.b2b-address-skeleton i:first-child{height:34px;width:45%;margin-bottom:5px}.b2b-address-skeleton i:nth-child(2){width:75%;height:15px}.b2b-address-skeleton i:nth-child(3){width:65%}.b2b-address-skeleton i:nth-child(4){width:50%}@keyframes address-shimmer{to{background-position-x:-220%}}@media(prefers-reduced-motion:reduce){.b2b-address-skeleton i{animation:none}}
</style>
