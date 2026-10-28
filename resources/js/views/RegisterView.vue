<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, RefreshCw, UserRound } from '@lucide/vue'
import TradeAuthStory from '../components/TradeAuthStory.vue'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loading = ref(false)
const error = ref('')
const captchaUrl = ref('')
const captchaLoading = ref(false)
const captchaError = ref('')
const form = reactive({ name: '', email: '', password: '', password_confirmation: '', captcha: '' })

async function loadCaptcha() {
  captchaLoading.value = true
  captchaError.value = ''
  if (captchaUrl.value) URL.revokeObjectURL(captchaUrl.value)
  captchaUrl.value = ''

  try {
    const { data } = await api.get('/auth/register/captcha', { responseType: 'blob' })
    captchaUrl.value = URL.createObjectURL(data)
  } catch {
    captchaError.value = 'Unable to load the security check. Select refresh to try again.'
  } finally {
    captchaLoading.value = false
  }
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await auth.register(form)
    router.replace({ path: '/verify-email', query: { sent: '1', email: data.email } })
  } catch (exception: any) {
    const errors = exception.response?.data?.errors
    error.value = errors ? Object.values(errors).flat().join(' ') : (exception.response?.data?.message || 'Unable to create your account.')
    form.captcha = ''
    await loadCaptcha()
  } finally {
    loading.value = false
  }
}

onMounted(loadCaptcha)
onBeforeUnmount(() => {
  if (captchaUrl.value) URL.revokeObjectURL(captchaUrl.value)
})
</script>

<template>
  <main class="auth-layout">
    <TradeAuthStory />
    <section class="auth-panel">
      <div class="auth-form-wrap auth-register-form">
        <div class="auth-register-icon"><UserRound :size="19" /></div>
        <span class="eyebrow">CUSTOMER REGISTRATION</span>
        <h2>Create your portal account.</h2>
        <p>Register your sign-in details. Company access and permissions are linked separately.</p>

        <form @submit.prevent="submit">
          <label>Your name<input v-model="form.name" required autocomplete="name" placeholder="Your full name"></label>
          <label>Work email<input v-model="form.email" type="email" required autocomplete="email" placeholder="you@company.com"></label>
          <label>Password<input v-model="form.password" type="password" required minlength="8" autocomplete="new-password"></label>
          <label>Confirm password<input v-model="form.password_confirmation" type="password" required autocomplete="new-password"></label>

          <div class="captcha-control">
            <div class="captcha-image-frame">
              <img v-if="captchaUrl" :src="captchaUrl" alt="Registration CAPTCHA">
              <span v-else>{{ captchaLoading ? 'Loading security check…' : 'Security check unavailable' }}</span>
            </div>
            <button type="button" class="captcha-refresh" :disabled="captchaLoading || loading" aria-label="Refresh CAPTCHA" @click="loadCaptcha">
              <RefreshCw :size="16" :class="{ spinning: captchaLoading }" />
            </button>
          </div>
          <label>Enter CAPTCHA<input v-model="form.captcha" required maxlength="10" autocapitalize="characters" autocomplete="off" placeholder="Type the characters shown above"></label>

          <div v-if="captchaError" class="form-error">{{ captchaError }}</div>
          <div v-if="error" class="form-error">{{ error }}</div>
          <button class="button primary wide" :disabled="loading || captchaLoading || !captchaUrl">
            {{ loading ? 'Creating account…' : 'Create account' }}<ArrowRight :size="18" />
          </button>
        </form>

        <div class="auth-separator"><span>ALREADY REGISTERED?</span></div>
        <RouterLink to="/login" class="button secondary wide">Sign in to your account</RouterLink>
      </div>
    </section>
  </main>
</template>

<style scoped>
.auth-register-icon{width:38px;height:38px;display:grid;place-items:center;margin-bottom:14px;border:1px solid #f0d9dc;border-radius:10px;background:#fff4f4;color:#c63c57}
.auth-register-form>p{line-height:1.6}
.captcha-control{display:flex;align-items:center;gap:8px}
.captcha-image-frame{width:197px;height:52px;display:grid;place-items:center;overflow:hidden;border:1px solid #e2e7e2;border-radius:7px;background:#fafbf9}
.captcha-image-frame img{width:100%;height:100%;object-fit:cover}
.captcha-image-frame span{padding:8px;color:#79867f;font-size:10px}
.captcha-refresh{width:42px;height:42px;display:grid;place-items:center;border:1px solid #dce4dc;border-radius:7px;background:#f6f8f5;color:#526f5d;cursor:pointer}
.captcha-refresh:disabled{opacity:.55;cursor:wait}
.captcha-refresh .spinning{animation:captcha-spin .8s linear infinite}
@keyframes captcha-spin{to{transform:rotate(360deg)}}
</style>
