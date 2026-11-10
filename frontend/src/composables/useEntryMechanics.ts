import { ref, computed, watch } from 'vue'
import {
  emptyMechanics,
  getMechanicsOptions,
  mechanicsInput,
  type EntryMechanics,
  type MechanicsOptions,
} from '../api/entryMechanics'
export function useEntryMechanics(profile: () => number | null, category: () => string) {
  const fields = ref<EntryMechanics>(emptyMechanics()),
    options = ref<MechanicsOptions | null>(null),
    error = ref(''),
    loading = ref(false),
    loadedProfile = ref<number | null | undefined>()
  let request = 0
  async function load() {
    if (!category()) return
    const current = ++request,
      p = profile()
    loading.value = true
    error.value = ''
    try {
      const data = await getMechanicsOptions(p)
      if (current === request) {
        options.value = data
        loadedProfile.value = p
      }
    } catch (cause) {
      if (current === request)
        error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить механику'
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
  const ready = computed(
    () =>
      options.value !== null && loadedProfile.value === profile() && !loading.value && !error.value,
  )
  const input = computed(() => mechanicsInput(category(), fields.value))
  return { fields, options, error, loading, ready, input, load }
}
