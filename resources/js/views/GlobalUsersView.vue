<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Search, ShieldCheck, Users } from '@lucide/vue'
import { api } from '../api'
import PortalOwnerLayout from '../components/PortalOwnerLayout.vue'

type GlobalUser = { id: number; name: string; email: string; created_at: string }
const users = ref<GlobalUser[]>([])
const search = ref('')
const appliedSearch = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(true)
const error = ref('')
async function load() {
  loading.value = true; error.value = ''
  try {
    const { data } = await api.get('/platform/users', { params: { page: page.value, search: appliedSearch.value || undefined } })
    users.value = data.data || []; page.value = data.current_page || 1; lastPage.value = data.last_page || 1; total.value = data.total || 0
  } catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to load global users.' }
  finally { loading.value = false }
}
function submitSearch() { appliedSearch.value = search.value.trim(); page.value = 1; void load() }
function dateLabel(value: string) { return value ? new Intl.DateTimeFormat('en-EG', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value)) : '—' }
onMounted(load)
</script>

<template>
  <PortalOwnerLayout><main class="global-users-page"><header class="global-users-header"><div><span class="eyebrow">GLOBAL ACCESS</span><h1>Global users</h1><p>Platform-level accounts with access across B2B workspaces.</p></div><span class="global-count"><ShieldCheck :size="17"/> {{ total }} global users</span></header>
    <section class="data-panel global-users-panel"><form class="table-toolbar global-users-toolbar" @submit.prevent="submitSearch"><div class="search-box"><Search :size="16"/><input v-model="search" placeholder="Search name or email" aria-label="Search global users"></div><button class="button primary" :disabled="loading"><Search :size="15"/> Search</button></form>
      <div v-if="error" class="form-error global-error" role="alert">{{ error }} <button class="button secondary" @click="load">Retry</button></div>
      <div v-if="loading" class="global-users-skeleton" aria-label="Loading global users" aria-busy="true"><i v-for="row in 5" :key="row"></i></div>
      <div v-else-if="!users.length" class="global-users-empty"><Users :size="23"/><strong>No global users found</strong></div>
      <div v-else class="global-users-table-wrap"><table><thead><tr><th>Global user</th><th>Access</th><th>Created</th></tr></thead><tbody><tr v-for="user in users" :key="user.id"><td><span class="global-user-person"><span>{{ user.name.split(' ').map(part => part[0]).join('').slice(0,2) }}</span><span><strong>{{ user.name }}</strong><small>{{ user.email }}</small></span></span></td><td><span class="global-access"><ShieldCheck :size="13"/> Platform admin</span></td><td>{{ dateLabel(user.created_at) }}</td></tr></tbody></table></div>
      <footer v-if="lastPage > 1" class="global-pagination"><span>Page {{ page }} of {{ lastPage }}</span><div><button class="button secondary" :disabled="loading || page <= 1" @click="page--; load()">Previous</button><button class="button secondary" :disabled="loading || page >= lastPage" @click="page++; load()">Next</button></div></footer>
      <p class="global-provisioning-note">Global access is privileged. New platform administrators are provisioned through secure operations access.</p>
    </section>
  </main></PortalOwnerLayout>
</template>

<style scoped>
.global-users-page{min-height:100vh;padding:34px clamp(18px,4vw,58px);background:#f5f6f3}.global-users-header{display:flex;align-items:end;justify-content:space-between;gap:20px;margin:0 auto 20px;max-width:1200px}.global-users-header h1{margin:7px 0 5px;color:#142f2a;font-size:30px;font-weight:500}.global-users-header p{margin:0;color:#728079;font-size:11px}.global-back{display:inline-flex;align-items:center;gap:6px;margin-bottom:18px;color:#557064;font-size:10px;text-decoration:none}.global-count{display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid #dfe5df;border-radius:9px;background:#fff;color:#5d7266;font-size:10px}.global-users-panel{max-width:1200px;margin:auto;overflow:hidden;border:1px solid #e1e6e1;border-radius:12px;background:#fff}.global-users-toolbar{display:flex;gap:10px;padding:15px 18px;border-bottom:1px solid #e9ede9}.global-users-toolbar .search-box{width:min(480px,100%);height:40px}.global-users-toolbar .button{display:inline-flex;align-items:center;gap:7px}.global-users-table-wrap{overflow:auto}.global-users-table-wrap table{width:100%;border-collapse:collapse;white-space:nowrap}.global-users-table-wrap th{padding:12px 16px;background:#f8faf8;color:#829088;text-align:left;font-size:8px;letter-spacing:.1em;text-transform:uppercase}.global-users-table-wrap td{padding:14px 16px;border-top:1px solid #eef1ee;color:#697770;font-size:10px}.global-user-person{display:flex;align-items:center;gap:10px}.global-user-person>span:first-child{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#e8efea;color:#4e7663;font-size:9px;font-weight:700}.global-user-person>span:last-child{display:grid;gap:3px}.global-user-person strong{color:#30453b;font-size:10px}.global-user-person small{color:#87938c;font-size:9px}.global-access{display:inline-flex;align-items:center;gap:6px;color:#4e7663}.global-users-empty{min-height:210px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:#77857e;font-size:10px}.global-error{margin:16px}.global-pagination{display:flex;justify-content:space-between;align-items:center;padding:13px 18px;border-top:1px solid #edf0ed;color:#748078;font-size:10px}.global-pagination>div{display:flex;gap:8px}.global-pagination .button{min-height:32px;padding:0 10px;font-size:9px}.global-provisioning-note{margin:0;padding:12px 18px;border-top:1px solid #edf0ed;background:#fbfcfa;color:#86918a;font-size:9px;line-height:1.5}.global-users-skeleton{display:grid;gap:8px;padding:16px 18px}.global-users-skeleton i{height:42px;border-radius:7px;background:linear-gradient(100deg,#e3e8e3 18%,#f8faf7 40%,#e3e8e3 62%);background-size:220% 100%;animation:global-shimmer 1.15s ease-in-out infinite}@keyframes global-shimmer{to{background-position-x:-220%}}@media(max-width:600px){.global-users-header{align-items:flex-start;flex-direction:column}.global-users-toolbar{align-items:stretch;flex-direction:column}.global-users-toolbar .search-box{width:100%}}@media(prefers-reduced-motion:reduce){.global-users-skeleton i{animation:none}}
</style>
