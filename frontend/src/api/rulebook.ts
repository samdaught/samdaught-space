export type RuleValue =
  null | string | number | boolean | RuleValue[] | { [key: string]: RuleValue }
export type RulebookData = Record<string, RuleValue>
export type RulebookOptions = {
  entries: { id: number; type: string; title: string; is_published: boolean }[]
  vocabulary: { kind: string; code: string; title: string; group_code: string }[]
  sources: { id: number; title: string }[]
  targets: string[]
  recovery: string[]
  features: string[]
}
export type Field = {
  title: string
  type: 'text' | 'number' | 'boolean' | 'select' | 'object' | 'list' | 'condition'
  nullable?: boolean
  values?: string[]
  relation?: string[]
  vocabulary?: string
  fields?: Record<string, Field>
  item?: Field
  initial?: RuleValue
  stringValue?: boolean
}
const text = (title: string): Field => ({ title, type: 'text' })
const number = (title: string, initial = 0, nullable = false): Field => ({
  title,
  type: 'number',
  initial,
  nullable,
})
const bool = (title: string, initial = false): Field => ({ title, type: 'boolean', initial })
const select = (title: string, values: string[], nullable = false): Field => ({
  title,
  type: 'select',
  values,
  nullable,
})
const ref = (title: string, relation: string[], nullable = false): Field => ({
  title,
  type: 'select',
  relation,
  nullable,
})
const vocab = (title: string, vocabulary: string, nullable = false): Field => ({
  title,
  type: 'select',
  vocabulary,
  nullable,
})
const object = (title: string, fields: Record<string, Field>, nullable = false): Field => ({
  title,
  type: 'object',
  fields,
  nullable,
})
const list = (title: string, item: Field): Field => ({ title, type: 'list', item })
export const abilities = ['str', 'dex', 'con', 'int', 'wis', 'cha']
export const recovery = ['none', 'short_rest', 'long_rest', 'dawn', 'initiative']
export const formula = object('Формула', {
  base: number('Постоянная'),
  terms: list(
    'Слагаемые',
    object('Слагаемое', {
      kind: select('Значение', [
        'ability_modifier',
        'proficiency_bonus',
        'character_level',
        'class_level',
      ]),
      code: select('Характеристика', abilities, true),
      entry_id: ref('Класс (пусто — текущий)', ['class'], true),
      multiplier: number('Множитель', 1),
      divisor: number('Делитель', 1),
      round: select('Округление', ['floor', 'ceil']),
    }),
  ),
  minimum: number('Минимум', 0, true),
  maximum: number('Максимум', 0, true),
})
const condition: Field = { title: 'Условие', type: 'condition' }
const spellFlags = {
  always_prepared: bool('Всегда подготовлено', true),
  count_against_limit: bool('Учитывать в лимите'),
  cast_with_slots: bool('Можно тратить ячейки', true),
  free_uses: { ...formula, title: 'Бесплатные применения' },
  recovery: select('Восстановление', recovery),
  cast_level: number('Круг применения (пусто — исходный)', 0, true),
}
export function rulebookSchema(category: string): Field {
  const fields: Record<string, Field> = {
    variant_required: bool('Вид требует выбора варианта'),
    subclass_level: number('Класс требует подкласс с уровня', 3),
    feature_kind: select('Вид особенности', [
      'general',
      'class_feature',
      'invocation',
      'metamagic',
      'maneuver',
      'item_plan',
    ]),
    repeatable: bool('Можно получать повторно'),
    distinct_choices: list('Какие выборы должны отличаться при повторе', text('Код группы')),
    prerequisites: { ...condition, title: 'Требования получения' },
    choices: list(
      'Выборы игрока',
      object('Выбор', {
        code: text('Код группы'),
        title: text('Название'),
        kind: select('Тип', [
          'ability',
          'size',
          'language',
          'spell_list',
          'mastery',
          'weapon_mastery',
          'proficiency',
        ]),
        count: number('Количество', 1),
        values: list('Допустимые значения', text('Код значения или ID записи')),
      }),
    ),
    casting_ability_code: select('Магическая характеристика таблицы развития', abilities, true),
    spell_list_class_ids: list(
      'Использовать списки заклинаний других классов',
      ref('Класс', ['class']),
    ),
    casting_choice_code: { ...text('Код выбора магической характеристики'), nullable: true },
    effects: list(
      'Расчётные эффекты',
      object('Эффект', {
        target: select('Цель', [
          'ability_str',
          'ability_dex',
          'ability_con',
          'ability_int',
          'ability_wis',
          'ability_cha',
          'hit_points',
          'armor_class',
          'initiative',
          'speed',
          'spell_attack',
          'spell_save',
          'skill',
          'saving_throw',
          'saving_throw_str',
          'saving_throw_dex',
          'saving_throw_con',
          'saving_throw_int',
          'saving_throw_wis',
          'saving_throw_cha',
          'weapon_attack',
          'weapon_damage',
          'cantrip_count',
          'known_count',
          'prepared_count',
          'ability_cap',
          'ability_choice',
          'ability_cap_choice',
        ]),
        target_entry_id: ref('Навык / оружие', ['skill', 'item'], true),
        choice_code: { ...text('Код выбора характеристики'), nullable: true },
        formula,
        condition,
      }),
    ),
    unlocks: list(
      'Выдачи по уровням',
      object('Выдача', {
        entry_id: ref('Запись', ['feature', 'feat', 'spell', 'skill', 'item', 'species_variant']),
        level: number('С уровня', 1),
        level_basis: select('Уровень', ['character', 'class']),
      }),
    ),
    spell_grants: list(
      'Фиксированные заклинания',
      object('Заклинание', {
        code: text('Код выдачи'),
        spell_id: ref('Заклинание', ['spell']),
        level: number('С уровня', 1),
        level_basis: select('Уровень', ['character', 'class']),
        ...spellFlags,
      }),
    ),
    spell_picks: list(
      'Выбор заклинаний из списка',
      object('Группа магии', {
        code: text('Код группы'),
        title: text('Название'),
        class_ids: list('Списки классов', ref('Класс', ['class'])),
        list_choice_code: {
          ...text('Код выбора списка вместо фиксированных классов'),
          nullable: true,
        },
        spell_level: number('Круг заклинания'),
        count: number('Количество', 1),
        ability_choice_code: { ...text('Код выбора характеристики'), nullable: true },
        ability_code: select('Фиксированная характеристика', abilities, true),
        ...spellFlags,
      }),
    ),
    resources: list(
      'Ресурсы',
      object('Ресурс', {
        code: text('Код ресурса'),
        title: text('Название'),
        maximum: { ...formula, title: 'Максимум' },
        recovery: select('Восстановление', recovery),
        level: number('С уровня', 1),
      }),
    ),
    sources: list(
      'Ссылки на книги и редакции',
      object('Ссылка', {
        source_id: ref('Книга', ['source']),
        page: text('Страница'),
        url: text('Ссылка'),
        revision: text('Редакция / исправление'),
      }),
    ),
    ready: bool('Механика записи заполнена'),
  }
  if (['subclass', 'species_variant'].includes(category))
    Object.assign(fields, {
      parent_entry_id: ref('Родитель', [category === 'subclass' ? 'class' : 'species']),
      selection_level: number('Уровень выбора', category === 'subclass' ? 3 : 1),
    })
  if (['class', 'subclass'].includes(category))
    fields.progression = list(
      'Развитие класса 1–20',
      object('Уровень', {
        level: number('Уровень', 1),
        proficiency_bonus: number('Бонус мастерства', 2),
        kind: select('Магия', ['none', 'spellcasting', 'pact']),
        cantrip_count: number('Заговоры'),
        known_count: number('Изученные'),
        prepared_count: number('Подготовленные'),
        pact_level: number('Круг магии договора'),
        recovery: select('Восстановление', recovery),
        change_on: select('Смена списка', ['level', 'long_rest', 'short_rest']),
        slots: list(
          'Ячейки',
          object('Ячейки', { level: number('Круг', 1), count: number('Количество') }),
        ),
      }),
    )
  if (category === 'item')
    fields.item = object(
      'Параметры предмета',
      {
        cost_cp: number('Стоимость в медных'),
        weight_grams: number('Вес в граммах'),
        weapon: object(
          'Оружие',
          {
            category: select('Категория', [
              'simple_melee',
              'simple_ranged',
              'martial_melee',
              'martial_ranged',
            ]),
            damage_count: number('Число костей', 1),
            damage_die: { ...select('Кость', ['1', '4', '6', '8', '10', '12', '20']), initial: 8 },
            damage_type: vocab('Тип урона', 'damage'),
            properties: list('Свойства', vocab('Свойство', 'weapon_property')),
            mastery: vocab('Мастерство', 'mastery', true),
            range_normal: number('Обычная дистанция'),
            range_long: number('Предельная дистанция'),
            versatile_die: number('Универсальная кость', 0, true),
          },
          true,
        ),
        armor: object(
          'Доспехи',
          {
            category: vocab('Категория', 'armor'),
            strength_min: number('Требование Силы'),
            stealth_disadvantage: bool('Помеха Скрытности'),
          },
          true,
        ),
        magic: object(
          'Магический предмет',
          {
            rarity: select('Редкость', [
              'common',
              'uncommon',
              'rare',
              'very_rare',
              'legendary',
              'artifact',
            ]),
            attunement: bool('Требуется настройка'),
            charges: { ...formula, title: 'Заряды' },
            recovery: select('Восстановление', recovery),
            plan_min_level: number('Минимальный уровень плана', 1),
            prerequisites: condition,
          },
          true,
        ),
      },
      true,
    )
  return object('Правила книги', fields)
}
export function initial(field: Field): RuleValue {
  if (field.nullable) return null
  if (field.initial !== undefined) return field.initial
  if (field.type === 'list') return []
  if (field.type === 'condition') return { op: 'all', conditions: [] }
  if (field.type === 'object')
    return Object.fromEntries(
      Object.entries(field.fields ?? {}).map(([key, value]) => [key, initial(value)]),
    )
  if (field.type === 'boolean') return false
  if (field.type === 'number') return 0
  return field.values?.[0] ?? (field.relation ? 0 : '')
}
export function emptyRulebook(): RulebookData {
  return {
    parent_entry_id: null,
    variant_required: false,
    subclass_level: 3,
    selection_level: 1,
    feature_kind: 'general',
    repeatable: false,
    distinct_choices: [],
    prerequisites: { op: 'all', conditions: [] },
    effects: [],
    choices: [],
    casting_choice_code: null,
    casting_ability_code: null,
    spell_list_class_ids: [],
    unlocks: [],
    progression: [],
    spell_grants: [],
    spell_picks: [],
    resources: [],
    item: null,
    sources: [],
    ready: false,
  }
}
export async function getRulebookOptions(profile: number | null): Promise<RulebookOptions> {
  const response = await fetch(
    '/api/v1/rulebook/options' + (profile === null ? '' : '?origin_profile_id=' + profile),
    { credentials: 'same-origin' },
  )
  if (!response.ok) throw new Error('Не удалось загрузить правила книги')
  return (await response.json()).data
}

