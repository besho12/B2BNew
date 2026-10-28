<script setup lang="ts">
import { useRouter } from 'vue-router'
import { FileText, Globe2, LayoutDashboard, LogOut, Settings2, Users } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
import { useSiteSettingsStore } from '../stores/siteSettings'

const auth = useAuthStore()
const site = useSiteSettingsStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  await router.replace('/login')
}
</script>

<template>
  <div class="portal-owner-layout">
    <aside class="portal-owner-rail">
      <RouterLink to="/platform" class="portal-owner-brand">
        <img v-if="site.settings.logo_url" :src="site.settings.logo_url" :alt="`${site.settings.site_name} logo`">
        <span v-else class="portal-owner-monogram"><Globe2 :size="18" /></span>
        <span><strong>{{ site.settings.site_name }}</strong><small>PORTAL OWNER</small></span>
      </RouterLink>
      <span class="portal-owner-nav-label">OVERVIEW</span>
      <nav class="portal-owner-nav" aria-label="Portal overview navigation">
        <RouterLink to="/platform" active-class="active"><LayoutDashboard :size="17" />Dashboard</RouterLink>
      </nav>
      <span class="portal-owner-nav-label">SITE CONTROL</span>
      <nav class="portal-owner-nav" aria-label="Portal owner navigation">
        <RouterLink to="/platform/settings" active-class="active"><Settings2 :size="17" />Master settings</RouterLink>
        <RouterLink to="/platform/mail-templates" active-class="active"><FileText :size="17" />Mail templates</RouterLink>
        <RouterLink to="/users/global" active-class="active"><Users :size="17" />Global users</RouterLink>
      </nav>
      <div class="portal-owner-account">
        <span class="portal-owner-avatar">{{ (auth.platformUser?.name || 'P').slice(0, 1).toUpperCase() }}</span>
        <span><strong>{{ auth.platformUser?.name || 'Portal owner' }}</strong><small>Super administrator</small></span>
        <button type="button" aria-label="Sign out" title="Sign out" @click="logout"><LogOut :size="16" /></button>
      </div>
    </aside>
    <section class="portal-owner-main">
      <header class="portal-owner-topbar"><span>Portal administration</span><span class="portal-owner-secure"><i></i>Owner access</span></header>
      <main class="portal-owner-content"><slot /></main>
    </section>
  </div>
</template>

<style>
.portal-owner-layout{min-height:100vh;display:grid;grid-template-columns:238px minmax(0,1fr);background:#f4f6f3;color:#203630;font-family:"DM Sans","Tajawal",sans-serif}.portal-owner-rail{position:sticky;top:0;height:100vh;display:flex;flex-direction:column;padding:25px 16px 16px;background:#142c2b;color:#fff}.portal-owner-brand{display:flex;align-items:center;gap:10px;padding:0 7px 24px;color:inherit;text-decoration:none}.portal-owner-brand>img,.portal-owner-monogram{width:36px;height:36px;object-fit:contain;border-radius:10px;background:#fff}.portal-owner-monogram{display:grid;place-items:center;background:#dfc68e;color:#183632}.portal-owner-brand>span:last-child{display:grid;gap:4px;min-width:0}.portal-owner-brand strong{max-width:160px;overflow:hidden;font-size:12px;text-overflow:ellipsis;white-space:nowrap}.portal-owner-brand small,.portal-owner-nav-label{color:#8da39c;font-size:8px;font-weight:700;letter-spacing:.14em}.portal-owner-nav-label{padding:20px 10px 8px}.portal-owner-nav{display:grid;gap:4px}.portal-owner-nav a{display:flex;align-items:center;gap:10px;padding:10px;border-radius:8px;color:#b4c2bd;font-size:11px;font-weight:600;text-decoration:none}.portal-owner-nav a:hover,.portal-owner-nav a.active{background:rgba(255,255,255,.09);color:#f3dca9}.portal-owner-nav a.active{box-shadow:inset 2px 0 #d4b673}.portal-owner-account{display:flex;align-items:center;gap:9px;margin-top:auto;padding:13px 5px 0;border-top:1px solid rgba(255,255,255,.1)}.portal-owner-avatar{width:31px;height:31px;display:grid;place-items:center;border-radius:50%;background:#d8e4dc;color:#234c3e;font-size:11px;font-weight:700}.portal-owner-account>span:nth-child(2){display:grid;gap:3px;min-width:0;flex:1}.portal-owner-account strong{overflow:hidden;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.portal-owner-account small{color:#8da39c;font-size:8px}.portal-owner-account button{width:30px;height:30px;display:grid;place-items:center;border:0;border-radius:7px;background:transparent;color:#a9b9b2;cursor:pointer}.portal-owner-account button:hover{background:rgba(255,255,255,.1);color:#fff}.portal-owner-main{min-width:0}.portal-owner-topbar{height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 clamp(20px,4vw,48px);border-bottom:1px solid #e4e9e3;background:rgba(255,255,255,.86);color:#687871;font-size:10px}.portal-owner-secure{display:flex;align-items:center;gap:7px;color:#6c8076}.portal-owner-secure i{width:7px;height:7px;border-radius:50%;background:#63a37d;box-shadow:0 0 0 3px #e8f2e9}.portal-owner-content{padding:30px clamp(18px,4vw,48px) 60px}@media(max-width:760px){.portal-owner-layout{grid-template-columns:1fr}.portal-owner-rail{position:relative;height:auto;padding:12px 14px;display:grid;grid-template-columns:1fr auto;align-items:center}.portal-owner-brand{padding:0}.portal-owner-nav-label{display:none}.portal-owner-nav{display:flex;grid-column:1/-1;grid-row:2;overflow:auto;padding-top:10px}.portal-owner-nav a{white-space:nowrap}.portal-owner-account{margin:0;padding:0;border:0}.portal-owner-account>span:nth-child(2),.portal-owner-account button{display:none}.portal-owner-topbar{height:48px}.portal-owner-content{padding:22px 14px 40px}}
</style>
