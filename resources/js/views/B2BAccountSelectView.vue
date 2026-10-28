<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Building2, Check, RefreshCw } from '@lucide/vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore(); const router = useRouter(); const busy = ref(false); const error = ref('')
onMounted(async () => { if (!auth.portalAccounts.length && !auth.portalAccountsError) await auth.loadPortalAccounts() })
async function choose(code: string) {
  busy.value = true; error.value = ''
  try { await auth.selectPortalAccount(code); router.replace('/products') }
  catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to select this customer account.' }
  finally { busy.value = false }
}
</script>
<template>
  <main class="register-page b2b-account-page"><div class="register-brand"><div class="brand-mark">B</div><strong>B2B <span>PORTAL</span></strong></div>
    <section class="register-card b2b-account-card"><div class="register-icon"><Building2 /></div><span class="eyebrow">CUSTOMER ACCOUNT</span><h1>Select an account</h1><p>Choose which customer account to use for product availability and pricing.</p>
      <div v-if="auth.portalAccountsError" class="form-error">{{ auth.portalAccountsError }} <button class="button secondary" @click="auth.loadPortalAccounts()"><RefreshCw :size="14"/> Try again</button></div>
      <div v-else-if="!auth.portalAccounts.length" class="table-empty"><Building2/><strong>No customer accounts found</strong><span>Contact your account administrator if you expected to see an account.</span></div>
      <div v-else class="b2b-account-list"><button v-for="account in auth.portalAccounts" :key="account.code" class="b2b-account-option" :disabled="busy" @click="choose(account.code)"><span><strong>{{ account.name }}</strong><small>Account {{ account.code }}</small></span><Check :size="18" /></button></div>
      <div v-if="error" class="form-error">{{ error }}</div><p class="auth-alt"><button class="link-button" @click="auth.logout().then(()=>router.replace('/login'))">Sign out</button></p>
    </section>
  </main>
</template>
<style scoped>
.b2b-account-page{min-height:100vh}.b2b-account-card{width:min(540px,100%)}.b2b-account-list{display:grid;gap:9px;margin-top:22px}.b2b-account-option{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px;border:1px solid #dce2dd;border-radius:9px;background:#fff;color:#263d36;text-align:left;cursor:pointer}.b2b-account-option:hover{border-color:#879f99;background:#f8faf8}.b2b-account-option span{display:grid;gap:5px}.b2b-account-option small{color:#7b8984;font-size:10px}.b2b-account-option svg{color:#5c8274}.link-button{border:0;background:none;color:#526f63;cursor:pointer}
</style>
