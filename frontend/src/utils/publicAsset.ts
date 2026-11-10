export function publicAsset(name: string): string {
  return new URL(import.meta.env.BASE_URL + name, import.meta.url).href
}
