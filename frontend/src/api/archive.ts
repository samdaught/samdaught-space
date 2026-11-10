export type ArchiveEntry = {
  id: number
  slug: string
  title: string
  type: string
  type_title: string
  profile_title: string
  source_title: string | null
  excerpt: string
}
export type ArchiveDetails = ArchiveEntry & {
  body: string | null
  source_url: string | null
  attribution_text: string | null
}
export type ArchivePage = {
  data: ArchiveEntry[]
  meta: { total: number; page: number; per_page: number; page_count: number }
}
async function read<T>(url: string): Promise<T> {
  const response = await fetch(url, { credentials: 'same-origin' })
  if (!response.ok)
    throw new Error(
      response.status === 404
        ? 'Запись или страница архива не найдена.'
        : `Не удалось загрузить архив: HTTP ${response.status}`,
    )
  return response.json() as Promise<T>
}
export function getArchiveEntries(query: URLSearchParams): Promise<ArchivePage> {
  return read('/api/v1/archive/entries?' + query.toString())
}
export async function getArchiveTypes(): Promise<Array<{ code: string; title: string }>> {
  return (await read<{ data: Array<{ code: string; title: string }> }>('/api/v1/archive/types'))
    .data
}
export async function getArchiveEntry(id: number): Promise<ArchiveDetails> {
  return (await read<{ data: ArchiveDetails }>(`/api/v1/archive/entries/${id}`)).data
}
