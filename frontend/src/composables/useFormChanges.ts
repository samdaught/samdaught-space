import { computed, ref } from 'vue'
import { useUnsavedChanges } from './useUnsavedChanges'

export function useFormChanges(
  source: () => unknown,
  busy: () => boolean,
  ready: () => boolean = () => true,
) {
  const fingerprint = () => JSON.stringify(source())
  const baseline = ref(fingerprint())
  const dirty = computed(() => ready() && baseline.value !== fingerprint())
  const markSaved = () => {
    baseline.value = fingerprint()
  }
  useUnsavedChanges(() => dirty.value, busy)
  return { dirty, markSaved }
}
