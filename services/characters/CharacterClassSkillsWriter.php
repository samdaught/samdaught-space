<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\characters\{Character, CharacterClassSkillChoice, CharacterClassSkill};
use yii\web\{ConflictHttpException, ForbiddenHttpException};

class CharacterClassSkillsWriter
{

    public function save(Character $character, array $ids, int $expectedRevision): void
    {
        Yii::$app->db->transaction(function () use ($character, $ids, $expectedRevision): void {
            $found = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%character}} WHERE [[id]] = :id FOR UPDATE', [':id' => $character->id])->queryScalar();
            $current = $found === false ? null : Character::findOne($found);
            if ($current === null || $current->class_entry_id !== $character->class_entry_id || $current->rule_profile_id !== $character->rule_profile_id) {
                throw new ConflictHttpException('Класс или профиль изменились. Загрузите актуальные данные.');
            }
            if ($current->status !== Character::STATUS_DRAFT) throw new ForbiddenHttpException('Навыки класса можно выбирать только у черновика.');
            $row = CharacterClassSkillChoice::findOne($character->id);
            $revision = $row === null ? 0 : $row->revision;
            if ($revision !== $expectedRevision) throw new ConflictHttpException('Навыки класса уже изменены. Загрузите актуальные данные.');
            if ($row === null) {
                $row = new CharacterClassSkillChoice();
                $row->character_id = $character->id;
            }
            $row->class_entry_id = $character->class_entry_id;
            $row->rule_profile_id = $character->rule_profile_id;
            $row->revision = $revision + 1;
            if (!$row->save()) throw new \RuntimeException('Не удалось сохранить контекст навыков класса.');
            CharacterClassSkill::deleteAll(['character_id' => $character->id]);
            foreach ($ids as $id) {
                $skill = new CharacterClassSkill();
                $skill->character_id = $character->id;
                $skill->skill_entry_id = $id;
                if (!$skill->save()) throw new \RuntimeException('Не удалось сохранить выбранные навыки класса.');
            }
        });
    }
}
