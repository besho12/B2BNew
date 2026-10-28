<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ArrowDownToLine, ArrowLeft, ArrowRight, CalendarDays, ClipboardList, RefreshCw, Search } from '@lucide/vue'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'

type OrderRow = {
  id: string; order_number: string; po_reference: string; order_date: string; due_date: string
  status: string; payment_status: string; type: string; total_amount: number | null; currency: string
}
const auth = useAuthStore()
const orders = ref<OrderRow[]>([])
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const search = ref('')
const appliedSearch = ref('')
const accountDraft = ref(auth.selectedPortalAccount || '')
const loading = ref(false)
const exporting = ref(false)
const error = ref('')
let requestVersion = 0
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))
const account = computed(() => auth.portalAccounts.find(item => item.code === auth.selectedPortalAccount) || null)

function dateLabel(value: string) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(auth.locale === 'ar' ? 'ar-EG' : 'en-EG', { dateStyle: 'medium' }).format(date)
}
function money(row: OrderRow) {
  if (row.total_amount === null) return '—'
  try { return new Intl.NumberFormat(auth.locale === 'ar' ? 'ar-EG' : 'en-EG', { style: 'currency', currency: row.currency || 'EGP' }).format(row.total_amount) }
  catch { return `${row.currency || 'EGP'} ${row.total_amount.toFixed(2)}` }
}
function statusClass(value: string) { return value.toLowerCase().replace(/[^a-z0-9]+/g, '-') }

