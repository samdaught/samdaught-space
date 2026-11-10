import type { PaginationMeta } from './pagination'

export type CharacterListItem = {
  id: number
  name: string
  rule_profile_id: number
  rule_profile_title: string
  target_level: number
  status: 'draft' | 'ready'
  revision: number
}

export type CharactersResponse = {
  data: CharacterListItem[]
  meta: PaginationMeta
}

export async function getCharacters(page: number = 1): Promise<CharactersResponse> {
  const params = new URLSearchParams({ page: String(page) })
  const response = await fetch('/api/v1/characters?' + params.toString(), {
    credentials: 'same-origin',
  })

  if (response.status === 401) {
    throw new Error('Войдите, чтобы открыть личный кабинет')
  }

  if (!response.ok) {
    throw new Error('Не удалось загрузить персонажей. HTTP ' + response.status)
  }

  const result = (await response.json()) as CharactersResponse

  return result
}
