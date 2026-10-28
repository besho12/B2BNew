<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Building2, ShieldCheck, UserPlus, Users } from '@lucide/vue'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'

type BPUser = { id: number; user: { id: number; name: string; email: string }; role: string; permissions: string[]; full_access: boolean; status: 'active' | 'inactive'; joined_at: string | null }
type PendingInvitation = { id: number; name: string; email: string; permissions: string[]; status: string; expires_at: string }
type PermissionGroups = Record<string, Record<string, string>>
const DEFAULT_USER_PERMISSIONS = ['view_team', 'view_product_catalog', 'create_quick_orders', 'manage_basket', 'view_sales_orders', 'view_account_details', 'view_address_book', 'view_inventory', 'view_own_leads', 'create_leads', 'update_own_leads', 'manage_own_tasks', 'view_own_deals', 'create_deals', 'update_own_deals', 'view_own_reports', 'view_own_commissions', 'view_own_support_cases', 'create_support_cases', 'reply_support_cases']
const auth = useAuthStore()
const users = ref<BPUser[]>([])
const invitations = ref<PendingInvitation[]>([])
const permissionGroups = ref<PermissionGroups>({})
const loading = ref(true)
const saving = ref(false)
const impersonatingUserId = ref<number | null>(null)
const error = ref('')
const notice = ref('')
const form = reactive({ name: '', email: '', permissions: [...DEFAULT_USER_PERMISSIONS] })
const editingUserId = ref<number | null>(null)
const permissionDraft = ref<string[]>([])
const account = computed(() => auth.portalAccounts.find(item => item.code === auth.selectedPortalAccount) || null)
const canManage = computed(() => ['owner', 'admin'].includes(auth.bootstrap?.membership.role || ''))
async function load() {
  loading.value = true; error.value = ''
  try { const { data } = await api.get('/team/members'); users.value = data.data || []; invitations.value = data.invitations || []; permissionGroups.value = data.permission_groups || {} }
  catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to load BP users.' }
  finally { loading.value = false }
}
async function addUser() {
  saving.value = true; error.value = ''; notice.value = ''
  try {
    const { data } = await api.post('/team/members', { ...form, permissions: [...form.permissions] })
    notice.value = data.member ? `${data.member.user.name} was added to this BP workspace.` : `An invitation was sent to ${data.invitation.email}.`
    form.name = ''; form.email = ''; form.permissions = [...DEFAULT_USER_PERMISSIONS]; await load()
  } catch (exception: any) { error.value = Object.values(exception.response?.data?.errors || { error: [exception.response?.data?.message || 'Unable to add this BP user.'] }).flat().join(' ') }
  finally { saving.value = false }
}
async function updateUser(user: BPUser, field: 'role' | 'status', value: string) {
  const previous = user[field]; user[field] = value as never; error.value = ''; notice.value = ''
  try { const { data } = await api.patch(`/team/members/${user.id}`, { [field]: value }); Object.assign(user, data); notice.value = `${user.user.name}'s BP access was updated.` }
  catch (exception: any) { user[field] = previous as never; error.value = exception.response?.data?.message || 'Unable to update BP user access.' }
}
function editPermissions(user: BPUser) { editingUserId.value = user.id; permissionDraft.value = [...user.permissions] }
function cancelPermissions() { editingUserId.value = null; permissionDraft.value = [] }
async function savePermissions(user: BPUser) {
  saving.value = true; error.value = ''; notice.value = ''
  try {
    const { data } = await api.patch(`/team/members/${user.id}`, { permissions: [...permissionDraft.value] })
    Object.assign(user, data); cancelPermissions(); notice.value = `Permissions updated for ${user.user.name}.`
  } catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to update BP user permissions.' }
  finally { saving.value = false }
}
async function loginAs(user: BPUser) {
  if (user.status !== 'active' || user.user.id === auth.bootstrap?.user.id || !window.confirm(`Sign in as ${user.user.name}? You can return to your account from the banner at the top of the page.`)) return
  impersonatingUserId.value = user.id; error.value = ''
  try {
    await api.post(`/team/members/${user.id}/impersonate`)
    window.location.assign('/products')
  } catch (exception: any) {
    error.value = exception.response?.data?.message || `Unable to sign in as ${user.user.name}.`
    impersonatingUserId.value = null
  }
}
function permissionCount(permissions: string[]) { return `${permissions.length} permission${permissions.length === 1 ? '' : 's'}` }
async function revoke(invitation: PendingInvitation) {
  try { await api.delete(`/team/invitations/${invitation.id}`); invitations.value = invitations.value.filter(item => item.id !== invitation.id); notice.value = `The invitation for ${invitation.email} was revoked.` }
  catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to revoke this invitation.' }
}
function dateLabel(value: string | null) { return value ? new Intl.DateTimeFormat('en-EG', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value)) : '—' }
onMounted(load)
</script>