async function load() {
  if (auth.portalDemoMode) return
  const version = ++requestVersion
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/portal/orders', { params: { page: page.value, per_page: perPage.value, search: appliedSearch.value || undefined } })
    if (version !== requestVersion) return
    orders.value = data.data || []
    total.value = Number(data.total || 0)
  } catch (exception: any) {
    if (version === requestVersion) error.value = exception.response?.data?.message || 'Unable to load order history.'
  } finally {
    if (version === requestVersion) loading.value = false
  }
}
function submitSearch() { appliedSearch.value = search.value.trim(); page.value = 1; void load() }
function changePage(next: number) { if (next < 1 || next > totalPages.value || next === page.value) return; page.value = next; void load() }
function changePageSize(value: string) { perPage.value = Number(value); page.value = 1; void load() }
async function exportOrders() {
  if (auth.portalDemoMode || !total.value || exporting.value) return
  if (total.value > 10000) { error.value = 'This export contains more than 10,000 orders. Narrow your search and try again.'; return }
  exporting.value = true
  error.value = ''
  try {
    const allOrders: OrderRow[] = []
    for (let current = 1; current <= Math.ceil(total.value / 100); current += 1) {
      const { data } = await api.get('/portal/orders', { params: { page: current, per_page: 100, search: appliedSearch.value || undefined } })
      allOrders.push(...(data.data || []))
    }
    const ExcelJS = await import('exceljs')
    const workbook = new ExcelJS.Workbook()
    const sheet = workbook.addWorksheet('Sales orders')
    sheet.columns = [
      { header: 'Order number', key: 'order_number', width: 20 }, { header: 'PO reference', key: 'po_reference', width: 24 },
      { header: 'Document date', key: 'order_date', width: 18 }, { header: 'Due date', key: 'due_date', width: 18 },
      { header: 'Status', key: 'status', width: 20 }, { header: 'Payment status', key: 'payment_status', width: 20 },
      { header: 'Type', key: 'type', width: 18 }, { header: 'Total', key: 'total_amount', width: 16 }, { header: 'Currency', key: 'currency', width: 12 },
    ]
    sheet.addRows(allOrders)
    sheet.getRow(1).font = { bold: true, color: { argb: 'FF244439' } }
    sheet.views = [{ state: 'frozen', ySplit: 1 }]
    const blob = new Blob([await workbook.xlsx.writeBuffer()], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url; link.download = 'sales-order-history.xlsx'; link.click()
    URL.revokeObjectURL(url)
  } catch (exception: any) {
    error.value = exception.response?.data?.message || 'Unable to export sales order history.'
  } finally { exporting.value = false }
}
async function changeAccount() {
  if (!accountDraft.value || accountDraft.value === auth.selectedPortalAccount) return
  try {
    await auth.selectPortalAccount(accountDraft.value)
    page.value = 1; orders.value = []; total.value = 0
    await load()
  } catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to switch customer account.' }
}
onMounted(() => { if (!auth.portalDemoMode) void load() })
</script>

<template>
  <section class="page-heading">
    <div><span class="eyebrow">SALES AR</span><h1>Sales order history</h1><p v-if="account">Orders placed for {{ account.name }}.</p><p v-else>Review order documents, payment status, and delivery dates for your selected customer account.</p></div>
    <AppSelect v-if="auth.portalAccounts.length > 1" v-model="accountDraft" class="b2b-account-switch" aria-label="Customer account" @change="changeAccount"><option v-for="item in auth.portalAccounts" :key="item.code" :value="item.code">{{ item.code }} · {{ item.name }}</option></AppSelect><span v-else class="b2b-current-account"><ClipboardList :size="16"/> {{ account?.code || (auth.portalDemoMode ? 'DEMO ACCOUNT' : 'Customer account') }}</span>
  </section>

  <section class="data-panel b2b-orders-panel">
    <form class="table-toolbar b2b-orders-toolbar" @submit.prevent="submitSearch">
      <div class="search-box b2b-orders-search"><Search :size="17"/><input v-model="search" placeholder="Search order number or PO reference" aria-label="Search orders"></div>
      <div class="b2b-orders-toolbar-actions">
        <label class="b2b-page-size"><span>Rows</span><AppSelect class="b2b-order-page-size" :value="perPage" aria-label="Rows per page" @change="changePageSize(($event.target as HTMLSelectElement).value)"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></AppSelect></label>
        <button class="button secondary b2b-orders-export" type="button" :disabled="loading || exporting || !total || auth.portalDemoMode" @click="exportOrders"><ArrowDownToLine :size="15"/> {{ exporting ? 'Preparing…' : 'Export Excel' }}</button>
        <button class="button primary b2b-orders-search-button" :disabled="loading"><Search :size="15"/> Search</button>
      </div>
    </form>

    <div v-if="auth.portalDemoMode" class="b2b-orders-empty"><span><ClipboardList :size="22"/></span><strong>Order history appears when the live account is connected.</strong><p>Sample catalog activity does not create orders.</p></div>
    <div v-else-if="loading && !orders.length" class="b2b-orders-skeleton" aria-label="Loading order history" aria-busy="true"><i v-for="row in 7" :key="row"></i></div>
    <div v-else-if="error" class="form-error b2b-orders-error" role="alert">{{ error }} <button class="button secondary" @click="load">Retry</button></div>
    <div v-else-if="!orders.length" class="b2b-orders-empty"><span><CalendarDays :size="22"/></span><strong>No orders found</strong><p>Try another order number or purchase reference.</p></div>
    <div v-else class="b2b-orders-table-wrap">
      <table class="b2b-orders-table"><thead><tr><th>Order</th><th>PO reference</th><th>Order date</th><th>Delivery date</th><th>Status</th><th>Payment</th><th>Order type</th><th class="number">Total</th></tr></thead>
        <tbody><tr v-for="order in orders" :key="order.id || order.order_number"><td><strong>{{ order.order_number }}</strong></td><td>{{ order.po_reference || '—' }}</td><td>{{ dateLabel(order.order_date) }}</td><td>{{ dateLabel(order.due_date) }}</td><td><span class="b2b-order-status" :class="`status-${statusClass(order.status)}`">{{ order.status }}</span></td><td><span class="b2b-order-status" :class="`status-${statusClass(order.payment_status)}`">{{ order.payment_status }}</span></td><td>{{ order.type || '—' }}</td><td class="number total">{{ money(order) }}</td></tr></tbody>
      </table>
    </div>

    <footer v-if="!auth.portalDemoMode && total" class="b2b-orders-pagination"><span>{{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, total) }} of {{ total }} orders</span><div><button class="button secondary" :disabled="loading || page <= 1" @click="changePage(page - 1)"><ArrowLeft :size="15"/> Previous</button><span>Page {{ page }} of {{ totalPages }}</span><button class="button secondary" :disabled="loading || page >= totalPages" @click="changePage(page + 1)">Next <ArrowRight :size="15"/></button></div></footer>
    <div v-if="loading && orders.length" class="b2b-orders-refresh" role="status"><RefreshCw :size="14"/> Updating order history…</div>
  </section>
</template>

