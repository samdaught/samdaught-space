export type SpeciesCard = {
  age: string
  height: string
  roleplay: string
  portraits: { url: string; alt: string }[]
  features: { title: string; description: string }[]
}

export function emptySpeciesCard(): SpeciesCard {
  return { age: '', height: '', roleplay: '', portraits: [], features: [] }
}

export function safePortraitUrl(value: string): string | undefined {
  const url = value.trim()
  if (!url || /[\s\\]/.test(url)) return undefined
  if (url.startsWith('/') && !url.startsWith('//')) return url
  try {
    return ['https:', 'http:'].includes(new URL(url).protocol) ? url : undefined
  } catch {
    return undefined
  }
}
