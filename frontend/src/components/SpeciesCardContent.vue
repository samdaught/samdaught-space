<script setup lang="ts">
import { safePortraitUrl, type SpeciesCard } from '../api/speciesCard'

defineProps<{ card: SpeciesCard; body: string; title: string }>()
</script>

<template>
  <div class="species-card-content">
    <div v-if="card.portraits.length" class="species-portraits">
      <figure v-for="(portrait, index) in card.portraits" :key="index">
        <img
          v-if="safePortraitUrl(portrait.url)"
          :src="safePortraitUrl(portrait.url)"
          :alt="portrait.alt || `Представитель расы «${title}»`"
          loading="lazy"
          referrerpolicy="no-referrer"
          width="400"
          height="240"
        />
        <span v-else>Иллюстрация {{ index + 1 }}</span>
      </figure>
    </div>
    <p v-if="body" class="species-prose">{{ body }}</p>
    <dl v-if="card.age || card.height" class="species-facts">
      <div v-if="card.age">
        <dt>Возраст</dt>
        <dd>{{ card.age }}</dd>
      </div>
      <div v-if="card.height">
        <dt>Рост</dt>
        <dd>{{ card.height }}</dd>
      </div>
    </dl>
    <section v-if="card.features.length" aria-label="Особенности расы">
      <h4>Особенности</h4>
      <div class="species-features">
        <article v-for="(feature, index) in card.features" :key="index">
          <h5>{{ feature.title || 'Название особенности' }}</h5>
          <p class="species-prose">{{ feature.description || 'Описание особенности' }}</p>
        </article>
      </div>
    </section>
    <section v-if="card.roleplay" aria-label="Как отыгрывать">
      <h4>Как отыгрывать</h4>
      <p class="species-prose">{{ card.roleplay }}</p>
    </section>
  </div>
</template>

<style scoped>
.species-card-content {
  min-width: 0;
}
.species-portraits {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.species-portraits figure {
  margin: 0;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--lavender);
  overflow: hidden;
  display: grid;
  place-items: center;
  min-height: 140px;
}
.species-portraits img {
  display: block;
  width: 100%;
  height: 200px;
  object-fit: contain;
}
.species-prose {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  margin: 0.5rem 0 1rem;
}
.species-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 1rem 0;
}
.species-facts div {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  background: var(--lavender);
  border: 1px solid var(--line);
  padding: 0.4rem 0.7rem;
  border-radius: 18px;
  font-size: 0.85rem;
}
.species-facts dt {
  font-weight: 700;
}
.species-facts dt::after {
  content: ':';
}
.species-facts dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.species-features {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr));
  gap: 0.65rem;
}
.species-features article {
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 0.9rem;
  min-width: 0;
}
.species-card-content h4 {
  margin: 1.2rem 0 0.6rem;
  font: 600 1.12rem/1.4 var(--font-heading);
}
.species-card-content h5 {
  margin: 0;
  font: 700 0.9rem/1.4 var(--font-body);
  overflow-wrap: anywhere;
}
.species-features p {
  margin-bottom: 0;
  font-size: 0.9rem;
}
</style>
