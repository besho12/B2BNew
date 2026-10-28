<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useSiteSettingsStore } from './stores/siteSettings'
import AppShell from './components/AppShell.vue'

const auth = useAuthStore(); const siteSettings = useSiteSettingsStore(); const route = useRoute(); const router = useRouter()
const appReady = ref(false)
const portalOwnerPaths = ['/platform', '/users/global', '/platform/settings', '/platform/mail-templates']
const routePermissions: Record<string, string> = {
  '/products': 'view_product_catalog', '/user/quickOrder': 'create_quick_orders', '/cart': 'manage_basket',
  '/orders': 'view_sales_orders', '/account': 'view_account_details', '/user/my_account': 'view_account_details',
  '/addresses': 'view_address_book', '/users/bp': 'view_team',
}
function firstPermittedPortalPath(): string {
  const membership = auth.bootstrap?.membership
  const permissions = membership?.permissions || []
  if (['owner', 'admin'].includes(membership?.role || '') || permissions.includes('view_product_catalog')) return '/products'
  if (permissions.includes('create_quick_orders')) return '/user/quickOrder'
  if (permissions.includes('view_sales_orders')) return '/orders'
  if (permissions.includes('view_account_details')) return '/account'
  if (permissions.includes('view_address_book')) return '/addresses'
  if (permissions.includes('view_team')) return '/users/bp'
  return '/login'
}
async function routeByAccess() {
  if (auth.loading) return
  if (!auth.authenticated && !route.meta.guest && !route.meta.public) { await router.replace('/login'); return }
  if (!auth.authenticated) return
  if (auth.platformAdmin) {
    if (!['/platform', '/users/global', '/platform/settings', '/platform/mail-templates'].includes(route.path)) { await router.replace('/platform'); return }
    return
  }
  if (auth.portalAccountSelectionRequired) { if (route.path !== '/select-account') await router.replace('/select-account'); return }
  if (route.path === '/select-account') { await router.replace('/products'); return }
  if (route.path === '/users/global' || route.path === '/platform/settings' || route.path === '/platform/mail-templates') { await router.replace('/products'); return }
  const requiredPermission = routePermissions[route.path]
  const membership = auth.bootstrap?.membership
  const permitted = membership && (membership.permissions.includes(requiredPermission || '') || (requiredPermission === 'view_team' && membership.permissions.includes('manage_members')))
  if (requiredPermission && membership && !['owner', 'admin'].includes(membership.role) && !permitted) {
    const destination = firstPermittedPortalPath()
    if (route.path !== destination) await router.replace(destination)
    return
  }
  if (route.path === '/cart' && !auth.portalDemoMode && !auth.selectedPortalAccount) { await router.replace('/products'); return }
  if (route.path === '/' || route.path === '/dashboard' || route.path === '/plans' || route.path === '/billing' || route.path === '/platform') { await router.replace('/products'); return }
  if (route.meta.guest && !route.meta.allowAuthenticated) { await router.replace('/products'); return }
}
onMounted(async () => {
  await Promise.all([auth.load(), siteSettings.load()])
  await routeByAccess()
  appReady.value = true
})
watch(() => route.path, () => { void routeByAccess() })
</script>

<template>
  <div v-if="auth.loading || !appReady" class="boot-screen"><div class="brand-mark">E</div><span>Preparing your workspace…</span></div>
  <router-view v-else-if="route.meta.guest || route.meta.public" />
  <router-view v-else-if="portalOwnerPaths.includes(route.path) && auth.platformAdmin" />
  <div v-else-if="auth.platformAdmin" class="boot-screen"><div class="brand-mark">E</div><span>Opening Portal Owner console…</span></div>
  <AppShell v-else-if="auth.authenticated && auth.bootstrap"><router-view /></AppShell>
  <div v-else class="boot-screen"><div class="brand-mark">E</div><span>{{ auth.authenticated ? 'Loading your workspace…' : 'Redirecting to sign in…' }}</span></div>
</template>
