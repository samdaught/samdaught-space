export type FeatCategory = {
  code: string
  title: string
}

export async function getFeatCategories(): Promise<FeatCategory[]> {
  const response = await fetch('/api/v1/feat-categories', { credentials: 'same-origin' })
  if (!response.ok) {
    throw new Error('Не удалось загрузить категории черт. HTTP ' + response.status)
  }
  const result = (await response.json()) as { data: FeatCategory[] }
  return result.data
}
