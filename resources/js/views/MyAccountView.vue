<script setup lang="ts">
import { computed, ref } from 'vue'
import { ArrowRight, Building2, ClipboardList, FileText, MapPinned } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const accountDraft = ref(auth.selectedPortalAccount || '')
const account = computed(() => auth.portalAccounts.find(item => item.code === auth.selectedPortalAccount) || null)
const accountError = ref('')

async function changeAccount() {
  if (!accountDraft.value || accountDraft.value === auth.selectedPortalAccount) return
  accountError.value = ''
  try {
    await auth.selectPortalAccount(accountDraft.value)
  } catch (exception: any) {
    accountError.value = exception.response?.data?.message || 'Unable to switch customer account.'
  }
}
</script>

<template>
  <section class="page-heading my-account-heading">
    <div>
      <span class="eyebrow">CUSTOMER PORTAL</span>
      <h1>My account</h1>
      <p>Manage your customer information, addresses, and order history.</p>
    </div>
    <AppSelect v-if="auth.portalAccounts.length > 1" v-model="accountDraft" class="b2b-account-switch" aria-label="Customer account" @change="changeAccount">
      <option v-for="item in auth.portalAccounts" :key="item.code" :value="item.code">{{ item.code }} · {{ item.name }}</option>
    </AppSelect>
    <span v-else class="my-account-current"><span class="my-account-avatar">{{ (account?.name || auth.bootstrap?.company.name || 'A').slice(0, 1).toUpperCase() }}</span><span><small>SELECTED CUSTOMER</small><strong>{{ account?.name || (auth.portalDemoMode ? 'Demo account' : auth.bootstrap?.company.name) }}</strong><small>{{ account?.code || (auth.portalDemoMode ? 'Sample account' : 'Workspace account') }}</small></span></span>
  </section>

  <p v-if="accountError" class="form-error my-account-error" role="alert">{{ accountError }}</p>

  <div class="my-account-groups">
    <section class="my-account-group" aria-labelledby="my-account-details-title">
      <header><div><span class="eyebrow">YOUR CUSTOMER PROFILE</span><h2 id="my-account-details-title">Account details</h2><p>Review company and contact information registered to your customer account.</p></div></header>
      <div class="my-account-cards">
        <RouterLink to="/account" class="my-account-card">
          <span class="my-account-card-icon"><Building2 :size="20" /></span>
          <span class="my-account-card-copy"><strong>Customer account details</strong><small>View your company code, primary contact, email, and phone numbers.</small></span>
          <ArrowRight class="my-account-arrow" :size="17" />
        </RouterLink>
        <RouterLink to="/addresses" class="my-account-card">
          <span class="my-account-card-icon"><MapPinned :size="20" /></span>
          <span class="my-account-card-copy"><strong>Address book</strong><small>View the invoice and shipping addresses registered to this account.</small></span>
          <ArrowRight class="my-account-arrow" :size="17" />
        </RouterLink>
      </div>
    </section>

    <section class="my-account-group" aria-labelledby="my-account-history-title">
      <header><div><span class="eyebrow">PURCHASING</span><h2 id="my-account-history-title">My history</h2><p>Review activity associated with your selected customer account.</p></div></header>
      <div class="my-account-cards">
        <RouterLink to="/orders" class="my-account-card">
          <span class="my-account-card-icon"><ClipboardList :size="20" /></span>
          <span class="my-account-card-copy"><strong>Order history</strong><small>Review previous orders, delivery dates, and order status.</small></span>
          <ArrowRight class="my-account-arrow" :size="17" />
        </RouterLink>
        <article class="my-account-card my-account-card-unavailable" aria-label="Invoices and credit notes unavailable">
          <span class="my-account-card-icon"><FileText :size="20" /></span>
          <span class="my-account-card-copy"><strong>Invoices &amp; credit notes</strong><small>Invoice history is not connected to this portal yet.</small></span>
          <span class="my-account-unavailable">Unavailable</span>
        </article>
      </div>
    </section>
  </div>
</template>

<style scoped>
.my-account-heading{align-items:center}.my-account-current{display:flex;align-items:center;gap:11px;max-width:320px;padding:9px 13px;border:1px solid var(--line);border-radius:10px;background:#fff}.my-account-avatar{width:38px;height:38px;display:grid;place-items:center;flex:none;border-radius:11px;background:#e9f0eb;color:#4d7663;font-size:13px;font-weight:700}.my-account-current>span:last-child{display:grid;gap:3px;min-width:0}.my-account-current small{color:#88948d;font-size:8px;font-weight:700;letter-spacing:.1em}.my-account-current strong{overflow:hidden;color:#30463b;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.my-account-groups{display:grid;gap:21px}.my-account-group{padding:20px;border:1px solid var(--line);border-radius:12px;background:#fff}.my-account-group>header{margin-bottom:15px}.my-account-group h2{margin:4px 0;color:#283e34;font-size:16px}.my-account-group header p{margin:4px 0 0;color:var(--muted);font-size:10px;line-height:1.6}.my-account-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.my-account-card{min-height:92px;display:flex;align-items:center;gap:13px;padding:15px;border:1px solid #e9ede9;border-radius:10px;background:#fff;color:inherit;text-align:left;text-decoration:none;transition:border-color .16s ease,background .16s ease,transform .16s ease}.my-account-card[href]:hover{transform:translateY(-1px);border-color:#b8cbbd;background:#fbfcfa}.my-account-card-icon{width:40px;height:40px;display:grid;place-items:center;flex:none;border-radius:11px;background:#edf3ee;color:#527764}.my-account-card-copy{display:grid;gap:5px;min-width:0}.my-account-card-copy strong{color:#31483c;font-size:11px}.my-account-card-copy small{color:#78857d;font-size:9px;line-height:1.5}.my-account-arrow{margin-left:auto;flex:none;color:#85948b}.my-account-card-unavailable{background:#fafbf9}.my-account-card-unavailable .my-account-card-icon{background:#f0f1ef;color:#8b948e}.my-account-card-unavailable .my-account-card-copy strong{color:#67736b}.my-account-unavailable{margin-left:auto;padding:5px 7px;border-radius:6px;background:#f0f1ef;color:#78837c;font-size:8px;white-space:nowrap}.my-account-error{margin-bottom:14px}@media(max-width:700px){.my-account-heading{align-items:flex-start;flex-direction:column}.my-account-cards{grid-template-columns:1fr}.my-account-group{padding:15px}}@media(prefers-reduced-motion:reduce){.my-account-card{transition:none}.my-account-card[href]:hover{transform:none}}
</style>
