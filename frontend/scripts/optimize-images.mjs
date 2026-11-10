import sharp from 'sharp'
import { readdir, stat } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'

const names = (await readdir(new URL('../public/', import.meta.url)))
  .filter((name) => name.endsWith('.png'))
  .map((name) => name.slice(0, -4))
let original = 0,
  optimized = 0
for (const name of names) {
  const source = fileURLToPath(new URL(`../public/${name}.png`, import.meta.url))
  const target = fileURLToPath(new URL(`../public/${name}.webp`, import.meta.url))
  const before = (await stat(source)).size
  const output = await sharp(source)
    .resize({ width: 1200, height: 1400, fit: 'inside', withoutEnlargement: true })
    .webp({ quality: 84, alphaQuality: 100, effort: 6 })
    .toFile(target)
  original += before
  optimized += output.size
  console.log(`${name}: ${Math.round(before / 1024)} → ${Math.round(output.size / 1024)} KiB`)
}
console.log(`Total: ${Math.round(original / 1024)} → ${Math.round(optimized / 1024)} KiB`)
