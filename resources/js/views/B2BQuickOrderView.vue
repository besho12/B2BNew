<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ArrowRight, Check, CircleAlert, FileUp, ListPlus, PackageSearch, Search, ShoppingCart, Trash2 } from '@lucide/vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'
import { useB2BCartStore } from '../stores/b2bCart'

type ProductPreview = { sku: string; name: string; image: string; category: string; price_minor: number; case_quantity: number; stock_quantity: number | null; stock_status: string; allow_backorder: boolean }
type OrderLine = { id: number; sku: string; quantity: number; product: ProductPreview | null; loading: boolean; error: string; requestVersion: number }
const auth = useAuthStore()
const cart = useB2BCartStore()
const router = useRouter()
const DEFAULT_LINE_COUNT = 10
const nextId = ref(DEFAULT_LINE_COUNT + 1)
const lines = ref<OrderLine[]>(Array.from({ length: DEFAULT_LINE_COUNT }, (_, index) => makeLine(index + 1)))
const working = ref(false)
const importing = ref(false)
const notice = ref('')
const error = ref('')
const importInput = ref<HTMLInputElement | null>(null)
const accountDraft = ref(auth.selectedPortalAccount || '')
const account = computed(() => auth.portalAccounts.find(item => item.code === auth.selectedPortalAccount) || null)
const activeLines = computed(() => lines.value.filter(line => line.sku.trim()))
const readyCount = computed(() => activeLines.value.filter(line => line.product && !line.error).length)
const estimatedTotal = computed(() => activeLines.value.reduce((sum, line) => sum + (line.product?.price_minor || 0) * (Number(line.quantity) || 0), 0))
const quickOrderScope = computed(() => `${auth.bootstrap?.user.id || 'guest'}_${auth.bootstrap?.company.id || 'company'}_${auth.selectedPortalAccount || 'none'}`)
watch(quickOrderScope, value => { void cart.load(value) }, { immediate: true })
watch(() => auth.selectedPortalAccount, value => { accountDraft.value = value || '' })

function makeLine(id = nextId.value++): OrderLine { return { id, sku: '', quantity: 1, product: null, loading: false, error: '', requestVersion: 0 } }
function formatMoney(minor: number) { return new Intl.NumberFormat(auth.locale === 'ar' ? 'ar-EG' : 'en-EG', { style: 'currency', currency: 'EGP' }).format(minor / 100) }
function updateSku(line: OrderLine) { line.product = null; line.loading = false; line.error = ''; line.requestVersion++ }

async function lookup(line: OrderLine) {
  const sku = line.sku.trim()
  if (!sku) { line.error = 'Enter a product code first.'; return }
  const duplicate = lines.value.find(other => other.id !== line.id && other.sku.trim().toLowerCase() === sku.toLowerCase())
  if (duplicate) { line.error = `This product is already listed on line ${lines.value.indexOf(duplicate) + 1}.`; return }
  const version = ++line.requestVersion
  line.loading = true; line.error = ''; line.product = null
  try {
    const { data } = await api.get<ProductPreview>(`/portal/products/${encodeURIComponent(sku)}`)
    if (line.requestVersion !== version) return
    line.product = data
    if (line.quantity < data.case_quantity) line.quantity = data.case_quantity
  } catch (exception: any) {
    if (line.requestVersion === version) line.error = exception.response?.data?.message || `Could not find product ${sku}.`
  } finally { if (line.requestVersion === version) line.loading = false }
}

function addLine() {
  if (lines.value.length >= 50) { error.value = 'A quick order can include up to 50 lines.'; return }
  lines.value.push(makeLine())
  error.value = ''
}
function removeLine(id: number) {
  lines.value = lines.value.filter(line => line.id !== id)
  if (!lines.value.length) lines.value = Array.from({ length: DEFAULT_LINE_COUNT }, () => makeLine())
}
function clearLines() { lines.value = Array.from({ length: DEFAULT_LINE_COUNT }, () => makeLine()); error.value = ''; notice.value = '' }

