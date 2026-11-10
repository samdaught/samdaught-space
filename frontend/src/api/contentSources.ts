export type ContentSourceOption = {
  id: number
  title: string
}

type ContentSourceOptionsResponse = {
  data: ContentSourceOption[]
}

export async function getContentSourceOptions(): Promise<ContentSourceOption[]> {
  const response = await fetch('/api/v1/content-sources/options', {
    credentials: 'same-origin',
  })

  if (!response.ok) {
    throw new Error(`Не удалось загрузить источники: HTTP ${response.status}`)
  }

  const result = (await response.json()) as ContentSourceOptionsResponse
  return result.data
}

export type ContentSource = {
  id: number
  code: string
  title: string
  url: string | null
  rights_note: string
  attribution_text: string | null
}

type ContentSourcesResponse = {
  data: ContentSource[]
}

export async function getContentSources(): Promise<ContentSource[]> {
  const response = await fetch('/api/v1/content-sources', {
    credentials: 'same-origin',
  })

  if (!response.ok) {
    throw new Error(`Не удалось загрузить источники: HTTP ${response.status}`)
  }

  const body = (await response.json()) as ContentSourcesResponse
  return body.data
}
export type NewContentSource = {
  code: string
  title: string
  rights_note: string
}

type CreatedSourceResponse = {
  data: ContentSource
}

export async function createContentSource(input: NewContentSource): Promise<ContentSource> {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (!csrf) throw new Error('CSRF-токен не найден')

  const response = await fetch('/api/v1/content-sources', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrf,
    },
    body: JSON.stringify(input),
  })
  if (response.status === 422) {
    const body = (await response.json()) as {
      error: {
        message: string
        fields: Record<string, string[]>
      }
    }

    const firstMessage = Object.values(body.error.fields)[0]?.[0]
    throw new Error(firstMessage ?? body.error.message)
  }
  if (!response.ok) throw new Error(`Не удалось создать источник: HTTP ${response.status}`)

  const body = (await response.json()) as CreatedSourceResponse
  return body.data
}
