<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, onUpdated, ref, useAttrs, watch } from 'vue'
import { Check, ChevronDown } from '@lucide/vue'

defineOptions({ inheritAttrs: false })

type SelectItem = { label: string; value: string; rawValue: unknown; disabled: boolean; index: number }

const props = defineProps<{
  modelValue?: string | number | null
  value?: string | number | null
  disabled?: boolean
}>()
const emit = defineEmits<{
  'update:modelValue': [value: unknown]
  change: [event: Event]
}>()

const attrs = useAttrs()
const nativeSelect = ref<HTMLSelectElement>()
const trigger = ref<HTMLButtonElement>()
const menu = ref<HTMLDivElement>()
const items = ref<SelectItem[]>([])
const selectedLabel = ref('Select an option')
const selectedValue = ref('')
const open = ref(false)
const activeIndex = ref(0)
const menuStyle = ref<Record<string, string>>({})
const listboxId = `app-select-${Math.random().toString(36).slice(2)}`

const selectedIndex = computed(() => items.value.findIndex(item => item.value === selectedValue.value))
const ariaLabel = computed(() => attrs['aria-label'] as string | undefined)

function syncSelection() {
  const native = nativeSelect.value
  if (!native) return

  const controlledValue = props.modelValue !== undefined ? props.modelValue : props.value
  if (controlledValue !== undefined && controlledValue !== null) {
    const match = Array.from(native.options).find(option => option.value === String(controlledValue))
    if (match) native.value = match.value
  }

  const nextItems = Array.from(native.options).map((option, index) => ({
    label: option.label || option.textContent?.trim() || '',
    value: option.value,
    rawValue: (option as HTMLOptionElement & { _value?: unknown })._value ?? option.value,
    disabled: option.disabled || (option.parentElement instanceof HTMLOptGroupElement && option.parentElement.disabled),
    index,
  }))

  const changedOptions = nextItems.length !== items.value.length || nextItems.some((item, index) => {
    const current = items.value[index]
    return !current || current.label !== item.label || current.value !== item.value || current.disabled !== item.disabled || current.rawValue !== item.rawValue
  })
  if (changedOptions) items.value = nextItems

  if (selectedValue.value !== native.value) selectedValue.value = native.value
  const nextLabel = native.selectedOptions[0]?.label || native.selectedOptions[0]?.textContent?.trim() || 'Select an option'
  if (selectedLabel.value !== nextLabel) selectedLabel.value = nextLabel
  if (open.value) updateMenuPosition()
}

function updateMenuPosition() {
  const rect = trigger.value?.getBoundingClientRect()
  if (!rect) return

  const width = Math.min(Math.max(rect.width, 170), window.innerWidth - 16)
  const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))
  const roomBelow = window.innerHeight - rect.bottom - 12
  const placeBelow = roomBelow >= 190 || roomBelow >= rect.top - 12
  const maxHeight = Math.max(110, Math.min(300, placeBelow ? roomBelow : rect.top - 12))
  const top = placeBelow ? rect.bottom + 6 : Math.max(8, rect.top - maxHeight - 6)

  const nextStyle = { left: `${left}px`, top: `${top}px`, width: `${width}px`, maxHeight: `${maxHeight}px` }
  if (Object.keys(nextStyle).some(key => menuStyle.value[key] !== nextStyle[key as keyof typeof nextStyle])) menuStyle.value = nextStyle
}

function focusActiveOption() {
  nextTick(() => menu.value?.querySelectorAll<HTMLButtonElement>('[role="option"]')[activeIndex.value]?.focus())
}

function showMenu() {
  if (props.disabled || open.value) return
  activeIndex.value = selectedIndex.value >= 0 ? selectedIndex.value : items.value.findIndex(item => !item.disabled)
  open.value = true
  updateMenuPosition()
  focusActiveOption()
}

function hideMenu(returnFocus = false) {
  if (!open.value) return
  open.value = false
  if (returnFocus) nextTick(() => trigger.value?.focus())
}

