<script setup lang="ts">
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue'
import type { PropType } from 'vue'
import { ArrowDown, Eye, PackageSearch, RefreshCw, Search, ShoppingCart, Star } from '@lucide/vue'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'
import { useB2BCartStore } from '../stores/b2bCart'
import Modal from '../components/Modal.vue'

type PortalAccount = { code: string; name: string }
type FilterNode = { code: string; name: string; children: FilterNode[] }
type ProductResponse = { data?: unknown; number_current_products?: number; number_all_products?: number; max_id?: number; backOrder?: string | number }
const auth = useAuthStore(); const cart = useB2BCartStore(); const result = ref<ProductResponse>({}); const filters = ref<any[]>([]); const search = ref(''); const selectedCategories = ref<string[]>([]); const loading = ref(true); const loadingMore = ref(false); const error = ref(''); const favorite = ref(false); const promotion = ref(false)
let catalogRequestVersion = 0
const keepSkeletonVisible = (milliseconds: number) => new Promise(resolve => window.setTimeout(resolve, milliseconds))
const account = computed<PortalAccount | null>(() => auth.portalAccounts.find(item => item.code === auth.selectedPortalAccount) || null)
const accountDraft = ref('')
const selectedProduct = ref<any | null>(null); const detailQuantity = ref(1); const cartNotice = ref('')
const cartScope = computed(() => `${auth.bootstrap?.user.id || 'guest'}_${auth.bootstrap?.company.id || 'company'}_${auth.selectedPortalAccount || 'default'}`)
watch(cartScope, value => { void cart.load(value) }, { immediate: true })
const demoProducts = [
  { id: 'DEMO-001', name: 'Nano Bike Wash', category: 'Cleaning', price: 12.5, currency: 'EGP', image: '/images/demo-products/product-01.svg', stock_status: 'In stock', favorite: true, promotion: true },
  { id: 'DEMO-002', name: 'Wet Chain Lube', category: 'Lubrication', price: 8.75, currency: 'EGP', image: '/images/demo-products/product-02.svg', caseQuantity: 2, stock_status: 'In stock', favorite: false, promotion: true },
  { id: 'DEMO-003', name: 'Dry Chain Lube', category: 'Lubrication', price: 9.25, currency: 'EGP', image: '/images/demo-products/product-03.svg', stock_status: 'Low stock', favorite: true, promotion: false },
  { id: 'DEMO-004', name: 'Bio Degreaser', category: 'Cleaning', price: 11.0, currency: 'EGP', image: '/images/demo-products/product-04.svg', stock_status: 'In stock', favorite: false, promotion: false },
  { id: 'DEMO-005', name: 'Foam Bike Cleaner', category: 'Cleaning', price: 14.5, currency: 'EGP', image: '/images/demo-products/product-05.svg', stock_status: 'In stock', favorite: false, promotion: true },
  { id: 'DEMO-006', name: 'Tubeless Tyre Sealant', category: 'Workshop', price: 16.0, currency: 'EGP', image: '/images/demo-products/product-06.svg', caseQuantity: 3, stock_status: 'In stock', favorite: true, promotion: false },
  { id: 'DEMO-007', name: 'Disc Brake Cleaner', category: 'Cleaning', price: 7.5, currency: 'EGP', image: '/images/demo-products/product-07.svg', stock_status: 'In stock', favorite: false, promotion: false },
  { id: 'DEMO-008', name: 'Chain Cleaning Brush', category: 'Tools', price: 6.25, currency: 'EGP', image: '/images/demo-products/product-08.svg', stock_status: 'Low stock', favorite: true, promotion: true },
  { id: 'DEMO-009', name: 'Microfiber Cloth', category: 'Accessories', price: 4.0, currency: 'EGP', image: '/images/demo-products/product-09.svg', caseQuantity: 5, stock_status: 'In stock', favorite: false, promotion: false },
  { id: 'DEMO-010', name: 'Workshop Cleaning Kit', category: 'Workshop', price: 24.0, currency: 'EGP', image: '/images/demo-products/product-10.svg', stock_status: 'In stock', favorite: false, promotion: true },
]
const demoCategories: FilterNode[] = [
  { code: 'Cleaning', name: 'Cleaning', children: [{ code: 'Cleaning', name: 'Bike cleaners', children: [] }, { code: 'Lubrication', name: 'Lubricants', children: [] }] },
  { code: 'Workshop', name: 'Workshop', children: [{ code: 'Tools', name: 'Tools', children: [] }, { code: 'Accessories', name: 'Accessories', children: [] }] },
]
const demoFavorites = ref(new Set(demoProducts.filter(item => item.favorite).map(item => item.id)))
const favoriteOverrides = ref<Record<string, boolean>>({})
const favoritePending = ref(new Set<string>())
async function quickView(item: any) {
  const sku = String(item.id ?? itemCode(item))
  if (!auth.portalDemoMode) {
    try {
      const { data } = await api.get(`/portal/products/${encodeURIComponent(sku)}`)
      item = { ...item, id: data.sku || sku, name: data.name || itemTitle(item), category: data.category || '', price: Number(data.price_minor ?? 0) / 100, currency: data.currency || 'EGP', image: data.image || itemImage(item), caseQuantity: Number(data.case_quantity || 1), stock_status: data.stock_status || 'Stock checked when adding' }
    } catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to load product details.'; return }
  }
  selectedProduct.value = item; detailQuantity.value = Math.max(1, item.caseQuantity || 1)
}
async function addToCart(item: any, quantity = item.caseQuantity || 1) {
  try {
    await cart.add(item.id ?? itemCode(item), quantity)
    cartNotice.value = `${item.name} added to your basket.`
    window.setTimeout(() => { cartNotice.value = '' }, 2600)
  } catch { error.value = cart.error }
}
async function toggleFavorite(item: any) {
  const sku = String(item.id ?? itemCode(item))
  if (favoritePending.value.has(sku)) return
  favoritePending.value = new Set(favoritePending.value).add(sku)
  if (auth.portalDemoMode) {
    const next = new Set(demoFavorites.value)
    if (next.has(sku)) next.delete(sku); else next.add(sku)
    demoFavorites.value = next
    await load(true)
    favoritePending.value = new Set([...favoritePending.value].filter(value => value !== sku))
    return
  }
  try {
    await api.post(`/portal/products/${encodeURIComponent(sku)}/favorite`)
    const key = `${auth.selectedPortalAccount || ''}:${sku}`
    favoriteOverrides.value = { ...favoriteOverrides.value, [key]: !Boolean(favoriteOverrides.value[key] ?? item.favorite) }
    await load(true)
  } catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to update this favorite.' }
  finally { favoritePending.value = new Set([...favoritePending.value].filter(value => value !== sku)) }
}
async function changeAccount() { if (!accountDraft.value || accountDraft.value === auth.selectedPortalAccount) return; catalogRequestVersion++; loading.value = true; loadingMore.value = false; try { await auth.selectPortalAccount(accountDraft.value); result.value = {}; await loadFilters(); await load(true) } catch (exception: any) { error.value = exception.response?.data?.message || 'Unable to switch customer account.'; loading.value = false } }
const htmlResult = computed(() => typeof result.value.data === 'string' ? result.value.data : '')
const legacyItems = computed<any[]>(() => {
  if (!htmlResult.value) return []
  const doc = new DOMParser().parseFromString(htmlResult.value, 'text/html')
  return Array.from(doc.querySelectorAll<HTMLElement>('.add_to_basket_btn')).map((button) => {
    const card = button.closest('.product, .product_item, .item, article, [class*="product"]') || button.parentElement
    const data = button.dataset
    const id = data.itemno || data.itemNo || ''
    return { id, name: data.description || card?.querySelector('.product_name, .product_title, h3, h4')?.textContent?.trim() || id || 'Product', price: Number(String(data.price || '0').replace(/[^0-9.]/g, '')) || 0, currency: 'EGP', image: data.img || card?.querySelector('img')?.getAttribute('src') || '', caseQuantity: Math.max(1, Number(data.caseqty || 1)), stock_status: 'Stock checked when adding', favorite: favoriteOverrides.value[`${auth.selectedPortalAccount || ''}:${id}`] ?? false }
  }).filter((item) => item.id)
})
const structuredItems = computed<any[]>(() => Array.isArray(result.value.data) ? result.value.data : [])
const shownCount = computed(() => typeof result.value.data === 'string' ? legacyItems.value.length : structuredItems.value.length)
const totalCount = computed(() => Number(result.value.number_all_products ?? 0))
const canLoadMore = computed(() => !auth.portalDemoMode && Boolean(result.value.max_id) && (totalCount.value === 0 || shownCount.value < totalCount.value))
function filterLabel(item: any) { return String(item.name ?? item.label ?? item.category_name ?? item.title ?? item.value ?? item.id ?? '') }
function filterValue(item: any) { return String(item.id ?? item.code ?? item.value ?? item.name ?? item.label ?? '') }
function normalizeFilters(rows: any[]): FilterNode[] { return rows.map(item => { const children = item.children ?? item.items ?? item.subcategories ?? item.categories ?? item.filters ?? []; return { code: filterValue(item), name: filterLabel(item), children: Array.isArray(children) ? normalizeFilters(children) : [] } }).filter(item => item.code && item.name) }
const filterOptions = computed<FilterNode[]>(() => { if (auth.portalDemoMode) return demoCategories; const value = filters.value as any; const rows = Array.isArray(value) ? value : (value?.data || value?.categories || value?.filters || []); return Array.isArray(rows) ? normalizeFilters(rows) : [] })
let FilterBranch: any
FilterBranch = defineComponent({ name: 'CatalogFilterBranch', props: { nodes: { type: Array as PropType<FilterNode[]>, required: true }, selected: { type: Array as PropType<string[]>, required: true } }, emits: ['toggle'], setup(props, { emit }) { return () => h('ul', { class: 'b2b-filter-tree' }, props.nodes.map(node => node.children.length
  ? h('li', { class: 'b2b-filter-group', key: node.code + node.name }, [h('details', { open: true }, [h('summary', node.name), h(FilterBranch, { nodes: node.children, selected: props.selected, onToggle: (code: string, checked: boolean) => emit('toggle', code, checked) })])])
  : h('li', { key: node.code + node.name }, [h('label', { class: 'b2b-filter-option' }, [h('input', { type: 'checkbox', value: node.code, checked: props.selected.includes(node.code), onChange: (event: Event) => emit('toggle', node.code, (event.target as HTMLInputElement).checked) }), h('span', node.name)])]
))) } })
function toggleCategory(code: string, checked: boolean) { const next = new Set(selectedCategories.value); if (checked) next.add(code); else next.delete(code); selectedCategories.value = [...next] }
function itemTitle(item: any) { return String(item.name ?? item.description ?? item.title ?? item.item_name ?? item.itemno ?? 'Product') }
function itemCode(item: any) { return String(item.itemno ?? item.code ?? item.sku ?? item.id ?? '') }
function itemPrice(item: any) { const value = item.price ?? item.unit_price ?? item.list_price; return value == null ? '' : `${item.currency ?? ''} ${value}`.trim() }
function itemImage(item: any) { return item.image_url ?? item.image ?? item.thumbnail ?? '' }
function mergeStructuredItems(current: any[], incoming: any[]) {
  const seen = new Set(current.map(item => itemCode(item)).filter(Boolean))
  return [...current, ...incoming.filter(item => { const code = itemCode(item); if (!code || seen.has(code)) return false; seen.add(code); return true })]
}
function appendUniqueMarkup(current: string, incoming: string) {
  const currentDoc = new DOMParser().parseFromString(current, 'text/html')
  const nextDoc = new DOMParser().parseFromString(incoming, 'text/html')
  const seen = new Set(Array.from(currentDoc.querySelectorAll<HTMLElement>('.add_to_basket_btn')).map(button => button.dataset.itemno || button.dataset.itemNo).filter(Boolean))
  for (const button of Array.from(nextDoc.querySelectorAll<HTMLElement>('.add_to_basket_btn'))) {
    const sku = button.dataset.itemno || button.dataset.itemNo
    if (!sku || seen.has(sku)) button.closest('.product, .product_item, .item, article, [class*="product"]')?.remove()
    else seen.add(sku)
  }
  return `${current}${nextDoc.body.innerHTML}`
}
async function load(reset = true) {
  if (reset) catalogRequestVersion++
  if (reset && loadingMore.value) loadingMore.value = false
  if (!reset && (loading.value || loadingMore.value || !canLoadMore.value)) return
  const requestVersion = catalogRequestVersion
  if (auth.portalDemoMode) {
    if (reset) { loading.value = true; result.value = {}; error.value = '' }
    else loadingMore.value = true
    await keepSkeletonVisible(reset ? 650 : 500)
    if (requestVersion !== catalogRequestVersion) return
    const query = search.value.trim().toLowerCase()
    const rows = demoProducts.map(item => ({ ...item, favorite: demoFavorites.value.has(item.id) })).filter(item => (!query || `${item.name} ${item.id} ${item.category}`.toLowerCase().includes(query)) && (!selectedCategories.value.length || selectedCategories.value.includes(item.category)) && (!favorite.value || item.favorite) && (!promotion.value || item.promotion))
    result.value = { data: rows, number_current_products: rows.length, number_all_products: rows.length, max_id: 0 }
    loading.value = false; loadingMore.value = false; error.value = ''
    return
  }
  if (reset) { loading.value = true; result.value = {}; error.value = '' } else { loadingMore.value = true; error.value = '' }
  try {
    const [{ data }] = await Promise.all([
      api.post<ProductResponse>('/portal/products', { max_id: reset ? 0 : result.value.max_id || 0, search: search.value, favorites: favorite.value ? 1 : 0, promotions: promotion.value ? 1 : 0, category_list: selectedCategories.value }),
      keepSkeletonVisible(reset ? 650 : 500),
    ])
    if (requestVersion !== catalogRequestVersion) return
    if (!reset && typeof result.value.data === 'string' && typeof data.data === 'string') data.data = appendUniqueMarkup(result.value.data, data.data)
    else if (!reset && Array.isArray(result.value.data) && Array.isArray(data.data)) data.data = mergeStructuredItems(result.value.data, data.data)
    result.value = data
  } catch (exception: any) { if (requestVersion === catalogRequestVersion) error.value = exception.response?.data?.message || 'Unable to load products from the B2B service.' }
  finally { if (requestVersion === catalogRequestVersion) { loading.value = false; loadingMore.value = false } }
}
async function loadFilters() {
  if (auth.portalDemoMode) { filters.value = demoCategories; return }
  try {
    const { data } = await api.get('/portal/filters')
    const value = data.data || data.categories || data.filters || data
    if (typeof value === 'string') {
      const parsed = new DOMParser().parseFromString(value, 'text/html')
      const roots = Array.from(parsed.querySelectorAll('ul')).filter(list => !list.parentElement?.closest('ul'))
      const parseList = (list: Element): any[] => Array.from(list.children).filter(child => child.tagName.toLowerCase() === 'li').map(li => {
        const currentList = li.parentElement
        const input = Array.from(li.querySelectorAll<HTMLInputElement>('.item_filter_checkbox')).find(candidate => candidate.closest('ul') === currentList)
        const childLists = Array.from(li.children).filter(child => child.tagName.toLowerCase() === 'ul')
        const name = input?.closest('label')?.textContent?.trim() || input?.parentElement?.textContent?.trim() || li.childNodes[0]?.textContent?.trim() || ''
        return { code: input?.dataset.code || input?.value || '', name, children: childLists.flatMap(parseList) }
      }).filter(item => item.code || item.children.length)
      if (roots.length) filters.value = roots.flatMap(parseList)
      else filters.value = Array.from(parsed.querySelectorAll<HTMLInputElement>('.item_filter_checkbox')).map(input => ({ code: input.dataset.code || input.value, name: input.closest('label')?.textContent?.trim() || input.parentElement?.textContent?.trim() || input.dataset.code || input.value })).filter(item => item.code)
    } else filters.value = value
  } catch { filters.value = [] }
}
function submitSearch() { void load(true) }
watch([favorite, promotion, selectedCategories], () => { void load(true) })
onMounted(async () => { if (!auth.selectedPortalAccount) { await auth.loadPortalAccounts(); if (auth.portalAccountSelectionRequired) return } accountDraft.value = auth.selectedPortalAccount || ''; await loadFilters(); await load() })
</script>
<template>
  <section class="page-heading"><div><span class="eyebrow">B2B CATALOG <i v-if="auth.portalDemoMode" class="b2b-demo-badge">DEMO DATA · TEMPORARY</i></span><h1>Products</h1><p v-if="auth.portalDemoMode">Temporary sample products for layout testing.</p><p v-else>Browse the live product list for {{ account?.name || 'your selected customer account' }}.</p></div><AppSelect v-if="auth.portalAccounts.length > 1" v-model="accountDraft" class="b2b-account-switch" aria-label="Customer account" @change="changeAccount"><option v-for="item in auth.portalAccounts" :key="item.code" :value="item.code">{{ item.code }} · {{ item.name }}</option></AppSelect><span v-else class="b2b-current-account"><RefreshCw :size="16"/> {{ auth.portalDemoMode ? 'DEMO ACCOUNT' : (account?.code || 'Customer account') }}</span></section>
  <section class="data-panel b2b-catalog-panel"><form class="table-toolbar b2b-catalog-toolbar" @submit.prevent="submitSearch"><div class="search-box"><Search/><input v-model="search" placeholder="Search products" aria-label="Search products"></div><button class="button primary" :disabled="loading"><Search :size="15"/> Search</button><label class="b2b-toggle"><input v-model="favorite" type="checkbox"> Favorites</label><label class="b2b-toggle"><input v-model="promotion" type="checkbox"> Promotions</label></form>
    <div class="b2b-catalog-layout" :class="{ 'b2b-catalog-no-filters': !filterOptions.length }">
      <aside v-if="filterOptions.length" class="b2b-filter-panel" aria-label="Product categories"><div class="b2b-filter-heading"><strong>Categories</strong><button v-if="selectedCategories.length" type="button" @click="selectedCategories = []">Clear</button></div><FilterBranch :nodes="filterOptions" :selected="selectedCategories" @toggle="toggleCategory" /></aside>
      <div class="b2b-catalog-results">
    <div v-if="error" class="form-error b2b-catalog-error" role="alert">{{ error }} <button class="button secondary" @click="load()">Retry</button></div>
    <div v-if="loading && !structuredItems.length && !htmlResult" class="b2b-product-grid b2b-skeleton-grid" aria-label="Loading products" aria-busy="true"><article v-for="item in 8" :key="item" class="b2b-product-card b2b-product-skeleton"><div class="b2b-skeleton-image"></div><div class="b2b-skeleton-copy"><i></i><i></i><i></i><div><i></i><i></i></div></div></article></div>
    <div v-else-if="auth.portalDemoMode && structuredItems.length" class="b2b-product-grid"><article v-for="item in structuredItems" :key="item.id" class="b2b-product-card"><div class="b2b-product-image"><img :src="item.image" :alt="itemTitle(item)"></div><div class="b2b-product-copy"><small>{{ item.id }}</small><strong>{{ item.name }}</strong><span>{{ item.currency }} {{ item.price.toFixed(2) }}</span><span class="b2b-stock">{{ item.stock_status }}</span><div class="b2b-demo-actions"><button type="button" class="button secondary" @click="quickView(item)"><Eye :size="14"/> Details</button><button type="button" class="button primary" @click="addToCart(item)"><ShoppingCart :size="14"/> Add</button><button type="button" class="button secondary" :aria-label="item.favorite ? 'Remove favorite' : 'Add favorite'" :aria-pressed="item.favorite" :title="item.favorite ? 'Remove favorite' : 'Add favorite'" @click="toggleFavorite(item)"><Star :size="14"/> {{ item.favorite ? 'Saved' : 'Favorite' }}</button></div></div></article></div><div v-else-if="htmlResult && legacyItems.length" class="b2b-product-grid"><article v-for="item in legacyItems" :key="item.id" class="b2b-product-card"><div class="b2b-product-image"><img v-if="item.image" :src="item.image" :alt="item.name"><PackageSearch v-else/></div><div class="b2b-product-copy"><small>{{ item.id }}</small><strong>{{ item.name }}</strong><span>{{ item.currency }} {{ item.price.toFixed(2) }}</span><span class="b2b-stock">{{ item.stock_status }}</span><div class="b2b-demo-actions"><button type="button" class="button secondary" @click="quickView(item)"><Eye :size="14"/> Details</button><button type="button" class="button primary" @click="addToCart(item)"><ShoppingCart :size="14"/> Add</button><button type="button" class="button secondary" :aria-label="item.favorite ? 'Remove favorite' : 'Add favorite'" :aria-pressed="item.favorite" :title="item.favorite ? 'Remove favorite' : 'Add favorite'" @click="toggleFavorite(item)"><Star :size="14"/> {{ item.favorite ? 'Saved' : 'Favorite' }}</button></div></div></article></div><div v-else-if="htmlResult" class="b2b-legacy-products" aria-label="Product results"><iframe :srcdoc="`<!doctype html><html><head><meta name='viewport' content='width=device-width,initial-scale=1'><style>body{font:14px Arial,sans-serif;color:#30413b;margin:0;padding:14px;background:#fff}img{max-width:100%;height:auto}a{color:#365e4d}button,input{font:inherit}</style></head><body>${htmlResult}</body></html>`" title="Products returned by the connected catalog" sandbox="allow-same-origin"></iframe></div>
    <div v-else-if="structuredItems.length" class="b2b-product-grid"><article v-for="(item,index) in structuredItems" :key="itemCode(item)||index" class="b2b-product-card"><div class="b2b-product-image"><img v-if="itemImage(item)" :src="itemImage(item)" :alt="itemTitle(item)"><PackageSearch v-else/></div><div class="b2b-product-copy"><small v-if="itemCode(item)">{{ itemCode(item) }}</small><strong>{{ itemTitle(item) }}</strong><span v-if="itemPrice(item)">{{ itemPrice(item) }}</span><span v-if="item.stock_status || item.availability" class="b2b-stock">{{ item.stock_status || item.availability }}</span></div></article></div>
    <div v-else-if="!loading && !error" class="table-empty"><PackageSearch/><strong>No products found</strong><span>Change your search or filters and try again.</span></div>
    <div v-if="loadingMore" class="b2b-product-grid b2b-skeleton-grid b2b-more-skeleton" aria-label="Loading more products" aria-busy="true"><article v-for="item in 4" :key="item" class="b2b-product-card b2b-product-skeleton"><div class="b2b-skeleton-image"></div><div class="b2b-skeleton-copy"><i></i><i></i><i></i><div><i></i><i></i></div></div></article></div>
    <footer v-if="totalCount || canLoadMore" class="table-footer b2b-pagination-footer"><span>{{ shownCount }}<template v-if="totalCount"> of {{ totalCount }}</template> products</span><button v-if="canLoadMore" class="button secondary b2b-load-more" :disabled="loading || loadingMore" @click="load(false)"><RefreshCw v-if="loadingMore" class="b2b-loading-spin" :size="15"/><ArrowDown v-else :size="15"/> {{ loadingMore ? 'Loading next products' : 'Load more products' }}</button></footer>
      </div>
    </div>
  </section>
  <div v-if="cartNotice" class="b2b-cart-toast" role="status">{{ cartNotice }} <RouterLink to="/cart">View basket</RouterLink></div>
  <Modal v-if="selectedProduct" :title="selectedProduct.name" :description="`${selectedProduct.id} · ${selectedProduct.category}`" @close="selectedProduct = null"><div class="b2b-detail-view"><img :src="selectedProduct.image" :alt="selectedProduct.name"><div><span class="b2b-detail-stock">{{ selectedProduct.stock_status }}</span><p>A temporary catalog sample for reviewing product details and the basket flow.</p><strong>{{ selectedProduct.currency }} {{ selectedProduct.price.toFixed(2) }} <small>per unit</small></strong><label>Quantity<input v-model.number="detailQuantity" type="number" min="1" :step="selectedProduct.caseQuantity || 1"></label><small>Order quantity must follow the case size of {{ selectedProduct.caseQuantity || 1 }}.</small><button class="button primary" @click="addToCart(selectedProduct, detailQuantity); selectedProduct = null"><ShoppingCart :size="15"/> Add to basket</button></div></div></Modal>