<style scoped>
.b2b-orders-panel{overflow:hidden}.b2b-orders-toolbar{display:flex;align-items:center;gap:10px;padding:15px 18px;background:#fff;border-bottom:1px solid var(--line)}.b2b-orders-toolbar .search-box{width:min(520px,100%);height:42px;border-radius:9px}.b2b-orders-toolbar .button{min-height:42px;display:inline-flex;align-items:center;gap:8px}.b2b-orders-table-wrap{overflow-x:auto}.b2b-orders-table{width:100%;border-collapse:collapse;white-space:nowrap}.b2b-orders-table th{padding:13px 15px;background:#fafbf9;color:#7d8983;text-align:left;font-size:9px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.b2b-orders-table td{padding:15px;border-top:1px solid #edf0ed;color:#5b6963;font-size:10px}.b2b-orders-table td strong{color:#263c34;font-size:11px}.b2b-orders-table .number{text-align:right}.b2b-orders-table .total{color:#263c34;font-weight:700}.b2b-order-status{display:inline-flex;padding:5px 8px;border-radius:20px;background:#f0f2ef;color:#69756f;font-size:9px;font-weight:700;text-transform:capitalize}.b2b-order-status[class*="complete"],.b2b-order-status[class*="paid"]{background:#e8f4ec;color:#37734f}.b2b-order-status[class*="cancel"],.b2b-order-status[class*="overdue"]{background:#fff0ed;color:#a45142}.b2b-order-status[class*="pending"],.b2b-order-status[class*="processing"],.b2b-order-status[class*="open"]{background:#fff5df;color:#946c19}.b2b-orders-empty{min-height:310px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}.b2b-orders-empty>span{width:48px;height:48px;display:grid;place-items:center;border-radius:14px;background:#eaf0eb;color:#557a69}.b2b-orders-empty strong{margin-top:14px;color:#344840;font-size:13px}.b2b-orders-empty p{margin:7px 0 0;color:var(--muted);font-size:10px}.b2b-orders-error{margin:18px}.b2b-orders-error .button{margin-left:8px}.b2b-orders-pagination{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 18px;border-top:1px solid var(--line);color:#77837d;font-size:10px}.b2b-orders-pagination>div{display:flex;align-items:center;gap:12px}.b2b-orders-pagination .button{min-height:34px;display:inline-flex;align-items:center;gap:6px;padding:0 10px;font-size:9px}.b2b-orders-refresh{display:flex;align-items:center;justify-content:center;gap:7px;padding:10px;color:#60766a;font-size:10px}.b2b-orders-skeleton{display:grid;gap:1px;padding:8px 18px}.b2b-orders-skeleton i{height:42px;border-radius:5px;background:linear-gradient(100deg,#eef1ee 20%,#f8f9f7 38%,#eef1ee 58%);background-size:220% 100%;animation:order-shimmer 1.35s ease-in-out infinite}@keyframes order-shimmer{to{background-position-x:-220%}}@media(max-width:700px){.b2b-orders-pagination{align-items:flex-start;flex-direction:column}.b2b-orders-pagination>div{width:100%;justify-content:space-between}.b2b-orders-toolbar{align-items:stretch;flex-direction:column}.b2b-orders-toolbar .search-box{width:100%}}@media(prefers-reduced-motion:reduce){.b2b-orders-skeleton i{animation:none}}
.b2b-orders-toolbar{display:grid;grid-template-columns:minmax(260px,1fr) auto;align-items:center;gap:14px;padding:16px 20px;background:#fff;border-bottom:1px solid var(--line)}
.b2b-orders-search{width:100%!important;min-width:0;height:44px;border-radius:10px}
.b2b-orders-toolbar-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px}
.b2b-page-size{display:flex;align-items:center;gap:7px;color:#6e7d75;font-size:10px;font-weight:600;white-space:nowrap}
.b2b-page-size .app-select{width:76px;min-width:76px}
.b2b-page-size .app-select-trigger{min-height:40px;padding:0 9px;border-color:#e1e7e2;border-radius:9px;font-size:11px}
.b2b-orders-toolbar .button{min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:8px;white-space:nowrap}
.b2b-orders-export{min-width:126px}
.b2b-orders-search-button{min-width:104px}
@media(max-width:760px){.b2b-orders-toolbar{grid-template-columns:minmax(0,1fr);gap:12px;padding:13px}.b2b-orders-toolbar-actions{display:grid;grid-template-columns:auto 1fr 1fr;gap:8px}.b2b-orders-export,.b2b-orders-search-button{min-width:0;width:100%}}
</style>