<template>
  <header class="page-heading"><div><span class="eyebrow">B2B ACCOUNT USERS</span><h1>BP users</h1><p>Manage sign-in access for {{ account?.name || auth.bootstrap?.company.name }}.</p></div><span class="b2b-current-account"><Building2 :size="16"/> {{ account?.code || 'Workspace account' }}</span></header>
  <div v-if="error" class="form-error bp-user-message" role="alert">{{ error }} <button class="button secondary" @click="load">Retry</button></div><div v-if="notice" class="team-success bp-user-message" role="status">{{ notice }}</div>

  <section v-if="canManage" class="data-panel bp-user-invite"><div><span class="eyebrow">ADD ACCOUNT ACCESS</span><h2>Invite a BP user</h2><p>Existing users are added to this workspace; new users receive an invitation. Choose the permissions they need below.</p></div><form @submit.prevent="addUser"><label>Full name<input v-model="form.name" autocomplete="name" required placeholder="Contact name"></label><label>Email address<input v-model="form.email" type="email" autocomplete="email" required placeholder="name@company.com"></label><details class="bp-permission-details"><summary>{{ permissionCount(form.permissions) }} selected · Configure permissions</summary><div v-for="(permissions, groupName) in permissionGroups" :key="groupName" class="bp-permission-group"><strong>{{ groupName }}</strong><label v-for="(label, key) in permissions" :key="key"><input v-model="form.permissions" type="checkbox" :value="key"><span>{{ label }}</span></label></div></details><button class="button primary" :disabled="saving"><UserPlus :size="16"/> {{ saving ? 'Sending…' : 'Invite BP user' }}</button></form></section>

  <section class="data-panel bp-user-list"><header><div><span class="eyebrow">CUSTOMER WORKSPACE</span><h2>BP user access</h2><p>{{ users.length }} user{{ users.length === 1 ? '' : 's' }} linked to this workspace.</p></div><ShieldCheck :size="20"/></header>
    <div v-if="loading" class="bp-user-loading" aria-busy="true"><i v-for="row in 5" :key="row"></i></div>
    <div v-else-if="!users.length" class="bp-user-empty"><Users :size="24"/><strong>No BP users are linked yet.</strong></div>
    <div v-else class="bp-user-table-wrap"><table class="bp-user-table"><thead><tr><th>User</th><th>Permissions</th><th>Status</th><th>Joined</th><th v-if="canManage">Actions</th></tr></thead><tbody><template v-for="user in users" :key="user.id"><tr><td><span class="bp-user-person"><span>{{ user.user.name.split(' ').map(part => part[0]).join('').slice(0, 2) }}</span><span><strong>{{ user.user.name }}</strong><small>{{ user.user.email }}</small></span></span></td><td><span v-if="user.full_access" class="bp-full-access">Full access</span><button v-else-if="canManage" class="bp-edit-permissions" type="button" @click="editingUserId === user.id ? cancelPermissions() : editPermissions(user)">{{ editingUserId === user.id ? 'Close editor' : permissionCount(user.permissions) + ' · Edit' }}</button><span v-else>{{ permissionCount(user.permissions) }}</span></td><td><AppSelect :value="user.status" :disabled="!canManage || user.role === 'owner' || user.user.id === auth.bootstrap?.user.id" @change="updateUser(user, 'status', ($event.target as HTMLSelectElement).value)"><option value="active">Active</option><option value="inactive">Inactive</option></AppSelect></td><td>{{ dateLabel(user.joined_at) }}</td><td v-if="canManage"><button class="bp-login-as" type="button" :disabled="saving || impersonatingUserId !== null || user.status !== 'active' || user.user.id === auth.bootstrap?.user.id" @click="loginAs(user)">{{ impersonatingUserId === user.id ? 'Opening…' : 'Login as' }}</button></td></tr><tr v-if="editingUserId === user.id" class="bp-permission-row"><td :colspan="canManage ? 5 : 4"><div class="bp-permission-editor"><section v-for="(permissions, groupName) in permissionGroups" :key="groupName" class="bp-permission-group"><strong>{{ groupName }}</strong><label v-for="(label, key) in permissions" :key="key"><input v-model="permissionDraft" type="checkbox" :value="key"><span>{{ label }}</span></label></section><footer><button class="button subtle" type="button" @click="cancelPermissions">Cancel</button><button class="button primary" type="button" :disabled="saving" @click="savePermissions(user)"><ShieldCheck :size="14"/>{{ saving ? 'Saving…' : 'Save permissions' }}</button></footer></div></td></tr></template></tbody></table></div>
    <div v-if="invitations.length" class="bp-user-invitations"><h3>Pending invitations</h3><div v-for="invitation in invitations" :key="invitation.id" class="bp-user-invitation"><span><strong>{{ invitation.name }}</strong><small>{{ invitation.email }}</small></span><span class="bp-user-role">{{ permissionCount(invitation.permissions) }}</span><span class="bp-invite-state">{{ invitation.status }} · expires {{ dateLabel(invitation.expires_at) }}</span><button v-if="canManage && invitation.status === 'pending'" class="button subtle" @click="revoke(invitation)">Revoke</button></div></div>
    <p v-if="!canManage" class="bp-user-readonly">Only workspace owners and admins can update BP user access.</p>
  </section>
