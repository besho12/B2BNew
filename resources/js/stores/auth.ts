import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, prepareCsrf } from '../api'
import type { Bootstrap } from '../types'

export const useAuthStore = defineStore('auth', () => {
  const bootstrap = ref<Bootstrap | null>(null)
  const platformAdmin = ref(false)
  const platformUser = ref<{ id: number; name: string; email: string } | null>(null)
  const loading = ref(true)
  const portalAccounts = ref<{ code: string; name: string }[]>([])
  const selectedPortalAccount = ref<string | null>(null)
  const portalAccountSelectionRequired = ref(false)
  const portalAccountsError = ref('')
  const portalDemoMode = ref(false)
  const impersonation = ref<{ original_user: { id: number; name: string; email: string } | null } | null>(null)
  const locale = ref<'en' | 'ar'>((localStorage.getItem('exousia_locale') as 'en' | 'ar') || 'en')
  const authenticated = computed(() => Boolean(bootstrap.value?.user || platformUser.value))

  function applyDirection() {
    document.documentElement.lang = locale.value
    document.documentElement.dir = locale.value === 'ar' ? 'rtl' : 'ltr'
  }
  function toggleLocale() {
    locale.value = locale.value === 'en' ? 'ar' : 'en'
    localStorage.setItem('exousia_locale', locale.value)
    applyDirection()
  }
  async function load() {
    loading.value = true
    bootstrap.value = null
    platformAdmin.value = false
    platformUser.value = null
    portalAccounts.value = []
    selectedPortalAccount.value = null
    portalAccountSelectionRequired.value = false
    portalAccountsError.value = ''
    portalDemoMode.value = false
    impersonation.value = null
    try {
      const { data: context } = await api.get<{user:{id:number;name:string;email:string};is_platform_admin:boolean;impersonation:{original_user:{id:number;name:string;email:string}|null}|null}>('/auth/context')
      impersonation.value = context.impersonation || null
      if (context.is_platform_admin) {
        platformAdmin.value = true
        platformUser.value = context.user
        portalAccountSelectionRequired.value = false
        return
      }

      const { data } = await api.get<Bootstrap>('/bootstrap')
      bootstrap.value = data
      localStorage.setItem('exousia_company_id', String(data.company.id))
      await loadPortalAccounts()
    } catch {
      bootstrap.value = null
      platformAdmin.value = false
      platformUser.value = null
    }
    finally { loading.value = false; applyDirection() }
  }
  async function loadPortalAccounts() {
    portalAccountsError.value = ''
    try {
      const { data } = await api.get('/portal/accounts')
      portalDemoMode.value = Boolean(data.demo_mode)
      portalAccounts.value = data.accounts || []
      selectedPortalAccount.value = data.selected_account || null
      portalAccountSelectionRequired.value = Boolean(data.requires_selection)
    } catch (error: any) { portalDemoMode.value = false; portalAccountsError.value = error.response?.data?.message || 'Unable to load customer accounts.' }
  }
  async function selectPortalAccount(code: string) {
    await api.post('/portal/account', { account_code: code })
    selectedPortalAccount.value = code
    portalAccountSelectionRequired.value = false
  }
  async function login(email: string, password: string) {
    await prepareCsrf(); await api.post('/auth/login', { email, password }); await load()
  }
  async function register(payload: Record<string, string>) {
    await prepareCsrf(); return api.post('/auth/register', payload)
  }
  async function logout() {
    await api.post('/auth/logout'); bootstrap.value = null; platformAdmin.value = false; platformUser.value = null; localStorage.removeItem('exousia_company_id'); portalAccounts.value = []; selectedPortalAccount.value = null; portalAccountSelectionRequired.value = false; portalAccountsError.value = ''; portalDemoMode.value = false
  }
  async function stopImpersonating() {
    await api.post('/auth/impersonation/stop')
    impersonation.value = null
  }
  async function switchCompany(id: number) {
    await api.post(`/companies/${id}/switch`); localStorage.setItem('exousia_company_id', String(id)); await load()
  }
  return { bootstrap, platformAdmin, platformUser, loading, locale, authenticated, portalAccounts, selectedPortalAccount, portalAccountSelectionRequired, portalAccountsError, portalDemoMode, impersonation, loadPortalAccounts, selectPortalAccount, load, login, register, logout, stopImpersonating, switchCompany, toggleLocale }
})
