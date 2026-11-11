<?php

// Run with: docker compose exec -T php php tests/ClassCardCheck.php
// All fixtures are rolled back, including when a check fails.
declare(strict_types=1);

require __DIR__ . '/../config/environment.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

use app\controllers\catalog\{ArchiveApiController, CatalogEntryApiController};
use app\models\catalog\CatalogEntry;
use app\models\rules\RuleProfile;
use app\services\characters\CharacterEditor;
use app\services\rules\{ClassCard, EntryMechanics, Rulebook};

$config = require __DIR__ . '/../config/console.php';
$config['bootstrap'] = [];
$config['components']['response'] = ['class' => yii\web\Response::class];
new yii\console\Application($config);

function checkClass(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$transaction = Yii::$app->db->beginTransaction();
try {
    $profile = RuleProfile::findOne(['code' => 'standard_2024']);
    checkClass($profile !== null, 'Standard profile is required.');
    $controller = new CatalogEntryApiController('class-card-check', Yii::$app);
    $save = new ReflectionMethod($controller, 'saveEntry');
    $entry = new CatalogEntry();
    $entry->setAttributes([
        'type' => 'class',
        'title' => 'Проверка карточки класса',
        'body' => 'Класс для решительных героев.',
        'rule_profile_id' => $profile->id,
    ]);
    $entry->revision = 1;
    $card = [
        'primary_abilities' => ['str', 'con'],
        'roleplay' => 'Защищает союзников и не отступает.',
        'portraits' => [['url' => '/class.webp', 'alt' => 'Воин']],
        'features' => [['level' => 1, 'title' => 'Стойкость', 'description' => 'Ты выдерживаешь тяжёлые удары.']],
        'subclass_label' => 'Путь героя',
    ];
    $mechanics = EntryMechanics::empty();
    $mechanics['traits'] = [['kind' => 'weapon', 'value' => 'simple'], ['kind' => 'weapon', 'value' => 'martial']];
    $data = [
        'class_card' => $card,
        'class_hit_die_sides' => 12,
        'class_saving_throw_codes' => ['str', 'con'],
        'class_skill_choice_count' => 0,
        'class_skill_entry_ids' => [],
        'creation_rules' => ['grants' => [], 'choices' => [], 'hit_point_bonus' => 0],
        'mechanics' => $mechanics,
        'rulebook' => Rulebook::empty(),
    ];
    checkClass($save->invoke($controller, $entry, $data, true), 'Create failed: ' . json_encode($entry->getErrors(), JSON_UNESCAPED_UNICODE));
    $loaded = $controller->actionView($entry->id)['data'];
    checkClass($loaded['class_card'] == $card, 'Card fields must survive save/reload.');
    checkClass(
        array_column($loaded['mechanics']['traits'], 'value') === ['martial', 'simple'],
        'Class training must survive card save.',
    );
    $entry->body = 'Изменённое описание';
    checkClass($save->invoke($controller, $entry, [], false), 'Partial update failed.');
    checkClass((new ClassCard())->read($entry->id) == $card, 'Partial updates must preserve the class card.');
    $entry->is_published = true;
    checkClass($entry->save(), 'Class publication failed.');
    $public = (new ArchiveApiController('archive-check', Yii::$app))->actionView($entry->id)['data'];
    checkClass($public['class_card'] == $card, 'Public archive must expose the class card.');
    checkClass(
        $public['class_details']['hit_die_sides'] === 12
            && in_array('martial', array_column($public['class_details']['training'], 'value'), true),
        'Public card must expose real class details.',
    );
    $options = (new CharacterEditor())->options(['rule_profile_id' => $profile->id, 'class_entry_id' => $entry->id], 1);
    checkClass($options['origins']['class_entry_id']['class_card'] == $card, 'Class selection must expose the same card.');
    $invalid = $card;
    $invalid['features'][0]['level'] = 21;
    $entry->title = 'This must not be saved';
    checkClass(!$save->invoke($controller, $entry, ['class_card' => $invalid], false), 'Invalid feature level should be rejected.');
    checkClass(CatalogEntry::findOne($entry->id)->title !== $entry->title, 'Invalid class card must not partially save the entry.');
    foreach (['javascript:alert(1)', '//example.com/image.png', '/\\example.com/image.png', "https://example.com/\nimage.png"] as $url) {
        $invalid = $card;
        $invalid['portraits'][0]['url'] = $url;
        try {
            (new ClassCard())->validate($entry, $invalid);
            throw new RuntimeException('Unsafe class image URL was accepted.');
        } catch (InvalidArgumentException $expected) {
        }
    }
    echo "PASS: class card create/edit/reload, preserved mechanics, public and builder cards, atomic validation and image URLs.\n";
} finally {
    $transaction->rollBack();
}
