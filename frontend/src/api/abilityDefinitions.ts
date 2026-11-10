export type AbilityDefinition = { code: string; title: string }

export async function getAbilityDefinitions(): Promise<AbilityDefinition[]> {
  const response = await fetch('/api/v1/ability-definitions', { credentials: 'same-origin' })
  if (response.status === 401) throw new Error('Войдите, чтобы заполнять записи архива')
  if (response.status === 403) throw new Error('Недостаточно прав для заполнения архива')
  if (!response.ok) throw new Error('Не удалось загрузить характеристики. HTTP ' + response.status)
  const result = (await response.json()) as { data: AbilityDefinition[] }
  return result.data
}
