<script setup lang="ts">
import { computed } from 'vue'
import { safePortraitUrl } from '../api/speciesCard'
import type { ClassCard, ClassDetails } from '../api/classCard'

const props = defineProps<{ card: ClassCard; details: ClassDetails; body: string; title: string }>()
const abilityTitles: Record<string, string> = {
  str: 'Сила',
  dex: 'Ловкость',
  con: 'Телосложение',
  int: 'Интеллект',
  wis: 'Мудрость',
  cha: 'Харизма',
}
const training = computed(() =>
  props.details.training.map((row) => {
    if (row.kind === 'weapon')
      return row.value === 'simple'
        ? 'простое оружие'
        : row.value === 'martial'
          ? 'воинское оружие'
          : row.value
    return row.value
  }),
)
const features = computed(() =>
  [...props.card.features].sort((left, right) => left.level - right.level),
)
</script>

<template>
  <div class="class-card-content">
    <div v-if="card.portraits.length" class="class-portraits">
      <figure v-for="(portrait, index) in card.portraits" :key="index">
        <img
          v-if="safePortraitUrl(portrait.url)"
          :src="safePortraitUrl(portrait.url)"
          :alt="portrait.alt || `Представитель класса «${title}»`"
          loading="lazy"
          referrerpolicy="no-referrer"
          width="400"
          height="240"
        />
        <span v-else>Иллюстрация {{ index + 1 }}</span>
      </figure>
    </div>
    <p v-if="body" class="class-prose">{{ body }}</p>
    <dl
      v-if="card.primary_abilities.length || details.hit_die_sides || training.length"
      class="class-facts"
    >
      <div v-if="card.primary_abilities.length">
        <dt>Основная характеристика</dt>
        <dd>{{ card.primary_abilities.map((code) => abilityTitles[code] ?? code).join(', ') }}</dd>
      </div>
      <div v-if="details.hit_die_sides">
        <dt>Кость хитов</dt>
        <dd>d{{ details.hit_die_sides }}</dd>
      </div>
      <div v-if="details.saving_throw_codes.length">
        <dt>Спасброски</dt>
        <dd>
          {{ details.saving_throw_codes.map((code) => abilityTitles[code] ?? code).join(', ') }}
        </dd>
      </div>
      <div v-if="training.length">
        <dt>Владения</dt>
        <dd>{{ training.join(', ') }}</dd>
      </div>
      <div v-if="details.skill_choice_count">
        <dt>Навыки</dt>
        <dd>
          {{ details.skill_choice_count }} на выбор{{
            details.skills.length ? `: ${details.skills.join(', ')}` : ''
          }}
        </dd>
      </div>
    </dl>
    <section v-if="features.length" aria-label="Особенности класса">
      <h4>Особенности до 3-го уровня</h4>
      <div class="class-features">
        <article v-for="(feature, index) in features" :key="index">
          <p class="feature-level">Ур. {{ feature.level }}</p>
          <h5>{{ feature.title || 'Название особенности' }}</h5>
          <p class="class-prose">{{ feature.description || 'Описание особенности' }}</p>
        </article>
      </div>
    </section>
    <section v-if="card.subclass_label" class="class-subclass">
      <h4>{{ card.subclass_label }}</h4>
      <p>Вариант класса выбирается в редакторе персонажа, когда он доступен по уровню.</p>
    </section>
    <section v-if="card.roleplay" aria-label="Как отыгрывать">
      <h4>Как отыгрывать</h4>
      <p class="class-prose">{{ card.roleplay }}</p>
    </section>
  </div>
</template>

<style scoped>
.class-card-content {
  min-width: 0;
}
.class-portraits {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.class-portraits figure {
  margin: 0;
  min-height: 140px;
  display: grid;
  place-items: center;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--lavender);
}
.class-portraits img {
  display: block;
  width: 100%;
  height: 200px;
  object-fit: contain;
}
.class-prose {
  margin: 0.5rem 0 1rem;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.class-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 1rem 0;
}
.class-facts div {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  padding: 0.4rem 0.7rem;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--lavender);
  font-size: 0.85rem;
}
.class-facts dt {
  font-weight: 700;
}
.class-facts dt::after {
  content: ':';
}
.class-facts dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.class-card-content h4 {
  margin: 1.2rem 0 0.6rem;
  font: 600 1.12rem/1.4 var(--font-heading);
}
.class-features {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr));
  gap: 0.65rem;
}
.class-features article,
.class-subclass {
  min-width: 0;
  padding: 0.9rem;
  border: 1px solid var(--line);
  border-radius: 10px;
}
.class-features h5 {
  margin: 0;
  font: 700 0.95rem/1.4 var(--font-body);
  overflow-wrap: anywhere;
}
.class-features .class-prose {
  margin-bottom: 0;
  font-size: 0.9rem;
}
.feature-level {
  margin: 0 0 0.2rem;
  color: var(--accent);
  font-size: 0.8rem;
  font-weight: 700;
}
.class-subclass p {
  margin: 0.3rem 0 0;
  color: var(--muted);
}
</style>
