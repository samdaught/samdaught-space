<script setup lang="ts">
import { spellLevelTitle, type SpellDefinition } from '../api/spells'
defineProps<{ spell: SpellDefinition; body: string }>()
</script>

<template>
  <div class="spell-card-content">
    <dl class="spell-properties">
      <dt>Уровень</dt>
      <dd>{{ spellLevelTitle(spell.level) }}</dd>
      <dt>Школа</dt>
      <dd>{{ spell.school || '—' }}</dd>
      <dt>Время сотворения</dt>
      <dd>{{ spell.casting_time || '—' }}</dd>
      <dt>Дистанция</dt>
      <dd>{{ spell.range_text || '—' }}</dd>
      <dt>Компоненты</dt>
      <dd>{{ spell.components || '—' }}</dd>
      <dt>Длительность</dt>
      <dd>{{ spell.duration || '—' }}</dd>
    </dl>
    <div v-if="spell.concentration || spell.ritual" class="spell-tags">
      <span v-if="spell.concentration">Концентрация</span><span v-if="spell.ritual">Ритуал</span>
    </div>
    <div class="spell-effect">
      <h4>Эффект</h4>
      <p>{{ body || 'Описание эффекта пока не добавлено.' }}</p>
    </div>
  </div>
</template>

<style scoped>
.spell-properties {
  display: grid;
  grid-template-columns: minmax(110px, 0.9fr) minmax(0, 1.5fr);
  gap: 0.35rem 1rem;
  margin: 0;
  font-size: 0.9rem;
}
.spell-properties dt {
  font-weight: 700;
}
.spell-properties dt::after {
  content: ':';
}
.spell-properties dd {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.spell-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.8rem;
}
.spell-tags span {
  padding: 0.15rem 0.55rem;
  border-radius: 20px;
  background: var(--lavender);
  font-size: 0.8rem;
}
.spell-effect {
  margin-top: 0.9rem;
  padding-top: 0.8rem;
  border-top: 1px solid var(--line-strong);
}
.spell-effect h4 {
  margin: 0 0 0.35rem;
  font: 700 0.95rem/1.4 var(--font-body);
}
.spell-effect p {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  margin: 0;
}
@media (max-width: 400px) {
  .spell-properties {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr);
    gap: 0.45rem 0.65rem;
  }
}
</style>
