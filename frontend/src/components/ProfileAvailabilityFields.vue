<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useUnsavedChanges } from '../composables/useUnsavedChanges'
type Mode = 'inherit' | 'allow' | 'deny'
type Row = {
  profile_id: number
  title: string
  mode: Mode
  compatible_origin: boolean
  available: boolean
}
const props = defineProps<{ entryId: number }>()
const rows = ref<Row[]>([]),
  modes = ref<Record<number, Mode>>({})
const busy = ref(false),
  saving = ref(false),
  error = ref(''),
  notice = ref('')
const dirty = computed(() => rows.value.some((row) => modes.value[row.profile_id] !== row.mode))
useUnsavedChanges(
  () => dirty.value,
  () => saving.value,
)
let request = 0
async function read(response: Response): Promise<Row[]> {
  if (!response.ok) {
    if (response.status === 409)
      throw new Error(
        'Настройка изменена в другой вкладке. Перезагрузите доступность; выбранные значения пока оставлены в форме.',
      )
    throw new Error('Не удалось обработать доступность. HTTP ' + response.status)
  }
  return ((await response.json()) as { data: Row[] }).data
}
async function load(): Promise<void> {
  if (
    dirty.value &&
    !window.confirm('Заменить несохранённые настройки доступности данными сервера?')
  )
    return
  const current = ++request
  busy.value = true
  error.value = ''
  notice.value = ''
  try {
    const data = await read(
      await fetch(`/api/v1/catalog-entries/${props.entryId}/availability`, {
        credentials: 'same-origin',
      }),
    )
    if (current !== request) return
    rows.value = data
    modes.value = Object.fromEntries(data.map((row) => [row.profile_id, row.mode]))
  } catch (cause) {
    if (current === request)
      error.value = cause instanceof Error ? cause.message : 'Ошибка загрузки'
  } finally {
    if (current === request) busy.value = false
  }
}
async function save(row: Row): Promise<void> {
  if (busy.value) return
  busy.value = true
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    const response = await fetch(
      `/api/v1/catalog-entries/${props.entryId}/availability/${row.profile_id}`,
      {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token':
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
        body: JSON.stringify({ mode: modes.value[row.profile_id], previous_mode: row.mode }),
      },
    )
    const data = await read(response)
    rows.value = rows.value.map((old) => {
      const fresh = data.find((item) => item.profile_id === old.profile_id)!
      return old.profile_id === row.profile_id ? fresh : { ...old, available: fresh.available }
    })
    notice.value = 'Доступность сохранена. Остальные изменения сохраняются своими кнопками.'
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Ошибка сохранения'
  } finally {
    busy.value = false
    saving.value = false
  }
}
watch(() => props.entryId, load, { immediate: true })
</script>
<template>
  <section class="profile-availability" aria-labelledby="availability-title">
    <h2 id="availability-title">Доступность при создании персонажа</h2>
    <p>
      Публикация управляет чтением в архиве. Здесь задаётся возможность выбирать запись по правилам
      мира. «Наследовать» удаляет местное исключение; затем действуют происхождение и настройки
      базового профиля. Для выбора в редакторе также нужны публикация и настроенные игровые правила.
    </p>
    <fieldset :disabled="busy">
      <legend>Исключения профилей</legend>
      <div v-for="row in rows" :key="row.profile_id" class="availability-row">
        <label
          >{{ row.title
          }}<select v-model="modes[row.profile_id]">
            <option value="inherit">Наследовать</option>
            <option value="allow" :disabled="!row.compatible_origin">Разрешить</option>
            <option value="deny">Запретить</option>
          </select></label
        >
        <span
          >{{ row.available ? 'Сейчас доступна' : 'Сейчас недоступна'
          }}<template v-if="!row.compatible_origin">: запись другого мира</template></span
        >
        <button type="button" :disabled="modes[row.profile_id] === row.mode" @click="save(row)">
          Сохранить для профиля
        </button>
      </div>
    </fieldset>
    <p v-if="busy" role="status">Обрабатываем настройки…</p>
    <p v-if="error" role="alert">{{ error }}</p>
    <p v-if="notice" role="status">{{ notice }}</p>
    <p v-if="dirty">Есть несохранённые настройки доступности.</p>
    <button type="button" :disabled="busy" @click="load">Перезагрузить доступность</button>
  </section>
</template>
