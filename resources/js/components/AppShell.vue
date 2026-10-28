<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Building2, ChevronDown, ClipboardList, MapPinned, Menu, Search, X, PackageSearch, ShoppingCart, Users, UserRound, LogOut, Repeat2, ListPlus } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
import { useI18n } from '../i18n'
import { useTasksStore } from '../stores/tasks'
import { useB2BCartStore } from '../stores/b2bCart'
import { useSiteSettingsStore } from '../stores/siteSettings'

const auth = useAuthStore(); const tasks = useTasksStore(); const b2bCart = useB2BCartStore(); const siteSettings = useSiteSettingsStore(); const route = useRoute(); const router = useRouter(); const { t } = useI18n()
const mobileOpen = ref(false); const profileOpen = ref(false); const accountSwitching = ref(false); const accountError = ref('')
const impersonationError = ref('')
const nav = [
  ['/products', PackageSearch, 'products'], ['/user/quickOrder', ListPlus, 'quickOrder'], ['/cart', ShoppingCart, 'cart'], ['/orders', ClipboardList, 'orders'],
  ['/user/my_account', Building2, 'myAccount'], ['/addresses', MapPinned, 'addresses'], ['/users/bp', Users, 'bpUsers'],
] as const
const navPermissions: Record<string, string> = {
  '/products': 'view_product_catalog', '/user/quickOrder': 'create_quick_orders', '/cart': 'manage_basket',
  '/orders': 'view_sales_orders', '/user/my_account': 'view_account_details', '/addresses': 'view_address_book', '/users/bp': 'view_team',
}
function hasPermission(permission: string) {
  const membership = auth.bootstrap?.membership
  return Boolean(membership && (['owner', 'admin'].includes(membership.role) || membership.permissions.includes(permission) || (permission === 'view_team' && membership.permissions.includes('manage_members'))))
}
const visibleNav = computed(() => nav.filter(([path]) => hasPermission(navPermissions[path]) && (path !== '/cart' || auth.portalDemoMode || Boolean(auth.selectedPortalAccount))))
const groups = [
  { id: 'workspace', title: 'WORKSPACE', paths: ['/products', '/user/quickOrder', '/cart'] },
  { id: 'sales-ar', title: 'SALES AR', paths: ['/orders'] },
  { id: 'manage', title: 'ACCOUNT & USERS', paths: ['/user/my_account', '/addresses', '/users/bp'] },
] as const
const expandedGroup = ref('workspace')
const visibleGroups = computed(() => groups.map(group => ({ ...group, items: visibleNav.value.filter(([path]) => (group.paths as readonly string[]).includes(path)) })).filter(group => group.items.length))
watch(() => [route.path, visibleGroups.value.map(group => group.id).join('|')], ([path]) => {
  const group = visibleGroups.value.find(item => item.items.some(([itemPath]) => path.startsWith(itemPath)))
  if (group) expandedGroup.value = group.id
}, { immediate: true })
function toggleGroup(id: string) { expandedGroup.value = expandedGroup.value === id ? '' : id }
const companyId = computed(() => auth.bootstrap?.company.id)
function isActive(path: string) { return route.path.startsWith(path) }
async function logout() { await auth.logout(); router.replace('/login') }
async function stopImpersonating() {
  impersonationError.value = ''
  try {
    await auth.stopImpersonating()
    window.location.assign('/products')
  } catch (exception: any) {
    impersonationError.value = exception.response?.data?.message || 'Unable to return to your account.'
  }
}
async function switchAccount(code: string) {
  if (code === auth.selectedPortalAccount) { accountSwitching.value = false; return }
  accountError.value = ''
  try {
    await auth.selectPortalAccount(code)
    accountSwitching.value = false
    profileOpen.value = false
    window.location.assign('/products')
  } catch (exception: any) { accountError.value = exception.response?.data?.message || 'Unable to switch customer account.' }
}
let refreshTimer: ReturnType<typeof setInterval> | undefined
function refreshWhenVisible() { if (document.visibilityState === 'visible') void tasks.refresh(true) }
watch(companyId, (id) => { if (id) void tasks.refresh(true); else tasks.reset() }, { immediate: true })
onMounted(() => {
  refreshTimer = setInterval(() => void tasks.refresh(true), 30_000)
  window.addEventListener('focus', refreshWhenVisible)
  document.addEventListener('visibilitychange', refreshWhenVisible)
})
onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
  window.removeEventListener('focus', refreshWhenVisible)
  document.removeEventListener('visibilitychange', refreshWhenVisible)
})
</script>

