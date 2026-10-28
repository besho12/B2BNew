<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { LifeBuoy, Paperclip, Plus, RefreshCw, Send, ShieldCheck, Star } from '@lucide/vue'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'
import Modal from '../components/Modal.vue'

type CaseAttachment = { id: number; original_name: string; mime_type: string; size_bytes: number; uploaded_at: string; url: string }
type SupportMessage = { id: number; body: string; is_internal: boolean; created_at: string; author?: { id: number; name: string } | null; attachments?: CaseAttachment[] }
type SupportCase = {
  id: number; created_by: number; subject: string; description: string; category: string; priority: string; status: string;
  system_reference?: string | null; support_contract?: string | null; serial_number?: string | null;
  contact_person?: string | null; contact_email?: string | null; contact_phone?: string | null;
  created_at: string; messages_count?: number; creator?: { id: number; name: string };
  assignee?: { id: number; name: string } | null; messages?: SupportMessage[]; attachments?: CaseAttachment[];
  feedback_rating?: number | null; feedback_comment?: string | null; feedback_submitted_at?: string | null;
}

const auth = useAuthStore()
const cases = ref<SupportCase[]>([])
const page = ref(1)
const lastPage = ref(1)
const selected = ref<SupportCase | null>(null)
const loading = ref(true)
const detailLoading = ref(false)
const saving = ref(false)
const replying = ref(false)
const uploading = ref(false)
const feedbackSaving = ref(false)
const error = ref('')
const notice = ref('')
const search = ref('')
const statusFilter = ref('')
const showCreate = ref(false)
const role = computed(() => auth.bootstrap?.membership.role || '')
const permissions = computed(() => auth.bootstrap?.membership.permissions || [])
const canManage = computed(() => ['owner', 'admin', 'manager', 'operations'].includes(role.value) || permissions.value.includes('manage_support_cases'))
const canCreate = computed(() => ['owner', 'admin', 'manager', 'agent', 'operations'].includes(role.value) || permissions.value.includes('create_support_cases'))
const canReply = computed(() => ['owner', 'admin', 'manager', 'agent', 'operations'].includes(role.value) || permissions.value.includes('reply_support_cases'))
const currentUserId = computed(() => auth.bootstrap?.user.id ?? null)
const canRate = computed(() => !!selected.value && currentUserId.value !== null && selected.value.created_by === currentUserId.value && ['resolved', 'closed'].includes(selected.value.status) && !selected.value.feedback_submitted_at)
const form = reactive({
  subject: '', description: '', category: 'general', priority: 'normal',
  system_reference: '', support_contract: '', serial_number: '',
  contact_person: '', contact_email: '', contact_phone: '',
})
const reply = reactive({ body: '', is_internal: false })
const feedback = reactive({ rating: '5', comment: '' })
const caseFiles = ref<File[]>([])
const replyFiles = ref<File[]>([])
const statuses = ['open', 'in_progress', 'waiting', 'resolved', 'closed']

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/support-cases', { params: { search: search.value || undefined, status: statusFilter.value || undefined, page: page.value } })
    cases.value = data.data
    page.value = data.current_page
    lastPage.value = data.last_page
    if (selected.value && !cases.value.some(item => item.id === selected.value?.id)) selected.value = null
  } catch (exception: any) {
    error.value = exception.response?.data?.message || 'Unable to load support cases.'
  } finally { loading.value = false }
}

async function openCase(item: SupportCase) {
  selected.value = item
  detailLoading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/support-cases/' + item.id)
    selected.value = data
    feedback.rating = String(data.feedback_rating || 5)
    feedback.comment = ''
  } catch (exception: any) {
    error.value = exception.response?.data?.message || 'Unable to load this case.'
  } finally { detailLoading.value = false }
}

function setFiles(event: Event, target: 'case' | 'reply') {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files || [])
  if (target === 'case') caseFiles.value = files
  else replyFiles.value = files
}

async function uploadFiles(caseId: number, files: File[], messageId?: number) {
  for (const file of files) {
    const payload = new FormData()
    payload.append('attachment', file)
    if (messageId) payload.append('message_id', String(messageId))
    await api.post('/support-cases/' + caseId + '/attachments', payload)
  }
}

function resetForm() {
  Object.assign(form, {
    subject: '', description: '', category: 'general', priority: 'normal',
    system_reference: '', support_contract: '', serial_number: '',
    contact_person: '', contact_email: '', contact_phone: '',
  })
  caseFiles.value = []
  error.value = ''
}