async function addToBasket() {
  error.value = ''; notice.value = ''
  if (!activeLines.value.length) { error.value = 'Add at least one product code to continue.'; return }
  const repeatedSku = activeLines.value.find((line, index) => activeLines.value.findIndex(other => other.sku.trim().toLowerCase() === line.sku.trim().toLowerCase()) !== index)
  if (repeatedSku) { error.value = `${repeatedSku.sku} appears more than once. Combine its quantity on one line.`; return }
  if (activeLines.value.some(line => !Number.isInteger(Number(line.quantity)) || Number(line.quantity) < 1)) { error.value = 'Enter a whole quantity of at least one for each line.'; return }

  working.value = true
  try {
    await cart.quickOrder(activeLines.value.map(line => ({ sku: line.sku.trim(), quantity: Number(line.quantity) })))
    notice.value = `${activeLines.value.length} product line${activeLines.value.length === 1 ? '' : 's'} added to your basket. No order has been placed.`
    lines.value = Array.from({ length: DEFAULT_LINE_COUNT }, () => makeLine())
    error.value = ''
  } catch (exception: any) {
    error.value = cart.error || exception.response?.data?.message || 'Some items could not be added. Review the quantities and try again.'
  } finally { working.value = false }
}

function parseCsv(text: string): string[][] {
  const rows: string[][] = []
  let row: string[] = []; let value = ''; let quoted = false
  for (let index = 0; index < text.length; index++) {
    const character = text[index]
    if (quoted) {
      if (character === '"' && text[index + 1] === '"') { value += '"'; index++ }
      else if (character === '"') quoted = false
      else value += character
    } else if (character === '"' && value.length === 0) quoted = true
    else if (character === ',') { row.push(value.trim()); value = '' }
    else if (character === '\n' || character === '\r') {
      if (character === '\r' && text[index + 1] === '\n') index++
      row.push(value.trim()); value = ''
      if (row.some(cell => cell !== '')) rows.push(row)
      row = []
    } else value += character
  }
  row.push(value.trim())
  if (row.some(cell => cell !== '')) rows.push(row)
  return rows
}

function applyImportedRows(rows: string[][], format: 'CSV' | 'XLSX') {
  if (!rows.length) { error.value = `The ${format} file has no order lines.`; return }
  const normalize = (value: string) => value.trim().toLowerCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ')
  const first = rows[0].map(normalize)
  const skuHeaders = ['sku', 'itemno', 'item no', 'item number', 'part no', 'product code', 'product sku']
  const quantityHeaders = ['quantity', 'qty', 'qty required', 'quantity required', 'order quantity']
  const skuColumn = first.findIndex(value => skuHeaders.includes(value))
  const quantityColumn = first.findIndex(value => quantityHeaders.includes(value))
  const hasHeader = skuColumn >= 0 && quantityColumn >= 0
  const dataRows = hasHeader ? rows.slice(1) : rows
  const linesToImport: Array<{ sku: string; quantity: number }> = []
  const problems: string[] = []
  dataRows.forEach((row, index) => {
    const sku = (row[hasHeader ? skuColumn : 0] || '').trim()
    const quantityText = (row[hasHeader ? quantityColumn : 1] || '').trim()
    const quantity = Number(quantityText)
    if (!sku || !Number.isInteger(quantity) || quantity < 1) problems.push(`Row ${index + (hasHeader ? 2 : 1)} needs a product code and a positive whole quantity.`)
    else linesToImport.push({ sku, quantity })
  })
  if (problems.length) { error.value = problems.slice(0, 3).join(' '); return }
  if (!linesToImport.length || linesToImport.length > 50) { error.value = `The ${format} must contain between 1 and 50 order lines.`; return }
  const seen = new Set<string>()
  for (const item of linesToImport) {
    const normalized = item.sku.toLowerCase()
    if (seen.has(normalized)) { error.value = `The ${format} contains ${item.sku} more than once. Combine it into one line.`; return }
    seen.add(normalized)
  }
  nextId.value = linesToImport.length + 1
  lines.value = linesToImport.map((item, index) => ({ ...makeLine(index + 1), sku: item.sku, quantity: item.quantity }))
  while (lines.value.length < DEFAULT_LINE_COUNT) lines.value.push(makeLine())
  notice.value = `${linesToImport.length} lines imported. Add them to your basket when ready.`
}