</template>
<style scoped>
.b2b-catalog-panel{overflow:hidden}.b2b-demo-badge{display:inline-block;margin-left:8px;padding:4px 7px;border-radius:12px;background:#fff2d7;color:#86652b;font-size:8px;font-style:normal;letter-spacing:.04em}.b2b-account-switch{max-width:min(340px,100%);padding:9px 11px;border:1px solid var(--line);border-radius:7px;background:#fff;color:#51635f;font-size:11px}.b2b-current-account{display:inline-flex;align-items:center;gap:7px;color:#687873;font-size:10px}.b2b-catalog-toolbar{display:grid;grid-template-columns:minmax(260px,1fr) auto auto auto;align-items:center;justify-content:initial;gap:10px;padding:13px 18px;background:#fff}.b2b-catalog-toolbar .search-box{width:100%;min-width:0;height:44px;padding:0 13px;border-color:#dfe5df;border-radius:9px;background:#fbfcfa}.b2b-catalog-toolbar .search-box:focus-within{border-color:#9aafa2;box-shadow:0 0 0 3px rgba(76,119,109,.08)}.b2b-catalog-toolbar .button.primary{min-height:44px;padding:0 19px;border-radius:9px;white-space:nowrap}.b2b-toggle,.b2b-filter-list label{display:inline-flex;align-items:center;gap:8px;color:#62716c;font-size:10px}.b2b-toggle{min-height:44px;padding:0 13px;border:1px solid var(--line);border-radius:9px;background:#fff;font-weight:600;white-space:nowrap;cursor:pointer}.b2b-toggle:hover,.b2b-toggle:focus-within{border-color:#b7c7bd;background:#fbfcfa}.b2b-toggle input{width:15px;height:15px;margin:0;accent-color:var(--gold)}.b2b-filter-list{display:flex;flex-wrap:wrap;gap:13px;padding:12px 17px;border-bottom:1px solid var(--line)}.b2b-catalog-error{margin:14px}.b2b-legacy-products{height:min(70vh,800px);padding:12px}.b2b-legacy-products iframe{width:100%;height:100%;border:0}.b2b-product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;padding:17px}.b2b-product-card{overflow:hidden;border:1px solid var(--line);border-radius:10px;background:#fff}.b2b-product-image{height:155px;display:grid;place-items:center;background:#f7f8f6;color:#8c9992}.b2b-product-image img{max-width:100%;max-height:100%;object-fit:contain}.b2b-product-copy{display:grid;gap:6px;padding:13px}.b2b-product-copy small,.b2b-stock{color:#87938d;font-size:9px}.b2b-product-copy strong{color:#33443d;font-size:12px}.b2b-product-copy>span:not(.b2b-stock){font-size:11px;font-weight:700}.b2b-demo-actions{display:flex;gap:7px;margin-top:7px}.b2b-demo-actions .button{min-height:34px;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:0 10px;font-size:9px}.b2b-detail-view{display:grid;grid-template-columns:minmax(180px, .85fr) minmax(220px,1fr);gap:20px;align-items:center}.b2b-detail-view>img{width:100%;border-radius:10px;background:#f4f6f3}.b2b-detail-view>div{display:grid;gap:12px}.b2b-detail-view p{margin:0;color:var(--muted);font-size:11px;line-height:1.6}.b2b-detail-view strong{font-size:17px}.b2b-detail-view strong small,.b2b-detail-view>div>small{color:var(--muted);font-size:9px;font-weight:400}.b2b-detail-stock{color:#4f7965;font-size:10px;font-weight:700}.b2b-detail-view label{display:grid;gap:6px;color:#465b53;font-size:10px;font-weight:700}.b2b-detail-view input{height:40px;padding:8px 10px;border:1px solid var(--line);border-radius:8px}.b2b-detail-view .button{display:inline-flex;align-items:center;justify-content:center;gap:8px}.b2b-cart-toast{position:fixed;right:24px;bottom:24px;z-index:60;padding:13px 16px;border:1px solid #d5e4da;border-radius:10px;background:#fff;box-shadow:0 12px 34px rgba(20,43,35,.15);color:#365e4d;font-size:11px}.b2b-cart-toast a{margin-left:8px;color:#17342f;font-weight:700;text-decoration:underline}.b2b-stock{color:#4f7965}@media(max-width:860px){.b2b-catalog-toolbar{grid-template-columns:minmax(0,1fr) auto auto}.b2b-catalog-toolbar .search-box{grid-column:1/-1}}@media(max-width:560px){.b2b-catalog-toolbar{grid-template-columns:1fr 1fr;padding:13px}.b2b-catalog-toolbar .search-box,.b2b-catalog-toolbar .button.primary{grid-column:1/-1}.b2b-toggle{justify-content:center;padding:0 8px}}
.b2b-demo-actions .button{width:36px;min-width:36px;min-height:36px;flex:0 0 36px;display:inline-flex;align-items:center;justify-content:center;gap:0;padding:0;overflow:hidden;font-size:0}.b2b-demo-actions .button svg{width:15px;height:15px;flex:0 0 auto}.b2b-demo-actions .button[aria-pressed="true"]{color:#9a6b16;border-color:#dcb96b;background:#fff7e5}.b2b-demo-actions .button[aria-pressed="true"] svg{fill:#d9aa42}
.b2b-skeleton-grid{pointer-events:none}.b2b-product-skeleton{border-color:#d9e0da;box-shadow:0 2px 8px rgba(32,53,44,.035)}.b2b-skeleton-image,.b2b-skeleton-copy i{display:block;overflow:hidden;position:relative;background:linear-gradient(100deg,#dfe5df 18%,#f7f9f6 40%,#dfe5df 62%);background-size:220% 100%;animation:b2b-shimmer 1.15s ease-in-out infinite}.b2b-skeleton-image{height:155px}.b2b-skeleton-copy{display:grid;gap:10px;padding:14px}.b2b-skeleton-copy i{height:10px;border-radius:6px}.b2b-skeleton-copy i:nth-child(1){width:34%}.b2b-skeleton-copy i:nth-child(2){width:76%;height:14px}.b2b-skeleton-copy i:nth-child(3){width:47%}.b2b-skeleton-copy>div{display:flex;gap:8px;margin-top:3px}.b2b-skeleton-copy>div i{width:36px;height:36px;border-radius:8px}.b2b-more-skeleton{padding-top:0}.b2b-pagination-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px}.b2b-load-more{min-height:40px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:9px;padding:0 16px}.b2b-load-more:disabled{opacity:.65;cursor:wait}.b2b-loading-spin{animation:b2b-spin .9s linear infinite}@keyframes b2b-shimmer{to{background-position-x:-220%}}@keyframes b2b-spin{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.b2b-skeleton-image,.b2b-skeleton-copy i,.b2b-loading-spin{animation:none}}
.b2b-catalog-layout{display:grid;grid-template-columns:210px minmax(0,1fr);align-items:start}.b2b-catalog-layout.b2b-catalog-no-filters{grid-template-columns:minmax(0,1fr)}.b2b-filter-panel{position:sticky;top:12px;margin:15px 0 15px 15px;padding:14px;border:1px solid var(--line);border-radius:10px;background:#fbfcfa}.b2b-filter-heading{display:flex;align-items:center;justify-content:space-between;padding-bottom:10px;border-bottom:1px solid var(--line);color:#354a40;font-size:11px}.b2b-filter-heading button{border:0;background:transparent;color:#78877e;font-size:9px;cursor:pointer}.b2b-filter-tree{display:grid;gap:8px;margin:0;padding:11px 0 0;list-style:none}.b2b-filter-tree ul{padding:7px 0 0 14px}.b2b-filter-tree summary{padding:4px 0;color:#41554b;font-size:10px;font-weight:700;cursor:pointer}.b2b-filter-tree summary::marker{color:var(--portal-secondary)}.b2b-filter-option{display:flex;align-items:flex-start;gap:8px;color:#66756d;font-size:10px;line-height:1.5;cursor:pointer}.b2b-filter-option input{flex:none;width:14px;height:14px;margin:0;accent-color:var(--portal-primary)}.b2b-catalog-results{min-width:0}
@media(max-width:860px){.b2b-catalog-layout{grid-template-columns:180px minmax(0,1fr)}.b2b-filter-panel{margin-left:10px;padding:11px}.b2b-product-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;padding:12px}}
@media(max-width:640px){.b2b-catalog-layout{grid-template-columns:1fr}.b2b-filter-panel{position:static;margin:10px;padding:12px}.b2b-filter-tree{grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px}}
</style>