function choose(item: SelectItem) {
  const native = nativeSelect.value
  if (!native || item.disabled || props.disabled) return

  native.selectedIndex = item.index
  native.value = item.value
  selectedValue.value = item.value
  selectedLabel.value = item.label
  emit('update:modelValue', item.rawValue)
  const changeEvent = new Event('change', { bubbles: true })
  native.dispatchEvent(changeEvent)
  emit('change', changeEvent)
  hideMenu(true)
}

function onTriggerKeydown(event: KeyboardEvent) {
  if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
    event.preventDefault()
    if (!open.value) showMenu()
  }
}

function onMenuKeydown(event: KeyboardEvent) {
  const enabled = items.value.filter(item => !item.disabled)
  if (!enabled.length) return

  const current = enabled.findIndex(item => item.index === activeIndex.value)
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    const direction = event.key === 'ArrowDown' ? 1 : -1
    const next = enabled[(current + direction + enabled.length) % enabled.length]
    activeIndex.value = next.index
    focusActiveOption()
  } else if (event.key === 'Home' || event.key === 'End') {
    event.preventDefault()
    activeIndex.value = (event.key === 'Home' ? enabled[0] : enabled[enabled.length - 1]).index
    focusActiveOption()
  } else if (event.key === 'Escape') {
    event.preventDefault()
    hideMenu(true)
  } else if (event.key === 'Tab') {
    hideMenu()
  }
}

function onOutsidePointer(event: PointerEvent) {
  const target = event.target as Node | null
  if (target && !trigger.value?.contains(target) && !menu.value?.contains(target)) hideMenu()
}

function onViewportChange() {
  if (open.value) updateMenuPosition()
}

watch(() => [props.modelValue, props.value, props.disabled], syncSelection)
onMounted(() => {
  syncSelection()
  document.addEventListener('pointerdown', onOutsidePointer, true)
  window.addEventListener('resize', onViewportChange)
  window.addEventListener('scroll', onViewportChange, true)
})
onUpdated(syncSelection)
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onOutsidePointer, true)
  window.removeEventListener('resize', onViewportChange)
  window.removeEventListener('scroll', onViewportChange, true)
})
</script>

<template>
  <div class="app-select" :class="attrs.class" :style="attrs.style">
    <select ref="nativeSelect" class="app-select-native" :name="attrs.name as string | undefined" aria-hidden="true" tabindex="-1" @change="syncSelection"><slot /></select>
    <button
      ref="trigger"
      type="button"
      class="app-select-trigger"
      :class="attrs.class"
      :id="attrs.id as string | undefined"
      :title="attrs.title as string | undefined"
      :disabled="disabled"
      :aria-label="ariaLabel"
      :aria-haspopup="'listbox'"
      :aria-controls="listboxId"
      :aria-expanded="open"
      :aria-activedescendant="open && activeIndex >= 0 ? `${listboxId}-option-${activeIndex}` : undefined"
      :aria-required="attrs.required ? 'true' : undefined"
      :aria-invalid="attrs['aria-invalid'] as any"
      @click="open ? hideMenu() : showMenu()"
      @keydown="onTriggerKeydown"
    >
      <span class="app-select-label">{{ selectedLabel }}</span>
      <ChevronDown :size="15" class="app-select-chevron" :class="{ open }" />
    </button>
    <Teleport to="body">
      <div v-if="open" :id="listboxId" ref="menu" class="app-select-menu" role="listbox" :style="menuStyle" @keydown="onMenuKeydown">
        <button
          v-for="item in items"
          :id="`${listboxId}-option-${item.index}`"
          :key="`${item.index}-${item.value}`"
          type="button"
          role="option"
          class="app-select-option"
          :class="{ selected: item.value === selectedValue }"
          :aria-selected="item.value === selectedValue"
          :aria-disabled="item.disabled"
          :disabled="item.disabled"
          tabindex="-1"
          @click="choose(item)"
          @mouseenter="activeIndex = item.index"
        >
          <span>{{ item.label }}</span><Check v-if="item.value === selectedValue" :size="14" />
        </button>
      </div>
    </Teleport>
  </div>
</template>