function spreadsheetCellText(cell: { value: unknown; text: string }): string {
  const value: any = cell.value
  if (value == null) return ''
  if (value instanceof Date) return value.toISOString()
  if (typeof value === 'object') {
    if ('result' in value) return String(value.result ?? '').trim()
    if ('text' in value) return String(value.text ?? '').trim()
    if (Array.isArray(value.richText)) return value.richText.map((part: any) => part.text || '').join('').trim()
    return String(cell.text || '').trim()
  }
  return String(value).trim()
}

async function importFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  error.value = ''; notice.value = ''
  const extension = file.name.split('.').pop()?.toLowerCase()
  if (!['csv', 'xlsx'].includes(extension || '')) { error.value = 'Import a CSV or XLSX file with SKU and Quantity columns. Save legacy XLS files as XLSX first.'; return }
  if (file.size > 5_000_000) { error.value = 'Order files must be smaller than 5 MB.'; return }
  importing.value = true
  try {
    if (extension === 'csv') {
      applyImportedRows(parseCsv((await file.text()).replace(/^\uFEFF/, '')), 'CSV')
      return
    }
    const ExcelJS = await import('exceljs')
    const workbook = new ExcelJS.Workbook()
    await workbook.xlsx.load(await file.arrayBuffer())
    const worksheet = workbook.worksheets.find(sheet => sheet.actualRowCount > 0)
    if (!worksheet) { error.value = 'The XLSX workbook has no order lines.'; return }
    if (worksheet.actualRowCount > 51) { error.value = 'The first worksheet may contain at most 50 order lines plus a header row.'; return }
    const rows: string[][] = []
    worksheet.eachRow({ includeEmpty: false }, row => {
      const values: string[] = []
      row.eachCell({ includeEmpty: true }, (cell, column) => { values[column - 1] = spreadsheetCellText(cell) })
      rows.push(values)
    })
    applyImportedRows(rows, 'XLSX')
  } catch { error.value = extension === 'csv' ? 'The CSV file could not be read.' : 'The XLSX file could not be read. Check that it is a valid Excel workbook.' }
  finally { importing.value = false }
}