async function createCase() {
  saving.value = true
  error.value = ''
  const files = [...caseFiles.value]
  try {
    const { data } = await api.post('/support-cases', form)
    showCreate.value = false
    notice.value = 'Support case created.'
    resetForm()
    await load()
    await openCase(data)
    if (files.length) {
      uploading.value = true
      try {
        await uploadFiles(data.id, files)
        await openCase(data)
      } catch (exception: any) {
        error.value = 'The case was created, but one or more attachments could not be uploaded.'
      } finally { uploading.value = false }
    }
  } catch (exception: any) {
    error.value = Object.values(exception.response?.data?.errors || { error: [exception.response?.data?.message || 'Unable to create support case.'] }).flat().join(' ')
  } finally { saving.value = false }
}

async function sendReply() {
  if (!selected.value) return
  replying.value = true
  error.value = ''
  const caseId = selected.value.id
  const files = [...replyFiles.value]
  try {
    const { data: message } = await api.post('/support-cases/' + caseId + '/messages', reply)
    reply.body = ''
    reply.is_internal = false
    replyFiles.value = []
    let attachmentFailed = false
    if (files.length) {
      uploading.value = true
      try {
        await uploadFiles(caseId, files, message.id)
      } catch (exception: any) {
        attachmentFailed = true
      }
    }
    await openCase(selected.value)
    await load()
    if (attachmentFailed) error.value = 'Your reply was sent, but one or more attachments could not be uploaded.'
  } catch (exception: any) {
    error.value = exception.response?.data?.message || 'Unable to send your reply.'
  } finally { replying.value = false; uploading.value = false }
}

async function submitFeedback() {
  if (!selected.value) return
  feedbackSaving.value = true
  error.value = ''
  try {
    await api.post('/support-cases/' + selected.value.id + '/feedback', { rating: Number(feedback.rating), comment: feedback.comment || null })
    notice.value = 'Thank you for your feedback.'
    await openCase(selected.value)
  } catch (exception: any) {
    error.value = exception.response?.data?.message || 'Unable to submit feedback.'
  } finally { feedbackSaving.value = false }
}

async function changeAssignee(userId: string) {
  if (!selected.value) return
  try {
    const { data } = await api.patch('/support-cases/' + selected.value.id, { assigned_to: userId ? Number(userId) : null })
    selected.value = { ...selected.value, ...data }
    notice.value = 'Case assignment updated.'
    await load()
  } catch (exception: any) {
    error.value = exception.response?.data?.message || 'Unable to update case assignment.'
  }
}

async function changeStatus(status: string) {
  if (!selected.value) return
  try {
    const { data } = await api.patch('/support-cases/' + selected.value.id, { status })
    selected.value = { ...selected.value, ...data }
    notice.value = 'Case marked ' + status.replace('_', ' ') + '.'
    await load()
  } catch (exception: any) {
    error.value = exception.response?.data?.message || 'Unable to update case status.'
  }
}

