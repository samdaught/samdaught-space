export type RuleProfile = {
  id: number
  code: string
  title: string
}

type RuleProfilesResponse = {
  data: RuleProfile[]
}

export async function getRuleProfiles(): Promise<RuleProfile[]> {
  const response = await fetch('/api/v1/rule-profiles')

  if (!response.ok) {
    throw new Error('Не удалось загрузить профили. HTTP ' + response.status)
  }

  const result = (await response.json()) as RuleProfilesResponse

  return result.data
}