async function downloadXlsxTemplate() {
  try {
    const ExcelJS = await import('exceljs')
    const workbook = new ExcelJS.Workbook()
    const worksheet = workbook.addWorksheet('Quick order')
    worksheet.columns = [{ header: 'SKU', key: 'sku', width: 24 }, { header: 'Quantity', key: 'quantity', width: 14 }]
    worksheet.addRow({ sku: 'DEMO-001', quantity: 2 })
    worksheet.getRow(1).font = { bold: true }
    worksheet.getColumn('sku').numFmt = '@'
    const buffer = await workbook.xlsx.writeBuffer()
    const url = URL.createObjectURL(new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
    const link = document.createElement('a'); link.href = url; link.download = 'quick-order-template.xlsx'; link.click()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch { error.value = 'The XLSX template could not be created.' }
}

async function changeAccount() {
  if (!accountDraft.value || accountDraft.value === auth.selectedPortalAccount) return
  error.value = ''; notice.value = ''; lines.value = Array.from({ length: DEFAULT_LINE_COUNT }, () => makeLine())
  try { await auth.selectPortalAccount(accountDraft.value) }
  catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to switch customer account.' }
}
</script>

<template>
  <div class="quick-order-page">
    <header class="page-heading quick-order-heading">
      <div><span class="eyebrow">B2B CATALOG</span><h1>Quick order</h1><p>Enter product codes and quantities to add multiple items to your basket at once.</p></div>
      <AppSelect v-if="auth.portalAccounts.length > 1" v-model="accountDraft" class="b2b-account-switch" aria-label="Customer account" @change="changeAccount"><option v-for="item in auth.portalAccounts" :key="item.code" :value="item.code">{{ item.code }} · {{ item.name }}</option></AppSelect>
      <span v-else class="b2b-current-account"><PackageSearch :size="16"/> {{ account?.code || (auth.portalDemoMode ? 'DEMO ACCOUNT' : 'Customer account') }}</span>
    </header>

    <div v-if="error" class="form-error quick-order-message" role="alert"><CircleAlert :size="16"/>{{ error }}</div>
    <div v-if="notice" class="quick-order-notice" role="status"><Check :size="16"/>{{ notice }}<RouterLink v-if="cart.items.length" to="/cart">View basket <ArrowRight :size="13"/></RouterLink></div>

    <section class="data-panel quick-order-panel">
      <div class="quick-order-toolbar">
        <div><span class="eyebrow">MULTI-ITEM ENTRY</span><h2>Order lines</h2><p>Enter a product code per row or import a CSV/XLSX file. Spreadsheet imports read the first worksheet; keep product codes as text. Save older XLS files as XLSX first. Prices and pack rules are checked by the server.</p></div>
        <div class="quick-order-tools">
          <input ref="importInput" class="quick-order-file" type="file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" aria-label="Import quick order CSV or XLSX file" @change="importFile">
          <button class="button secondary" type="button" :disabled="importing" @click="importInput?.click()"><FileUp :size="15"/> {{ importing ? 'Reading file…' : 'Import CSV / XLSX' }}</button>
          <button class="button subtle" type="button" @click="downloadXlsxTemplate">XLSX template</button>
        </div>
      </div>

      <div class="quick-order-table-head"><span>#</span><span>PRODUCT CODE</span><span>PRODUCT</span><span>QUANTITY</span><span>UNIT PRICE</span><span>LINE TOTAL</span><span></span></div>
      <article v-for="(line, index) in lines" :key="line.id" class="quick-order-line">
        <span class="quick-order-row-number" :aria-label="`Row ${index + 1}`">{{ index + 1 }}</span>
        <div class="quick-order-sku"><label class="sr-only" :for="`quick-order-sku-${line.id}`">Product code, line {{ index + 1 }}</label><input :id="`quick-order-sku-${line.id}`" v-model="line.sku" autocomplete="off" placeholder="Enter SKU or item number" @input="updateSku(line)" @keydown.enter.prevent="lookup(line)"><button class="quick-order-lookup" type="button" :disabled="line.loading || !line.sku.trim()" :aria-label="`Find product for line ${index + 1}`" @click="lookup(line)"><Search v-if="!line.loading" :size="15"/><span v-else class="quick-order-spinner"/></button></div>
        <div class="quick-order-product"><template v-if="line.product"><img v-if="line.product.image" :src="line.product.image" alt=""/><span class="quick-order-product-copy"><strong>{{ line.product.name }}</strong><small>{{ line.product.category || line.product.sku }} · {{ line.product.stock_status }} · Pack of {{ line.product.case_quantity }}</small></span></template><span v-else class="quick-order-placeholder">{{ line.loading ? 'Checking product…' : 'Product details appear after lookup' }}</span><small v-if="line.error" class="quick-order-line-error" role="alert">{{ line.error }}</small></div>
        <label class="quick-order-quantity"><span class="sr-only">Quantity for {{ line.product?.name || line.sku || ('line ' + (index + 1)) }}</span><input v-model.number="line.quantity" type="number" min="1" :step="line.product?.case_quantity || 1" :aria-label="'Quantity for ' + (line.product?.name || line.sku || ('line ' + (index + 1)))"></label>
        <strong class="quick-order-unit-price">{{ line.product ? formatMoney(line.product.price_minor) : '—' }}</strong>
        <strong class="quick-order-line-total">{{ line.product ? formatMoney(line.product.price_minor * (Number(line.quantity) || 0)) : '—' }}</strong>
        <button class="icon-button quick-order-remove" type="button" :aria-label="`Remove order line ${index + 1}`" @click="removeLine(line.id)"><Trash2 :size="16"/></button>
      </article>

      <footer class="quick-order-footer">
        <div class="quick-order-row-actions"><button class="button secondary" type="button" :disabled="lines.length >= 50" @click="addLine"><ListPlus :size="15"/> Add line</button><button class="button subtle" type="button" @click="clearLines"><Trash2 :size="14"/> Clear lines</button></div>
        <div class="quick-order-total"><span>Estimated total for looked-up lines</span><strong>{{ formatMoney(estimatedTotal) }}</strong></div>
      </footer>
    </section>

    <aside class="quick-order-submit"><p><strong>{{ activeLines.length }}</strong> line{{ activeLines.length === 1 ? '' : 's' }} entered<span v-if="readyCount"> · {{ readyCount }} looked up</span></p><button class="button primary" type="button" :disabled="working || !activeLines.length" @click="addToBasket"><ShoppingCart v-if="!working" :size="16"/><span v-else class="quick-order-spinner quick-order-spinner-light"/>{{ working ? 'Adding items…' : 'Add items to basket' }}</button></aside>
    <p class="quick-order-disclaimer">Adding items updates your account-specific basket only. No order or payment is submitted.</p>
  </div>
</template>

<style scoped>
.quick-order-heading{align-items:center}.quick-order-panel{overflow:hidden;border:1px solid var(--line);border-radius:12px;background:#fff}.quick-order-toolbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:20px;border-bottom:1px solid var(--line)}.quick-order-toolbar h2{margin:4px 0;color:#293f35;font-size:16px}.quick-order-toolbar p{margin:5px 0 0;color:var(--muted);font-size:10px;line-height:1.5}.quick-order-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.quick-order-tools .button,.quick-order-row-actions .button{display:inline-flex;align-items:center;gap:7px}.quick-order-file{display:none}.quick-order-table-head,.quick-order-line{display:grid;grid-template-columns:minmax(150px,1.1fr) minmax(190px,1.7fr) minmax(88px,.55fr) minmax(85px,.55fr) minmax(90px,.6fr) 32px;align-items:center;gap:12px}.quick-order-table-head{padding:11px 17px;background:#f8faf8;color:#849088;font-size:8px;font-weight:700;letter-spacing:.08em}.quick-order-line{min-height:83px;padding:12px 17px;border-bottom:1px solid #eef1ee}.quick-order-line:last-of-type{border-bottom:0}.quick-order-sku{display:flex;align-items:center;min-width:0}.quick-order-sku input,.quick-order-quantity input{width:100%;height:36px;padding:0 10px;border:1px solid #dfe5df;border-radius:7px;background:#fff;color:#354a3f;font-size:10px;outline:none}.quick-order-sku input:focus,.quick-order-quantity input:focus{border-color:#88a593;box-shadow:0 0 0 3px #5e856c18}.quick-order-lookup{width:34px;height:34px;display:grid;place-items:center;flex:none;margin-left:-37px;border:0;border-radius:6px;background:#fff;color:#63816f;cursor:pointer}.quick-order-lookup:disabled{opacity:.45;cursor:default}.quick-order-product{display:flex;align-items:center;gap:10px;min-width:0}.quick-order-product>img{width:42px;height:42px;flex:none;object-fit:cover;border-radius:7px;background:#f2f5f2}.quick-order-product-copy{display:grid;gap:4px;min-width:0}.quick-order-product-copy strong{overflow:hidden;color:#31473c;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.quick-order-product-copy small,.quick-order-placeholder{color:#849088;font-size:9px;line-height:1.4}.quick-order-line-error{color:#a54840;font-size:9px}.quick-order-quantity input{text-align:center}.quick-order-unit-price,.quick-order-line-total{color:#43584c;font-size:10px;white-space:nowrap}.quick-order-line-total{font-weight:700}.quick-order-remove{color:#87938c}.quick-order-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px 17px;border-top:1px solid var(--line);background:#fcfdfb}.quick-order-row-actions{display:flex;gap:7px;flex-wrap:wrap}.quick-order-total{display:grid;gap:4px;text-align:right}.quick-order-total span{color:#79867f;font-size:9px}.quick-order-total strong{color:#2d4438;font-size:15px}.quick-order-submit{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:15px;padding:16px 18px;border:1px solid #e6ebe6;border-radius:10px;background:#fff}.quick-order-submit p{margin:0;color:#718077;font-size:10px}.quick-order-submit p strong{color:#30463b}.quick-order-submit .button{display:inline-flex;align-items:center;justify-content:center;gap:8px}.quick-order-submit .button:disabled{opacity:.55;cursor:not-allowed}.quick-order-disclaimer{margin:9px 2px 0;color:#89948d;font-size:9px}.quick-order-message,.quick-order-notice{display:flex;align-items:center;gap:8px;margin-bottom:12px;padding:11px 13px;border-radius:8px;font-size:10px}.quick-order-notice{border:1px solid #dbe9dd;background:#f1f8f2;color:#426c50}.quick-order-notice a{display:inline-flex;align-items:center;gap:5px;margin-left:auto;color:inherit;font-weight:700;text-decoration:none}.quick-order-spinner{width:14px;height:14px;border:2px solid #d8e2da;border-top-color:#557965;border-radius:50%;animation:quick-order-spin .7s linear infinite}.quick-order-spinner-light{border-color:#ffffff55;border-top-color:#fff}@keyframes quick-order-spin{to{transform:rotate(360deg)}}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}@media(max-width:1050px){.quick-order-table-head,.quick-order-line{grid-template-columns:minmax(130px,1.1fr) minmax(160px,1.5fr) minmax(76px,.55fr) minmax(70px,.55fr) minmax(78px,.6fr) 28px;gap:8px;padding-inline:12px}.quick-order-product>img{width:34px;height:34px}}@media(max-width:760px){.quick-order-heading{align-items:flex-start;flex-direction:column}.quick-order-toolbar{align-items:flex-start;flex-direction:column}.quick-order-table-head{display:none}.quick-order-line{grid-template-columns:minmax(0,1fr) 34px;gap:10px;padding:13px}.quick-order-sku{grid-column:1;grid-row:1}.quick-order-product{grid-column:1/-1;grid-row:2;min-height:38px}.quick-order-quantity{grid-column:1;grid-row:3;width:110px}.quick-order-unit-price,.quick-order-line-total{grid-row:4}.quick-order-unit-price{grid-column:1}.quick-order-line-total{grid-column:1;grid-row:5}.quick-order-remove{grid-column:2;grid-row:1}.quick-order-footer,.quick-order-submit{align-items:flex-start;flex-direction:column}.quick-order-total{text-align:left}.quick-order-submit .button{width:100%}.quick-order-tools{width:100%}}@media(prefers-reduced-motion:reduce){.quick-order-spinner{animation:none}}
.quick-order-table-head,.quick-order-line{grid-template-columns:30px minmax(150px,1.1fr) minmax(190px,1.7fr) minmax(88px,.55fr) minmax(85px,.55fr) minmax(90px,.6fr) 32px}
.quick-order-row-number{color:#8a968f;font-size:9px;font-weight:700;text-align:center}
@media(max-width:1050px){.quick-order-table-head,.quick-order-line{grid-template-columns:28px minmax(125px,1.1fr) minmax(150px,1.5fr) minmax(76px,.55fr) minmax(70px,.55fr) minmax(78px,.6fr) 28px}}
@media(max-width:760px){.quick-order-line{grid-template-columns:24px minmax(0,1fr) 34px}.quick-order-row-number{grid-column:1;grid-row:1;align-self:start;padding-top:10px}.quick-order-sku{grid-column:2;grid-row:1}.quick-order-product{grid-column:2/-1;grid-row:2}.quick-order-quantity{grid-column:2;grid-row:3}.quick-order-unit-price,.quick-order-line-total{grid-column:2;grid-row:4}.quick-order-line-total{grid-row:5}.quick-order-remove{grid-column:3;grid-row:1}}
</style>
