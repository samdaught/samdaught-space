import { onBeforeUnmount, ref } from 'vue'

type Guard = { dirty: () => boolean; busy: () => boolean }
const guards = new Set<Guard>()
let confirmedAction = false
export const navigationPending = ref(false)
export function finishNavigation(): void {
  navigationPending.value = false
}

export function confirmLeave(): boolean {
  if (confirmedAction) return true
  if ([...guards].some((guard) => guard.busy())) {
    window.alert('Дождитесь завершения сохранения, затем повторите переход.')
    return false
  }
  const allowed =
    ![...guards].some((guard) => guard.dirty()) ||
    window.confirm('Есть несохранённые изменения. Уйти и потерять их?')
  if (allowed) navigationPending.value = true
  return allowed
}

export async function withConfirmedLeave(action: () => Promise<void>): Promise<void> {
  if (!confirmLeave()) return
  await afterSuccessfulSave(action)
}

export async function afterSuccessfulSave<T>(action: () => Promise<T>): Promise<T> {
  confirmedAction = true
  try {
    return await action()
  } finally {
    confirmedAction = false
    finishNavigation()
  }
}

export function useUnsavedChanges(dirty: () => boolean, busy: () => boolean = () => false): void {
  const guard = { dirty, busy }
  guards.add(guard)
  const beforeUnload = (event: BeforeUnloadEvent) => {
    if (dirty() || busy()) {
      event.preventDefault()
      event.returnValue = ''
    }
  }
  window.addEventListener('beforeunload', beforeUnload)
  onBeforeUnmount(() => {
    guards.delete(guard)
    window.removeEventListener('beforeunload', beforeUnload)
  })
}