</template>

<style scoped>
.bp-user-message{margin-bottom:14px}.bp-user-message .button{margin-left:8px}.bp-user-invite,.bp-user-list{padding:20px;border:1px solid var(--line);border-radius:12px;background:#fff}.bp-user-invite{margin-bottom:16px}.bp-user-invite h2,.bp-user-list h2{margin:4px 0;color:#263c33;font-size:15px}.bp-user-invite p,.bp-user-list header p{margin:4px 0 0;color:var(--muted);font-size:10px}.bp-user-invite form{display:grid;grid-template-columns:1.2fr 1.4fr 1fr auto;align-items:end;gap:11px;margin-top:17px}.bp-user-invite label{display:grid;gap:6px;color:#596b62;font-size:9px;font-weight:700}.bp-user-invite input,.bp-user-invite select,.bp-user-table select{height:38px;padding:0 10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:#40544a;font-size:10px}.bp-user-invite .button{min-height:38px;display:inline-flex;align-items:center;gap:7px;white-space:nowrap}.bp-user-list>header{display:flex;justify-content:space-between;align-items:center;padding-bottom:16px}.bp-user-list>header>svg{color:#648271}.bp-user-table-wrap{overflow:auto}.bp-user-table{width:100%;border-collapse:collapse;white-space:nowrap}.bp-user-table th{padding:11px 12px;background:#f8faf8;color:#7f8a84;text-align:left;font-size:8px;letter-spacing:.08em;text-transform:uppercase}.bp-user-table td{padding:12px;border-top:1px solid #edf0ed;color:#56665e;font-size:10px}.bp-user-table td select{height:32px}.bp-user-person{display:flex;align-items:center;gap:9px}.bp-user-person>span:first-child{width:32px;height:32px;display:grid;place-items:center;border-radius:10px;background:#e8efea;color:#4e7663;font-size:9px;font-weight:700}.bp-user-person>span:last-child{display:grid;gap:3px}.bp-user-person strong{color:#31463c;font-size:10px}.bp-user-person small,.bp-user-invitation small{color:#86918b;font-size:9px}.bp-user-empty{min-height:190px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:#77857e;font-size:10px}.bp-user-invitations{margin-top:18px;padding-top:16px;border-top:1px solid var(--line)}.bp-user-invitations h3{margin:0 0 10px;color:#31463c;font-size:12px}.bp-user-invitation{display:grid;grid-template-columns:1fr 100px 1.2fr auto;align-items:center;gap:10px;padding:10px 0;border-top:1px solid #f0f2f0;font-size:9px}.bp-user-invitation>span:first-child{display:grid;gap:3px}.bp-user-role,.bp-invite-state{color:#718078}.bp-user-readonly{margin:14px 0 0;color:#8a958f;font-size:9px}.bp-user-loading{display:grid;gap:9px}.bp-user-loading i{height:40px;border-radius:7px;background:linear-gradient(100deg,#e3e8e3 18%,#f8faf7 40%,#e3e8e3 62%);background-size:220% 100%;animation:bp-user-shimmer 1.15s ease-in-out infinite}@keyframes bp-user-shimmer{to{background-position-x:-220%}}@media(max-width:920px){.bp-user-invite form{grid-template-columns:1fr 1fr}.bp-user-invitation{grid-template-columns:1fr auto}}@media(max-width:560px){.bp-user-invite form{grid-template-columns:1fr}.bp-user-invitation{grid-template-columns:1fr}.bp-user-list{padding:14px}}@media(prefers-reduced-motion:reduce){.bp-user-loading i{animation:none}}
</style>

<style scoped>
.bp-user-invite form{grid-template-columns:1fr 1fr}.bp-permission-details{grid-column:1/-1;padding:10px 12px;border:1px solid #e8ede8;border-radius:8px;background:#fafbf9}.bp-permission-details summary{color:#4e685a;font-size:9px;font-weight:700;cursor:pointer}.bp-permission-details[open] summary{margin-bottom:11px}.bp-permission-group{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px 12px;padding:12px 0;border-top:1px solid #e9ede8}.bp-permission-group>strong{grid-column:1/-1;color:#344b3e;font-size:9px}.bp-permission-group label{display:flex;align-items:flex-start;gap:7px;color:#63736a;font-size:9px;font-weight:500;line-height:1.45;cursor:pointer}.bp-permission-group input{width:14px;height:14px;flex:none;margin:0;accent-color:#537965}.bp-user-table .bp-edit-permissions{padding:5px 7px;border:1px solid #dfe7df;border-radius:6px;background:#fff;color:#4d715d;font-size:9px;cursor:pointer}.bp-user-table .bp-edit-permissions:hover{border-color:#9eb6a4;background:#f7faf7}.bp-full-access{display:inline-flex;padding:5px 8px;border-radius:20px;background:#edf5ee;color:#477353;font-size:8px;font-weight:700}.bp-permission-row td{white-space:normal!important;background:#fafbf9}.bp-permission-editor{padding:4px 2px 10px}.bp-permission-editor .bp-permission-group{grid-template-columns:repeat(4,minmax(0,1fr));padding:12px}.bp-permission-editor footer{display:flex;justify-content:flex-end;gap:8px;padding:10px 12px}.bp-permission-editor footer .button{display:inline-flex;align-items:center;gap:6px}.bp-user-invitation .bp-user-role{font-size:9px}@media(max-width:920px){.bp-permission-group{grid-template-columns:repeat(2,minmax(0,1fr))}.bp-permission-editor .bp-permission-group{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:560px){.bp-user-invite form{grid-template-columns:1fr}.bp-permission-details{grid-column:auto}.bp-permission-group,.bp-permission-editor .bp-permission-group{grid-template-columns:1fr}.bp-permission-editor footer{padding-inline:0}}
</style>

<style scoped>
.bp-login-as{padding:6px 9px;border:1px solid #dfe7df;border-radius:7px;background:#fff;color:#3f6c55;font-size:9px;font-weight:700;cursor:pointer}.bp-login-as:hover:not(:disabled){border-color:#9eb6a4;background:#f5faf6}.bp-login-as:disabled{opacity:.45;cursor:not-allowed}
</style>