function label(value: string) { return value.replace(/_/g, ' ') }
function formatDate(value: string) { return new Intl.DateTimeFormat(auth.locale === 'ar' ? 'ar-EG' : 'en-EG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) }
function sizeLabel(bytes: number) { return bytes < 1024 * 1024 ? Math.max(1, Math.round(bytes / 1024)) + ' KB' : (bytes / 1024 / 1024).toFixed(1) + ' MB' }
onMounted(load)
</script>

<template>
  <div class="support-page">
    <header class="page-heading">
      <div><span class="eyebrow">WORKSPACE HELP</span><h1>Support tickets</h1><p>Keep questions and issues organized, with a clear conversation history.</p></div>
      <button v-if="canCreate" class="button primary" @click="showCreate = true"><Plus />New ticket</button>
    </header>
    <div v-if="notice" class="support-notice" role="status">{{ notice }}<button @click="notice = ''">Dismiss</button></div>
    <div v-if="error" class="form-error support-error" role="alert">{{ error }}</div>
    <section class="support-layout">
      <div class="panel support-list-panel">
        <div class="support-filters">
          <input v-model="search" placeholder="Search support cases" @keyup.enter="page = 1; load()">
          <AppSelect v-model="statusFilter" aria-label="Filter by status" @change="page = 1; load()"><option value="">All statuses</option><option v-for="status in statuses" :key="status" :value="status">{{ label(status) }}</option></AppSelect>
          <button class="icon-button" aria-label="Refresh cases" :disabled="loading" @click="load"><RefreshCw :size="16" /></button>
        </div>
        <div v-if="loading" class="support-empty">Loading cases…</div>
        <template v-else>
          <button v-for="item in cases" :key="item.id" class="support-case-row" :class="{ active: selected?.id === item.id }" @click="openCase(item)">
            <div class="support-row-top"><strong>{{ item.subject }}</strong><span class="case-status" :class="'status-' + item.status">{{ label(item.status) }}</span></div>
            <p>{{ item.description }}</p><div class="support-row-meta"><span>{{ item.category }}</span><span :class="'priority-' + item.priority">{{ item.priority }}</span><span>{{ item.messages_count || 0 }} replies</span></div>
          </button>
          <div v-if="lastPage > 1" class="support-pagination"><button class="button secondary" :disabled="page <= 1 || loading" @click="page--; load()">Previous</button><span>Page {{ page }} of {{ lastPage }}</span><button class="button secondary" :disabled="page >= lastPage || loading" @click="page++; load()">Next</button></div>
          <div v-if="!cases.length" class="support-empty"><LifeBuoy :size="24" /><strong>No tickets found</strong><span>Open a case when your team needs help.</span></div>
        </template>
      </div>
      <section class="panel support-detail-panel">
        <div v-if="!selected" class="support-empty support-select-prompt"><LifeBuoy :size="30" /><strong>Select a support case</strong><span>Choose a case to read its conversation.</span></div>
        <template v-else>
          <header class="support-detail-heading">
            <div><span class="eyebrow">CASE #{{ selected.id }} · {{ selected.category }}</span><h2>{{ selected.subject }}</h2><p>Opened by {{ selected.creator?.name || 'Workspace member' }} · {{ formatDate(selected.created_at) }}</p></div>
            <div v-if="canManage" class="support-management-controls">
              <AppSelect :value="selected.status" aria-label="Change case status" @change="changeStatus(($event.target as HTMLSelectElement).value)"><option v-for="status in statuses" :key="status" :value="status">{{ label(status) }}</option></AppSelect>
              <AppSelect :value="selected.assignee?.id || ''" aria-label="Assign support case" @change="changeAssignee(($event.target as HTMLSelectElement).value)"><option value="">Unassigned</option><option v-for="member in auth.bootstrap?.members || []" :key="member.id" :value="member.id">{{ member.name }}</option></AppSelect>
            </div>
            <span v-else class="case-status" :class="'status-' + selected.status">{{ label(selected.status) }}</span>
          </header>
          <div v-if="detailLoading" class="support-empty">Loading conversation…</div>
          <div v-else class="support-thread">
            <article class="support-message initial-message">
              <div class="support-message-author"><strong>{{ selected.creator?.name || 'Workspace member' }}</strong><time>{{ formatDate(selected.created_at) }}</time></div>
              <div class="support-metadata"><span v-if="selected.system_reference">Reference: {{ selected.system_reference }}</span><span v-if="selected.support_contract">Contract: {{ selected.support_contract }}</span><span v-if="selected.serial_number">Serial: {{ selected.serial_number }}</span><span v-if="selected.contact_person">Contact: {{ selected.contact_person }}</span><span v-if="selected.contact_email">{{ selected.contact_email }}</span><span v-if="selected.contact_phone">{{ selected.contact_phone }}</span></div>
              <p>{{ selected.description }}</p>
              <a v-for="file in selected.attachments || []" :key="file.id" class="support-attachment" :href="file.url"><Paperclip :size="14" />{{ file.original_name }}<small>{{ sizeLabel(file.size_bytes) }}</small></a>
            </article>
            <article v-for="message in selected.messages || []" :key="message.id" class="support-message" :class="{ internal: message.is_internal }">
              <div class="support-message-author"><strong>{{ message.author?.name || 'Former member' }}<ShieldCheck v-if="message.is_internal" :size="13" /></strong><time>{{ formatDate(message.created_at) }}</time></div>
              <span v-if="message.is_internal" class="internal-label">Internal note</span><p>{{ message.body }}</p>
              <a v-for="file in message.attachments || []" :key="file.id" class="support-attachment" :href="file.url"><Paperclip :size="14" />{{ file.original_name }}<small>{{ sizeLabel(file.size_bytes) }}</small></a>
            </article>
          </div>
          <form v-if="canReply && !['resolved', 'closed'].includes(selected.status)" class="support-reply" @submit.prevent="sendReply">
            <textarea v-model="reply.body" rows="3" maxlength="10000" required placeholder="Write a reply…"></textarea>
            <div class="support-reply-tools">
              <label v-if="canManage" class="internal-toggle"><input v-model="reply.is_internal" type="checkbox"> Add as internal note</label>
              <label class="file-picker"><Paperclip :size="15" />Attach files<input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx" @change="setFiles($event, 'reply')"></label>
              <button class="button primary" :disabled="replying || !reply.body.trim()"><Send :size="16" />{{ uploading ? 'Uploading…' : replying ? 'Sending…' : 'Send reply' }}</button>
            </div>
            <small v-if="replyFiles.length" class="selected-files">{{ replyFiles.map(file => file.name).join(', ') }}</small>
          </form>
          <div v-if="['resolved', 'closed'].includes(selected.status)" class="support-feedback-area">
            <div v-if="selected.feedback_submitted_at" class="feedback-thanks"><Star :size="16" />Your feedback: {{ selected.feedback_rating }}/5<span v-if="selected.feedback_comment"> · {{ selected.feedback_comment }}</span></div>
            <form v-else-if="canRate" class="support-feedback-form" @submit.prevent="submitFeedback">
              <strong>How was your support experience?</strong>
              <label>Rating<AppSelect v-model="feedback.rating"><option v-for="rating in [5,4,3,2,1]" :key="rating" :value="String(rating)">{{ rating }} / 5</option></AppSelect></label>
              <textarea v-model="feedback.comment" rows="2" maxlength="3000" placeholder="Optional comments"></textarea>
              <button class="button primary" :disabled="feedbackSaving"><Star :size="15" />{{ feedbackSaving ? 'Saving…' : 'Send feedback' }}</button>
            </form>
            <p v-else class="support-closed-note">This case is closed. A manager can reopen it to continue the conversation.</p>
          </div>
          <p v-else-if="!canReply" class="support-closed-note">You can view this case, but your role cannot reply.</p>
        </template>
      </section>
    </section>
    <Modal v-if="showCreate" title="Open a support ticket" description="Tell your workspace team what you need help with." @close="showCreate = false">
      <form class="form-grid" @submit.prevent="createCase">
        <label class="full">Subject<input v-model="form.subject" required maxlength="190" placeholder="What do you need help with?"></label>
        <label>Category<AppSelect v-model="form.category"><option value="general">General</option><option value="account">Account</option><option value="product">Product</option><option value="order">Order</option><option value="billing">Billing</option><option value="technical">Technical</option><option value="other">Other</option></AppSelect></label>
        <label>Priority<AppSelect v-model="form.priority"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></AppSelect></label>
        <label>System reference<input v-model="form.system_reference" maxlength="120" placeholder="Optional system or account reference"></label>
        <label>Support contract<input v-model="form.support_contract" maxlength="120"></label>
        <label>Serial number<input v-model="form.serial_number" maxlength="120"></label>
        <label>Contact person<input v-model="form.contact_person" maxlength="160"></label>
        <label>Contact email<input v-model="form.contact_email" type="email" maxlength="190"></label>
        <label>Contact phone<input v-model="form.contact_phone" maxlength="40"></label>
        <label class="full">Details<textarea v-model="form.description" rows="5" required maxlength="10000" placeholder="Include the details your support team needs."></textarea></label>
        <label class="file-picker full"><Paperclip :size="15" />Attach files<input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx" @change="setFiles($event, 'case')"></label>
        <small v-if="caseFiles.length" class="selected-files full">{{ caseFiles.map(file => file.name).join(', ') }}</small>
        <div v-if="error" class="form-error full">{{ error }}</div>
        <div class="form-actions full"><button type="button" class="button secondary" @click="showCreate = false">Cancel</button><button class="button primary" :disabled="saving || uploading">{{ saving ? 'Creating…' : uploading ? 'Uploading…' : 'Create case' }}</button></div>
      </form>
    </Modal>
  </div>
</template>

<style scoped>
.support-page { display: grid; gap: 18px; }
.support-layout { display: grid; grid-template-columns: minmax(300px, .78fr) minmax(0, 1.4fr); gap: 18px; align-items: start; }
.support-list-panel, .support-detail-panel { overflow: hidden; min-height: 540px; }
.support-filters { display: flex; gap: 8px; padding: 14px; border-bottom: 1px solid var(--line); }
.support-filters input, .support-filters select, .support-detail-heading select, .support-feedback-form select { min-width: 0; border: 1px solid var(--line); border-radius: 9px; padding: 9px 10px; background: var(--panel); color: inherit; }
.support-filters input { flex: 1; }
.support-case-row { display: block; width: 100%; text-align: left; padding: 15px 16px; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: inherit; cursor: pointer; }
.support-case-row:hover, .support-case-row.active { background: #f7f8f5; }
.support-row-top, .support-row-meta, .support-message-author { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.support-row-top strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.case-status { display: inline-flex; padding: 4px 8px; border-radius: 999px; background: #eef2f7; color: #475569; font-size: 11px; text-transform: capitalize; white-space: nowrap; }
.status-open { background: #eaf3ff; color: #2563a5; }.status-in_progress { background: #fff3d8; color: #926000; }.status-waiting { background: #f3edff; color: #7044a4; }.status-resolved, .status-closed { background: #e8f7ee; color: #267449; }
.support-case-row p { margin: 8px 0 10px; color: var(--muted); font-size: 13px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.support-row-meta { justify-content: flex-start; color: var(--muted); font-size: 11px; text-transform: capitalize; }.priority-urgent, .priority-high { color: #bc3d38; }
.support-empty { display: grid; justify-items: center; gap: 9px; padding: 42px 20px; color: var(--muted); text-align: center; }.support-empty strong { color: var(--ink); }.support-select-prompt { min-height: 480px; align-content: center; }
.support-pagination { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; color: var(--muted); font-size: 11px; }.support-pagination .button { min-height: 30px; padding: 6px 10px; font-size: 10px; }
.support-detail-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 20px; border-bottom: 1px solid var(--line); }.support-detail-heading h2 { margin: 7px 0 5px; font-size: 20px; }.support-detail-heading p { margin: 0; color: var(--muted); font-size: 12px; }
.support-management-controls { display: flex; flex-wrap: wrap; gap: 8px; }
.support-thread { display: grid; gap: 12px; padding: 18px; max-height: 390px; overflow: auto; }.support-message { padding: 14px; border: 1px solid var(--line); border-radius: 12px; background: var(--panel); }.support-message.initial-message { background: #f7f8f5; }.support-message.internal { border-color: #ead8ad; background: #fffaf0; }.support-message-author strong { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; }.support-message-author time { color: var(--muted); font-size: 11px; }.support-message p { margin: 9px 0 0; white-space: pre-wrap; line-height: 1.5; font-size: 13px; }
.internal-label { color: #8a620e; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; }.support-metadata { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 9px; }.support-metadata span { padding: 4px 7px; border-radius: 7px; background: #f1f3ef; color: #60716a; font-size: 10px; }
.support-attachment { display: inline-flex; align-items: center; gap: 6px; margin: 10px 8px 0 0; padding: 7px 9px; border: 1px solid var(--line); border-radius: 8px; color: #42695f; font-size: 11px; }.support-attachment small { color: var(--muted); }.support-attachment:hover { background: #f5f8f4; }
.support-reply { display: grid; gap: 10px; padding: 16px 18px; border-top: 1px solid var(--line); }.support-reply textarea, .support-feedback-form textarea { width: 100%; resize: vertical; border: 1px solid var(--line); border-radius: 10px; padding: 11px; font: inherit; }.support-reply-tools { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; }.internal-toggle { display: flex; align-items: center; gap: 7px; color: #8a620e; font-size: 12px; }.file-picker { position: relative; display: inline-flex; align-items: center; gap: 6px; color: #527667; font-size: 11px; font-weight: 700; cursor: pointer; }.file-picker input { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }.file-picker:focus-within { outline: 2px solid #789a89; outline-offset: 3px; }.selected-files { color: var(--muted); font-size: 10px; overflow-wrap: anywhere; }
.support-feedback-area { padding: 15px 18px; border-top: 1px solid var(--line); }.support-feedback-form { display: grid; gap: 10px; }.support-feedback-form strong { font-size: 13px; }.support-feedback-form label { display: flex; align-items: center; gap: 10px; color: var(--muted); font-size: 11px; }.support-feedback-form select { min-width: 90px; }.support-feedback-form .button { justify-self: start; }.feedback-thanks { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; color: #537968; font-size: 12px; }.feedback-thanks span { color: var(--muted); }
.support-closed-note { margin: 0; padding: 16px 18px; color: var(--muted); border-top: 1px solid var(--line); font-size: 13px; }.support-notice { display: flex; justify-content: space-between; padding: 10px 12px; border-radius: 9px; background: #e8f7ee; color: #267449; font-size: 13px; }.support-notice button { border: 0; background: none; color: inherit; cursor: pointer; text-decoration: underline; }.support-error { padding: 10px 12px; }
@media (max-width: 900px) { .support-layout { grid-template-columns: 1fr; }.support-list-panel { min-height: auto; }.support-detail-panel { min-height: 420px; } }
@media (max-width: 600px) { .support-management-controls { width: 100%; }.support-management-controls select { flex: 1; }.support-filters { flex-wrap: wrap; }.support-filters input { flex-basis: 100%; }.support-detail-heading { flex-direction: column; }.support-thread { padding: 12px; } }
</style>