<?php

// Run with: docker compose exec -T php php tests/SpellCardCheck.php
// All fixtures are rolled back, including when a check fails.
declare(strict_types=1);

require __DIR__ . '/../config/environment.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

use app\controllers\catalog\{ArchiveApiController, CatalogEntryApiController};
use app\models\catalog\CatalogEntry;
use app\models\rules\RuleProfile;
use app\services\characters\CharacterEditor;
use app\services\rules\{ClassDefinitionWriter, EntryMechanics, Rulebook};

$config = require __DIR__ . '/../config/console.php';
$config['bootstrap'] = [];
$config['components']['response'] = ['class' => yii\web\Response::class];
new yii\console\Application($config);

function checkSpell(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$transaction = Yii::$app->db->beginTransaction();
try {
    $profile = RuleProfile::findOne(['code' => 'standard_2024']);
    checkSpell($profile !== null, 'Standard profile is required.');
    $controller = new CatalogEntryApiController('spell-check', Yii::$app);
    $save = new ReflectionMethod($controller, 'saveEntry');
    $make = static function (string $type, string $title) use ($profile): CatalogEntry {
        $entry = new CatalogEntry();
        $entry->setAttributes(['type' => $type, 'title' => $title, 'body' => "Эффект заклинания.\nНа более высоких уровнях — дополнительный эффект.", 'rule_profile_id' => $profile->id]);
        $entry->revision = 1;
        return $entry;
    };
    $class = $make('class', 'Тестовый класс заклинателя');
    checkSpell($class->save(), 'Class fixture failed.');
    (new ClassDefinitionWriter())->save($class, 6, ['int', 'wis']);
    $spell = $make('spell', 'Тестовое заклинание');
    $definition = ['level' => 1, 'school' => 'Воплощение', 'casting_time' => 'Реакция: при получении урона', 'range_text' => '60 футов', 'components' => 'Вербальный, Соматический', 'duration' => 'Мгновенная', 'concentration' => false, 'ritual' => true];
    $mechanics = EntryMechanics::empty();
    $mechanics['spell'] = $definition;
    $mechanics['class_ids'] = [$class->id];
    $book = Rulebook::empty();
    $book['ready'] = true;
    checkSpell($save->invoke($controller, $spell, ['mechanics' => $mechanics, 'rulebook' => $book], true), 'Spell create failed: ' . json_encode($spell->getErrors(), JSON_UNESCAPED_UNICODE));
    $loaded = $controller->actionView($spell->id)['data'];
    checkSpell($loaded['mechanics']['spell'] == $definition, 'Spell fields must survive save/reload.');
    checkSpell($loaded['mechanics']['class_ids'] === [$class->id], 'Spell class links must survive save/reload.');
    $loaded['mechanics']['spell']['duration'] = 'До 1 минуты';
    $loaded['mechanics']['spell']['concentration'] = true;
    checkSpell($save->invoke($controller, $spell, ['mechanics' => $loaded['mechanics']], false), 'Spell edit failed.');
    checkSpell($controller->actionView($spell->id)['data']['rulebook']['ready'], 'Editing a card must preserve hidden rule settings.');
    $spell->is_published = true;
    checkSpell($spell->save(), 'Spell publication failed.');
    $public = (new ArchiveApiController('archive-check', Yii::$app))->actionView($spell->id)['data'];
    checkSpell($public['spell']['duration'] === 'До 1 минуты' && $public['spell']['concentration'], 'Public archive must expose saved spell parameters.');
    checkSpell($public['body'] === $spell->body, 'Public archive must expose the complete effect text.');
    $source = $make('species', 'Тестовый источник магии');
    $choice = ['code' => 'spell_check', 'title' => 'Выберите заклинание', 'count' => 1, 'mode' => 'grant', 'entry_ids' => [$spell->id]];
    checkSpell($save->invoke($controller, $source, ['species_speed_feet' => 30, 'creation_rules' => ['grants' => [], 'choices' => [$choice], 'hit_point_bonus' => 0]], true), 'Source fixture failed.');
    $source->is_published = true;
    checkSpell($source->save(), 'Source publication failed.');
    $context = ['rule_profile_id' => $profile->id, 'species_entry_id' => $source->id];
    $state = (new CharacterEditor())->options($context, 1);
    $key = $source->id . ':spell_check';
    $group = array_values(array_filter($state['creation']['groups'], static fn($row) => $row['key'] === $key))[0];
    checkSpell($group['options'][0]['body'] === $spell->body && $group['options'][0]['spell']['level'] === 1, 'Card details must be available before selecting a spell.');
    $state = (new CharacterEditor())->options($context, 1, [$key => [$spell->id]]);
    $selected = array_values(array_filter($state['creation']['entries'], static fn($row) => $row['id'] === $spell->id))[0];
    checkSpell($selected['spell']['concentration'] && $selected['body'] === $spell->body, 'The selected spell must retain its card.');
    $invalid = $loaded['mechanics'];
    $invalid['spell']['level'] = 10;
    checkSpell(!$save->invoke($controller, $spell, ['mechanics' => $invalid], false), 'Invalid spell levels must be rejected.');
    checkSpell($controller->actionView($spell->id)['data']['mechanics']['spell']['level'] === 1, 'Invalid edits must preserve saved parameters.');
    echo "PASS: spell create/edit/reload, class links, hidden settings, public card, details before selection, selected spell, invalid input.\n";
} finally {
    $transaction->rollBack();
}
