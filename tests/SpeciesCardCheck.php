<?php

// Run against a migrated development database: docker compose exec -T php php tests/SpeciesCardCheck.php
// All test records are rolled back, including when a check fails.
declare(strict_types=1);

require __DIR__ . '/../config/environment.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

use app\controllers\catalog\{ArchiveApiController, CatalogEntryApiController};
use app\models\catalog\CatalogEntry;
use app\models\rules\RuleProfile;
use app\services\rules\{EntryMechanics, Rulebook, SpeciesCard};
use app\services\characters\CharacterEditor;

$config = require __DIR__ . '/../config/console.php';
$config['bootstrap'] = [];
$config['components']['response'] = ['class' => yii\web\Response::class];
new yii\console\Application($config);

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$transaction = Yii::$app->db->beginTransaction();
try {
    $profile = RuleProfile::findOne(['code' => 'standard_2024']);
    check($profile !== null, 'Standard profile is required.');
    $controller = new CatalogEntryApiController('species-card-check', Yii::$app);
    $save = new ReflectionMethod($controller, 'saveEntry');
    $card = [
        'age' => 'Взрослеют к 20 годам', 'height' => '150–190 см',
        'roleplay' => "Любопытство и амбиции.\nОставить след в истории.",
        'portraits' => [['url' => '/archive.webp', 'alt' => 'Представитель расы']],
        'features' => [['title' => 'Находчивый', 'description' => 'После продолжительного отдыха получаешь вдохновение.']],
    ];
    $entry = new CatalogEntry();
    $entry->setAttributes(['type' => 'species', 'title' => 'Проверка карточки расы', 'body' => 'Описание расы.', 'rule_profile_id' => $profile->id]);
    $entry->revision = 1;
    $mechanics = EntryMechanics::empty();
    $mechanics['traits'] = [['kind' => 'size', 'value' => 'medium'], ['kind' => 'language', 'value' => 'Общий']];
    $data = ['species_speed_feet' => 30, 'species_card' => $card, 'mechanics' => $mechanics, 'rulebook' => Rulebook::empty(), 'creation_rules' => ['grants' => [], 'choices' => [], 'hit_point_bonus' => 0]];
    check($save->invoke($controller, $entry, $data, true), 'Create failed: ' . json_encode($entry->getErrors(), JSON_UNESCAPED_UNICODE));
    $loaded = $controller->actionView($entry->id)['data'];
    check($loaded['species_card'] == $card, 'Card fields must survive save/reload.');
    check($loaded['mechanics']['traits'] === [['kind' => 'language', 'value' => 'Общий'], ['kind' => 'size', 'value' => 'medium']], 'Existing mechanics must survive.');
    $entry->body = 'Изменённое описание';
    check($save->invoke($controller, $entry, [], false), 'Partial update failed.');
    check((new SpeciesCard())->read($entry->id) == $card, 'Omitting card from a partial update must preserve it.');
    $entry->is_published = true;
    check($entry->save(), 'Publishing test entry failed.');
    $public = (new ArchiveApiController('archive-check', Yii::$app))->actionView($entry->id)['data'];
    check($public['species_card'] == $card, 'Public archive must expose the saved card.');
    $options = (new CharacterEditor())->options(['rule_profile_id' => $profile->id, 'species_entry_id' => $entry->id], 1);
    check($options['origins']['species_entry_id']['species_card'] == $card, 'Character species selection must expose the same card.');
    $invalid = $card;
    $invalid['features'][0]['title'] = '';
    $entry->title = 'This must not be saved';
    check(!$save->invoke($controller, $entry, ['species_card' => $invalid], false), 'Incomplete feature should be rejected.');
    check(CatalogEntry::findOne($entry->id)->title !== $entry->title, 'Invalid card must not partially save the entry.');
    check((new SpeciesCard())->read($entry->id) == $card, 'Invalid update must preserve saved content.');

    foreach (['javascript:alert(1)', '//example.com/image.png', '/\\example.com/image.png', "https://example.com/\nimage.png", 'data:image/svg+xml,test'] as $url) {
        $invalid = $card;
        $invalid['portraits'][0]['url'] = $url;
        try {
            (new SpeciesCard())->validate($entry, $invalid);
            throw new RuntimeException('Unsafe image URL was accepted.');
        } catch (InvalidArgumentException $expected) {
        }
    }
    $other = new CatalogEntry();
    $other->type = 'class';
    try {
        (new SpeciesCard())->validate($other, $card);
        throw new RuntimeException('A class must not accept species card fields.');
    } catch (InvalidArgumentException $expected) {
    }
    check((new SpeciesCard())->read(2147483647) === null, 'Entries without cards must retain the legacy fallback.');
    echo "PASS: card round-trip, partial updates, mechanics preservation, public archive, character selection, atomic validation, image URLs, category validation, legacy fallback.\n";
} finally {
    $transaction->rollBack();
}