<template>
  <div class="app-frame">
    <div v-if="mobileOpen" class="mobile-scrim" @click="mobileOpen = false" />
    <aside class="sidebar" :class="{ open: mobileOpen }">
      <div class="sidebar-brand"><img v-if="siteSettings.settings.logo_url" class="portal-brand-logo" :src="siteSettings.settings.logo_url" :alt="`${siteSettings.settings.site_name} logo`"><div v-else class="brand-mark">{{ siteSettings.settings.site_name.slice(0, 1).toUpperCase() }}</div><div><strong>{{ siteSettings.settings.site_name }}</strong><small>{{ siteSettings.settings.tagline }}</small></div><button class="icon-button mobile-only" @click="mobileOpen=false"><X :size="19" /></button></div>
      <div class="workspace-card">
        <span class="workspace-avatar"><img v-if="auth.bootstrap?.company.logo_url" :src="auth.bootstrap.company.logo_url" :alt="`${auth.bootstrap.company.name} logo`"><span v-else>{{ auth.bootstrap?.company.name.charAt(0) }}</span></span>
        <span><small>WORKSPACE</small><strong>{{ auth.bootstrap?.company.name }}</strong></span>
        <ChevronDown :size="15" />
      </div>
      <nav class="main-nav">
        <section v-for="group in visibleGroups" :key="group.id" class="nav-group">
          <button class="nav-group-toggle" type="button" :aria-expanded="expandedGroup === group.id" :aria-controls="`nav-group-${group.id}`" @click="toggleGroup(group.id)">
            <span>{{ group.title }}</span><ChevronDown :size="14" :class="{ rotated: expandedGroup === group.id }" />
          </button>
          <div v-if="expandedGroup === group.id" :id="`nav-group-${group.id}`" class="nav-group-links">
            <RouterLink v-for="([path, Icon, key]) in group.items" :key="path" :to="path" :class="{ active: isActive(path) }" @click="mobileOpen=false">
              <component :is="Icon" :size="19" /><span>{{ key === 'products' ? 'Product catalog' : key === 'cart' ? 'Basket' : key === 'orders' ? 'Sales order history' : t[key] }}</span><i v-if="key === 'cart' && b2bCart.itemCount > 0">{{ b2bCart.itemCount > 99 ? '99+' : b2bCart.itemCount }}</i>
            </RouterLink>
          </div>
        </section>
      </nav>
      <div class="sidebar-footer">B2B Portal <span>v0.1</span></div>
    </aside>

    <section class="workspace">
      <div v-if="siteSettings.settings.announcement_enabled && siteSettings.settings.header_announcement" class="site-announcement" role="status">{{ siteSettings.settings.header_announcement }}</div>
      <div v-if="auth.impersonation" class="impersonation-banner" role="status"><span>You are viewing the workspace as <strong>{{ auth.bootstrap?.user.name }}</strong><template v-if="auth.impersonation.original_user">. Signed in by {{ auth.impersonation.original_user.name }}</template>.</span><span><small v-if="impersonationError" role="alert">{{ impersonationError }}</small><button type="button" @click="stopImpersonating">Return to your account</button></span></div>
      <header class="topbar" :class="`site-header-${siteSettings.settings.header_style}`">
        <button class="icon-button mobile-only" @click="mobileOpen=true"><Menu :size="20" /></button>
        <div class="global-search"><Search :size="18" /><input :placeholder="`${t.search} products, orders…`"></div>
        <div class="top-actions">
          <button class="profile-trigger" :aria-expanded="profileOpen" aria-haspopup="menu" @click="profileOpen=!profileOpen; accountSwitching=false; accountError=''">
            <span class="user-avatar">{{ auth.bootstrap?.user.name.split(' ').map(n=>n[0]).join('').slice(0,2) }}</span>
            <span class="profile-copy"><strong>{{ auth.bootstrap?.user.name }}</strong><small>{{ auth.bootstrap?.membership.role }}</small></span><ChevronDown :size="15" />
          </button>
          <div v-if="profileOpen" class="profile-menu" role="menu">
            <button role="menuitem" @click="router.push('/account'); profileOpen=false"><UserRound :size="15"/> Profile</button>
            <button v-if="auth.portalAccounts.length > 1" role="menuitem" @click="accountSwitching=!accountSwitching; accountError=''" :aria-expanded="accountSwitching"><Repeat2 :size="15"/> Switch account</button>
            <div v-if="accountSwitching" class="profile-accounts"><button v-for="account in auth.portalAccounts" :key="account.code" role="menuitemradio" :aria-checked="account.code===auth.selectedPortalAccount" :disabled="account.code===auth.selectedPortalAccount" @click="switchAccount(account.code)"><span>{{ account.name }}</span><small>{{ account.code }}{{ account.code===auth.selectedPortalAccount ? ' · Current' : '' }}</small></button></div>
            <p v-if="accountError" class="profile-account-error" role="alert">{{ accountError }}</p>
            <button role="menuitem" @click="logout"><LogOut :size="15"/> {{ t.signOut }}</button>
          </div>
        </div>
      </header>
      <main class="page-content"><slot /></main>
    </section>
  </div>
</template>

<style scoped>
.profile-menu{min-width:210px;display:grid;gap:2px}.profile-menu>button{display:flex;align-items:center;gap:9px}.profile-accounts{display:grid;gap:3px;margin:2px 0 5px;padding:5px;border-block:1px solid var(--line)}.profile-accounts button{display:grid;gap:3px;padding:8px;border:0;border-radius:6px;background:transparent;color:var(--ink);text-align:left;cursor:pointer}.profile-accounts button:hover:not(:disabled){background:#f4f5f1}.profile-accounts button:disabled{color:#809087;cursor:default}.profile-accounts small{color:#899391;font-size:10px}.profile-account-error{margin:4px 7px;color:#a43e37;font-size:11px}.impersonation-banner{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 22px;background:#fff4dc;color:#604912;font-size:11px}.impersonation-banner>span:last-child{display:flex;align-items:center;gap:10px}.impersonation-banner button{padding:6px 10px;border:1px solid #d7bb7d;border-radius:7px;background:#fffaf0;color:#604912;font:inherit;font-weight:700;cursor:pointer}.impersonation-banner small{color:#a43e37}@media(max-width:600px){.impersonation-banner{align-items:flex-start;flex-direction:column}}
</style>