export const ruleLabels: Record<string, string> = {
  str: 'Сила',
  dex: 'Ловкость',
  con: 'Телосложение',
  int: 'Интеллект',
  wis: 'Мудрость',
  cha: 'Харизма',
  none: 'Не используется',
  short_rest: 'Короткий отдых',
  long_rest: 'Продолжительный отдых',
  dawn: 'Рассвет',
  initiative: 'Инициатива',
  general: 'Обычная особенность',
  class_feature: 'Особенность класса',
  invocation: 'Воззвание',
  metamagic: 'Метамагия',
  maneuver: 'Манёвр',
  item_plan: 'План предмета',
  ability: 'Характеристика',
  size: 'Размер',
  language: 'Язык',
  spell_list: 'Список заклинаний',
  mastery: 'Свойство мастерства',
  weapon_mastery: 'Мастерство выбранного оружия',
  proficiency: 'Владение',
  level: 'Уровень персонажа',
  class_level: 'Уровень класса',
  character: 'Персонаж',
  class: 'Класс',
  spellcasting: 'Заклинательство',
  pact: 'Магия договора',
  ability_modifier: 'Модификатор характеристики',
  proficiency_bonus: 'Бонус мастерства',
  character_level: 'Уровень персонажа',
  floor: 'Вниз',
  ceil: 'Вверх',
  entry: 'Наличие записи',
  armor: 'Доспехи',
  choice: 'Значение выбора',
  common: 'Обычный',
  uncommon: 'Необычный',
  rare: 'Редкий',
  very_rare: 'Очень редкий',
  legendary: 'Легендарный',
  artifact: 'Артефакт',
  simple_melee: 'Простое рукопашное',
  simple_ranged: 'Простое дальнобойное',
  martial_melee: 'Воинское рукопашное',
  martial_ranged: 'Воинское дальнобойное',
  hit_points: 'Максимум хитов',
  armor_class: 'Класс доспеха',
  speed: 'Скорость',
  spell_attack: 'Атака заклинанием',
  spell_save: 'СЛ заклинаний',
  skill: 'Бонус навыка',
  saving_throw: 'Все спасброски',
  weapon_attack: 'Атака оружием',
  weapon_damage: 'Урон оружия',
  cantrip_count: 'Число заговоров',
  known_count: 'Число изученных заклинаний',
  prepared_count: 'Число подготовленных заклинаний',
  ability_cap: 'Предел всех характеристик',
  ability_choice: 'Увеличение выбранной характеристики',
  ability_cap_choice: 'Предел выбранной характеристики',
}
