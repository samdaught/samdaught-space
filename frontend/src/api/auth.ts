import { acceptSession, forgetSession, refreshSession, type Session } from './session'

export type AuthFields = Record<string, string[]>
export class AuthenticationError extends Error {
  constructor(
    message: string,
    public status: number,
    public fields: AuthFields = {},
  ) {
    super(message)
  }
}

async function request<T>(action: string, body: Record<string, string | boolean>): Promise<T> {
  const current = await refreshSession()
  const response = await fetch('/api/v1/auth/' + action, {
    method: 'POST',
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': current.csrf_token },
    body: JSON.stringify(body),
  })
  if (!response.ok) {
    if (response.status === 401) forgetSession()
    let value: { message?: string; error?: { message?: string; fields?: AuthFields } } = {}
    try {
      value = await response.json()
    } catch {}
    throw new AuthenticationError(
      value.error?.message ??
        value.message ??
        (response.status === 400
          ? 'Сессия формы устарела. Обновите страницу и повторите.'
          : 'Не удалось выполнить запрос. Попробуйте ещё раз.'),
      response.status,
      value.error?.fields,
    )
  }
  return ((await response.json()) as { data: T }).data
}

export async function login(
  username: string,
  password: string,
  rememberMe: boolean,
): Promise<Session> {
  return acceptSession(await request<Session>('login', { username, password, rememberMe }))
}
export async function signup(
  username: string,
  password: string,
  passwordRepeat: string,
): Promise<void> {
  await request('signup', { username, password, passwordRepeat })
}
export async function logout(): Promise<void> {
  try {
    acceptSession(await request<Session>('logout', {}))
  } catch (cause) {
    if (cause instanceof AuthenticationError && cause.status === 401) {
      await refreshSession()
      return
    }
    throw cause
  }
}
export async function changePassword(
  newPassword: string,
  newPasswordRepeat: string,
  currentPassword?: string,
): Promise<void> {
  const body: Record<string, string | boolean> =
    currentPassword === undefined
      ? { newPassword, newPasswordRepeat }
      : { currentPassword, newPassword, newPasswordRepeat }
  acceptSession(
    await request<Session>(
      currentPassword === undefined ? 'force-password-change' : 'change-password',
      body,
    ),
  )
}

export function safeReturnPath(value: unknown): string {
  if (
    typeof value !== 'string' ||
    !value.startsWith('/') ||
    value.startsWith('//') ||
    /[\\\u0000-\u001f\u007f]/.test(value) ||
    /^\/(?:login|signup|force-password-change|site|api)(?:[/?#]|$)/.test(value)
  )
    return '/cabinet'
  return value
}
