import type { PaginationMeta } from './pagination'

export type NewsContent = { title: string; summary: string; body: string }
export type NewsAdmin = NewsContent & {
  id: number
  is_published: boolean
  published_at: string | null
  created_at: string
  updated_at: string
  revision: number
}
export type NewsSummary = { id: number; title: string; excerpt: string; published_at: string }
export type NewsArticle = NewsSummary & { body: string }
export type NewsPage<T> = { data: T[]; meta: PaginationMeta }

export function formatNewsDate(value: string | null): string {
  if (value === null) return 'Не опубликована'
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? 'Дата неизвестна'
    : new Intl.DateTimeFormat('ru-RU', { dateStyle: 'long' }).format(date)
}

async function readResponse<T>(response: Response): Promise<T> {
  if (response.status === 422) {
    const result = (await response.json()) as {
      error: { message: string; fields: Record<string, string[]> }
    }
    throw new Error(Object.values(result.error.fields).flat().join('\n') || result.error.message)
  }
  if (response.status === 401) throw new Error('Войдите, чтобы управлять новостями')
  if (response.status === 403) throw new Error('Недостаточно прав для управления новостями')
  if (response.status === 404) throw new Error('Новость или страница списка не найдена')
  if (response.status === 409) {
    let message = 'Новость уже изменена. Загрузите сохранённую версию перед повторным действием.'
    try {
      const result = (await response.json()) as { message?: string }
      if (result.message) message = result.message
    } catch {}
    throw new Error(message)
  }
  if (!response.ok) throw new Error('Не удалось выполнить запрос новостей. HTTP ' + response.status)
  return (await response.json()) as T
}

function headers(): Record<string, string> {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (!csrf) throw new Error('CSRF-токен не найден')
  return { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }
}

export async function getNews(page = 1): Promise<NewsPage<NewsSummary>> {
  return readResponse(
    await fetch('/api/v1/news?' + new URLSearchParams({ page: String(page), per_page: '5' }), {
      credentials: 'same-origin',
    }),
  )
}
export async function getNewsArticle(id: number): Promise<NewsArticle> {
  return (
    await readResponse<{ data: NewsArticle }>(
      await fetch(`/api/v1/news/${id}`, { credentials: 'same-origin' }),
    )
  ).data
}
export async function getAdminNews(page = 1): Promise<NewsPage<NewsAdmin>> {
  return readResponse(
    await fetch(
      '/api/v1/admin/news?' + new URLSearchParams({ page: String(page), per_page: '20' }),
      { credentials: 'same-origin' },
    ),
  )
}
export async function getAdminNewsArticle(id: number): Promise<NewsAdmin> {
  return (
    await readResponse<{ data: NewsAdmin }>(
      await fetch(`/api/v1/admin/news/${id}`, { credentials: 'same-origin' }),
    )
  ).data
}
export async function saveNews(
  id: number | null,
  content: NewsContent,
  revision?: number,
): Promise<NewsAdmin> {
  const body = {
    title: content.title.trim(),
    summary: content.summary.trim(),
    body: content.body.trim(),
    ...(id === null ? {} : { revision }),
  }
  return (
    await readResponse<{ data: NewsAdmin }>(
      await fetch(id === null ? '/api/v1/admin/news' : `/api/v1/admin/news/${id}`, {
        method: id === null ? 'POST' : 'PATCH',
        credentials: 'same-origin',
        headers: headers(),
        body: JSON.stringify(body),
      }),
    )
  ).data
}
export async function setNewsPublished(
  id: number,
  revision: number,
  published: boolean,
): Promise<NewsAdmin> {
  return (
    await readResponse<{ data: NewsAdmin }>(
      await fetch(`/api/v1/admin/news/${id}/${published ? 'publish' : 'unpublish'}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers(),
        body: JSON.stringify({ revision }),
      }),
    )
  ).data
}
export async function deleteNews(id: number, revision: number): Promise<void> {
  await readResponse(
    await fetch(`/api/v1/admin/news/${id}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: headers(),
      body: JSON.stringify({ revision }),
    }),
  )
}
