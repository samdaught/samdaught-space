<script setup lang="ts">
defineProps<{
  title: string
  entries: { id: number; title: string }[]
  origin: { id: number; title: string; body: string | null } | null
  disabled: boolean
}>()
const selected = defineModel<number | null>({ required: true })
</script>

<template>
  <div class="origin-picker">
    <div class="origin-list" role="group" :aria-label="title">
      <button
        type="button"
        :disabled="disabled"
        :aria-pressed="selected === null"
        @click="selected = null"
      >
        Пока не выбран
      </button>
      <button
        v-for="entry in entries"
        :key="entry.id"
        type="button"
        :disabled="disabled"
        :aria-pressed="selected === entry.id"
        @click="selected = entry.id"
      >
        {{ entry.title }}
      </button>
      <p v-if="selected !== null && !entries.some((entry) => entry.id === selected)" role="alert">
        Выбранный вариант №{{ selected }} недоступен. Выберите другой.
      </p>
      <p v-if="!entries.length">Выберите мир, чтобы увидеть доступные варианты.</p>
    </div>
    <article class="origin-description" :aria-label="`Описание: ${title}`">
      <template v-if="origin && origin.id === selected">
        <h3>{{ origin.title }}</h3>
        <p class="origin-body">{{ origin.body || 'Описание в архиве пока не добавлено.' }}</p>
        <RouterLink :to="`/archive/${origin.id}`" target="_blank" rel="noopener"
          >Подробнее в архиве ↗</RouterLink
        >
      </template>
      <template v-else
        ><h3>{{ selected === null ? 'Выберите вариант слева' : 'Загружаем описание…' }}</h3>
        <p>Здесь появятся описание и игровые особенности выбранного варианта.</p></template
      >
      <slot />
    </article>
  </div>
</template>

<style scoped>
.origin-picker {
  display: grid;
  grid-template-columns: minmax(150px, 0.7fr) minmax(0, 2fr);
  gap: 1.25rem;
  align-items: start;
}
.origin-list {
  display: grid;
  gap: 0.35rem;
  max-height: 32rem;
  overflow-y: auto;
  padding: 0.35rem;
  background: var(--lavender);
  border-radius: 10px;
}
.origin-list button {
  margin-inline-start: 0;
  text-align: left;
  border-color: transparent;
  background: transparent;
  font-weight: 500;
  width: 100%;
}
.origin-list button[aria-pressed='true'] {
  background: var(--ink);
  color: var(--on-ink);
}
.origin-list p {
  padding: 0.6rem;
  font-size: 0.85rem;
}
.origin-description {
  min-width: 0;
  padding: 0.5rem 0.5rem 0.5rem 0;
}
.origin-description h3 {
  margin-top: 0;
}
.origin-body {
  white-space: pre-wrap;
}
@media (max-width: 680px) {
  .origin-picker {
    grid-template-columns: 1fr;
  }
  .origin-list {
    display: flex;
    flex-wrap: wrap;
    max-height: 14rem;
  }
  .origin-list button {
    width: auto;
  }
}
</style>
