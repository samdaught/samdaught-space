<script setup lang="ts">
import { computed, nextTick, ref, useId } from 'vue'
import { spellLevelTitle, type SpellDefinition } from '../api/spells'
import SpellCardContent from './SpellCardContent.vue'

type SpellOption = {
  id: number
  title: string
  body?: string | null
  spell?: SpellDefinition | null
}
const props = defineProps<{ entries: SpellOption[] }>()
const selected = defineModel<number[]>({ default: () => [] })
const dialog = ref<HTMLDialogElement | null>(null)
const active = ref<SpellOption | null>(null)
const titleId = useId()
const levels = computed(() =>
  [...new Set(props.entries.map((entry) => entry.spell?.level ?? -1))].sort((a, b) => a - b),
)
async function showSpell(entry: SpellOption): Promise<void> {
  active.value = entry
  await nextTick()
  dialog.value?.showModal()
}
function dismissBackdrop(event: MouseEvent): void {
  if (event.target !== dialog.value || !dialog.value) return
  const rect = dialog.value.getBoundingClientRect()
  if (
    event.clientX < rect.left ||
    event.clientX > rect.right ||
    event.clientY < rect.top ||
    event.clientY > rect.bottom
  )
    dialog.value.close()
}
</script>

<template>
  <div class="spell-choice-list">
    <section
      v-for="level in levels"
      :key="level"
      :aria-label="level < 0 ? 'Заклинания' : spellLevelTitle(level)"
    >
      <h4>{{ level < 0 ? 'Заклинания' : spellLevelTitle(level) }}</h4>
      <div
        v-for="entry in entries.filter((entry) => (entry.spell?.level ?? -1) === level)"
        :key="entry.id"
        class="spell-choice"
        :class="{ 'is-selected': selected.includes(entry.id) }"
      >
        <label class="spell-choice-label">
          <input v-model="selected" type="checkbox" :value="entry.id" />
          <span
            ><strong>{{ entry.title }}</strong
            ><small v-if="entry.spell"
              >{{ spellLevelTitle(entry.spell.level)
              }}<template v-if="entry.spell.school"> · {{ entry.spell.school }}</template></small
            ><span
              v-if="entry.spell?.concentration || entry.spell?.ritual"
              class="spell-choice-tags"
              ><small v-if="entry.spell.concentration">Концентрация</small
              ><small v-if="entry.spell.ritual">Ритуал</small></span
            ></span
          >
        </label>
        <button
          v-if="entry.spell"
          type="button"
          class="spell-info"
          :aria-label="`Описание заклинания «${entry.title}»`"
          aria-haspopup="dialog"
          @click="showSpell(entry)"
        >
          <span aria-hidden="true">i</span>
        </button>
      </div>
    </section>
    <dialog ref="dialog" class="spell-dialog" :aria-labelledby="titleId" @click="dismissBackdrop">
      <template v-if="active?.spell">
        <header>
          <h3 :id="titleId">{{ active.title }}</h3>
          <button type="button" @click="dialog?.close()">Закрыть</button>
        </header>
        <SpellCardContent :spell="active.spell" :body="active.body ?? ''" />
      </template>
    </dialog>
  </div>
</template>

<style scoped>
.spell-choice-list section {
  margin-bottom: 1rem;
}
.spell-choice-list h4 {
  margin: 0 0 0.5rem;
  padding: 0.65rem 0.8rem;
  border-radius: 8px;
  background: var(--lavender);
  font: 700 1rem/1.4 var(--font-body);
}
.spell-choice {
  display: flex;
  align-items: start;
  gap: 0.7rem;
  padding: 0.65rem 0.75rem;
  margin-top: 0.5rem;
  border: 1px solid var(--line-strong);
  border-radius: 10px;
  background: var(--paper);
}
.spell-choice.is-selected {
  border-color: var(--accent);
  box-shadow: inset 3px 0 var(--accent);
}
.spell-choice-label {
  display: flex;
  align-items: start;
  gap: 0.7rem;
  flex: 1;
  min-width: 0;
  cursor: pointer;
  min-height: 44px;
}
.spell-choice-label input {
  flex: none;
  margin-top: 0.3rem;
}
.spell-choice-label strong {
  display: block;
  overflow-wrap: anywhere;
}
.spell-choice-label small {
  display: block;
  font-weight: 400;
  font-size: 0.8rem;
  color: var(--muted);
  margin-top: 0.15rem;
}
.spell-choice-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.35rem;
}
.spell-choice-tags small {
  background: var(--lavender);
  padding: 0.1rem 0.5rem;
  border-radius: 12px;
  color: var(--text);
}
.spell-info {
  flex: none;
  width: 44px;
  height: 44px;
  min-height: 44px;
  padding: 0;
  margin: 0;
  border-radius: 50%;
  font-family: var(--font-heading);
  font-size: 1.1rem;
  font-weight: 700;
}
.spell-dialog {
  width: min(460px, calc(100vw - 2rem));
  max-height: calc(100dvh - 2rem);
  overflow: auto;
  margin: auto;
  padding: 1.15rem;
  color: var(--text);
  background: var(--paper);
  border: 1px solid var(--line-strong);
  border-radius: 12px;
  box-shadow: 0 12px 48px #35243f38;
}
.spell-dialog::backdrop {
  background: #35243f55;
}
.spell-dialog header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  gap: 0.7rem;
  margin-bottom: 0.9rem;
}
.spell-dialog h3 {
  font: 700 1.1rem/1.4 var(--font-body);
  margin: 0;
  overflow-wrap: anywhere;
}
.spell-dialog header button {
  margin: 0;
  padding: 0.35rem 0.6rem;
  min-height: 44px;
  font-size: 0.8rem;
}
</style>
