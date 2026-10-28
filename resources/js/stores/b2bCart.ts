import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '../api'

export type CartLine = { sku: string; name: string; image: string; category: string; price_minor: number; case_quantity: number; stock_status: string; quantity: number }
type CartPayload = { items: CartLine[]; item_count: number; subtotal_minor: number }

export const useB2BCartStore = defineStore('b2b-cart', () => {
  const items = ref<CartLine[]>([])
  const scope = ref('')
  const loading = ref(false)
  const error = ref('')
  let loadGeneration = 0
  const itemCount = computed(() => items.value.reduce((total, item) => total + item.quantity, 0))
  const subtotalMinor = computed(() => items.value.reduce((total, item) => total + item.price_minor * item.quantity, 0))

  function apply(payload: CartPayload) { items.value = payload.items || [] }
  async function load(value = scope.value) {
    const generation = ++loadGeneration
    if (value) scope.value = value
    loading.value = true; error.value = ''
    try {
      const { data } = await api.get<CartPayload>('/portal/cart')
      if (generation === loadGeneration) apply(data)
    } catch (exception: any) {
      if (generation === loadGeneration) { error.value = exception.response?.data?.message || 'Unable to load your basket.'; items.value = [] }
    } finally { if (generation === loadGeneration) loading.value = false }
  }
  async function add(sku: string, quantity: number) {
    error.value = ''
    try { const { data } = await api.post<CartPayload>('/portal/cart/items', { sku, quantity }); apply(data) }
    catch (exception: any) { error.value = Object.values(exception.response?.data?.errors || { cart: [exception.response?.data?.message || 'Unable to add this product.'] }).flat().join(' '); throw exception }
  }
  async function quickOrder(items: Array<{ sku: string; quantity: number }>) {
    error.value = ''
    try { const { data } = await api.post<CartPayload>('/portal/cart/quick-order', { items }); apply(data) }
    catch (exception: any) { error.value = Object.values(exception.response?.data?.errors || { cart: [exception.response?.data?.message || 'Unable to add these quick order items.'] }).flat().join(' '); throw exception }
  }
  async function setQuantity(sku: string, quantity: number) {
    error.value = ''
    try { const { data } = await api.patch<CartPayload>(`/portal/cart/items/${encodeURIComponent(sku)}`, { quantity }); apply(data) }
    catch (exception: any) { error.value = Object.values(exception.response?.data?.errors || { cart: [exception.response?.data?.message || 'Unable to change this quantity.'] }).flat().join(' '); throw exception }
  }
  async function remove(sku: string) { const { data } = await api.delete<CartPayload>(`/portal/cart/items/${encodeURIComponent(sku)}`); apply(data) }
  async function clear() { const { data } = await api.delete<CartPayload>('/portal/cart'); apply(data) }

  return { items, scope, loading, error, itemCount, subtotalMinor, load, add, quickOrder, setQuantity, remove, clear }
})
