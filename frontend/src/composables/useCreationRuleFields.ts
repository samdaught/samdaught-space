import { computed, ref, watch } from 'vue'
import {
  getCreationRuleOptions,
  getCreationRuleInput,
  type CreationRuleDraft,
  type CreationRuleOptions,
} from '../api/creationRules'

export function useCreationRuleFields(profile: () => number | null, category: () => string) {
  const fields = ref<CreationRuleDraft>({ grants: [], choices: [], hit_point_bonus: 0 })
  const options = ref<CreationRuleOptions | null>(null)
  const loading = ref(false)
  const error = ref('')
  const loadedProfile = ref<number | null | undefined>(undefined)
  let request = 0
  const ready = computed(
    () =>
      options.value !== null &&
      loadedProfile.value === profile() &&
      !loading.value &&
      error.value === '',
  )
  const supported = computed(() => options.value?.source_types.includes(category()) ?? false)
  const input = computed(() =>
    !supported.value
      ? undefined
      : options.value === null
        ? null
        : getCreationRuleInput(fields.value, options.value),
  )
  async function load(): Promise<void> {
    if (category() === '') return
    const current = ++request,
      requestedProfile = profile()
    loading.value = true
    error.value = ''
    try {
      const data = await getCreationRuleOptions(requestedProfile)
      if (current === request && requestedProfile === profile()) {
        options.value = data
        loadedProfile.value = requestedProfile
      }
    } catch (cause) {
      if (current === request)
        error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить выдачи'
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
  return { fields, options, loading, error, ready, supported, input, load }
}
