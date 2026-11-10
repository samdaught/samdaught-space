import { ref, watch, computed } from 'vue'
import {
  emptyRulebook,
  getRulebookOptions,
  type RulebookOptions,
  type RulebookData,
} from '../api/rulebook'
export function useRulebook(profile: () => number | null, category: () => string) {
  const fields = ref<RulebookData>(emptyRulebook()),
    options = ref<RulebookOptions | null>(null),
    error = ref(''),
    loading = ref(false)
  let request = 0
  const loaded = ref<number | null | undefined>()
  async function load() {
    if (!category()) return
    const current = ++request
    loading.value = true
    error.value = ''
    try {
      const result = await getRulebookOptions(profile())
      if (current === request) {
        options.value = result
        loaded.value = profile()
      }
    } catch (cause) {
      if (current === request)
        error.value = cause instanceof Error ? cause.message : 'Ошибка загрузки'
    } finally {
      if (current === request) loading.value = false
    }
  }
  watch(
    [profile, category],
    () => {
      void load()
    },
    { immediate: true },
  )
  return {
    fields,
    options,
    error,
    loading,
    load,
    ready: computed(
      () => !!options.value && loaded.value === profile() && !loading.value && !error.value,
    ),
  }
}
