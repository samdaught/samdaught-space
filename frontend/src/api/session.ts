import { shallowRef } from 'vue'

export type Session = {
  authenticated: boolean
  username: string | null
  must_change_password: boolean
  can_manage_catalog: boolean
  can_manage_news: boolean
  csrf_token: string
  login_url: string
  logout_url: string
  return_url: string
}

export const session = shallowRef<Session | null>(null)
let pending: Promise<Session> | null = null
let generation = 0

function applySession(value: Session): Session {
  if (
    typeof value.authenticated !== 'boolean' ||
    (value.username !== null && typeof value.username !== 'string') ||
    typeof value.must_change_password !== 'boolean' ||
    typeof value.can_manage_catalog !== 'boolean' ||
    typeof value.can_manage_news !== 'boolean' ||
    typeof value.csrf_token !== 'string' ||
    value.csrf_token === '' ||
    typeof value.login_url !== 'string' ||
    typeof value.logout_url !== 'string' ||
    typeof value.return_url !== 'string'
  )
    throw new Error('Сервер вернул некорректные сведения о доступе')
  session.value = value
  document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', value.csrf_token)
  return value
}

export function acceptSession(value: Session): Session {
  ++generation
  pending = null
  return applySession(value)
}
export function forgetSession(): void {
  ++generation
  pending = null
  session.value = null
}

export function refreshSession(): Promise<Session> {
  if (pending !== null) return pending
  const current = generation
  pending = (async () => {
    try {
      const response = await fetch('/api/v1/session', {
        credentials: 'same-origin',
        cache: 'no-store',
      })
      if (!response.ok) throw new Error('Не удалось проверить доступ. HTTP ' + response.status)
      const value = (await response.json()) as Session

      if (current !== generation) {
        if (session.value !== null) return session.value
        throw new Error('Сессия изменилась. Повторите запрос.')
      }
      return applySession(value)
    } catch (cause) {
      if (current !== generation && session.value !== null) return session.value
      if (current === generation) session.value = null
      throw cause
    } finally {
      if (current === generation) pending = null
    }
  })()
  return pending
}
