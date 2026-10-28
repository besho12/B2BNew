<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Eye, EyeOff } from '@lucide/vue'
import TradeAuthStory from '../components/TradeAuthStory.vue'
import { useAuthStore } from '../stores/auth'
const auth = useAuthStore(); const router = useRouter(); const route = useRoute(); const email = ref(''); const password = ref('password'); const visible = ref(false); const loading = ref(false); const returning = ref(false); const error = ref('')
async function submit() { loading.value=true; error.value=''; try { await auth.login(email.value,password.value); router.replace(auth.platformAdmin ? '/platform' : (auth.portalAccountSelectionRequired ? '/select-account' : '/products')) } catch(e:any) { if(e.response?.data?.verification_required){router.replace({path:'/verify-email',query:{email:e.response.data.email}});return} error.value=e.response?.data?.message || 'Unable to sign in.' } finally { loading.value=false } }
async function returnToOriginalAccount() { returning.value=true; error.value=''; try { await auth.stopImpersonating(); window.location.assign('/products') } catch(e:any) { error.value=e.response?.data?.message || 'Unable to return to your account.'; returning.value=false } }
</script>
<template>
  <div v-if="auth.impersonation" class="impersonation-return"><strong>This user’s workspace is unavailable.</strong><span>You still have an active sign-in-as session.</span><button class="button secondary" :disabled="returning" @click="returnToOriginalAccount">{{ returning ? 'Returning…' : `Return to ${auth.impersonation.original_user?.name || 'your account'}` }}</button><p v-if="error" class="form-error">{{ error }}</p></div>
  <main class="auth-layout">
    <TradeAuthStory />
    <section class="auth-panel"><div class="auth-form-wrap"><span class="eyebrow">CUSTOMER SIGN IN</span><h2>Welcome to your trade account.</h2><p>Sign in to browse your catalogue, build an order, and manage your account.</p><div v-if="route.query.verified === '1'" class="team-success">Your email is verified. You can sign in.</div><div v-if="route.query.reset === '1'" class="team-success">Your password has been reset. Sign in with your new password.</div><form @submit.prevent="submit"><label>Email address<input v-model="email" type="email" autocomplete="email" required></label><label>Password<div class="password-field"><input v-model="password" :type="visible?'text':'password'" autocomplete="current-password" required><button type="button" @click="visible=!visible"><EyeOff v-if="visible" :size="18"/><Eye v-else :size="18"/></button></div></label><div class="forgot-link"><RouterLink to="/forgot-password">Forgot password?</RouterLink></div><div v-if="error" class="form-error">{{ error }}</div><button class="button primary wide" :disabled="loading">{{ loading?'Signing in…':'Sign in to your account' }}<ArrowRight :size="18"/></button></form><div class="auth-separator"><span>NEW TO THE TRADE PORTAL?</span></div><RouterLink to="/register" class="button secondary wide">Request a customer account</RouterLink></div>
    </section>
  </main>
</template>

<style scoped>
.impersonation-return{position:fixed;z-index:10;top:14px;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:12px;padding:12px 16px;border:1px solid #e8d7a7;border-radius:9px;background:#fff9e9;color:#685522;box-shadow:0 8px 24px #142f2a1c;font-size:12px}.impersonation-return span{color:#867549}.impersonation-return p{margin:0;color:#a43e37}@media(max-width:640px){.impersonation-return{right:12px;left:12px;transform:none;flex-wrap:wrap;justify-content:center}}
</style>
