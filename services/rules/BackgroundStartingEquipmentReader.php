<?php

declare(strict_types=1);

namespace app\services\rules;

use app\models\catalog\CatalogEntry;
use app\models\rules\{BackgroundStartingEquipment, BackgroundStartingEquipmentItem};

class BackgroundStartingEquipmentReader
{
    public function get(?CatalogEntry $background): ?array
    {
        $rule = $background === null ? null : BackgroundStartingEquipment::findOne($background->id);
        if ($rule === null) {
            return null;
        }
        return [
            'package_money_cp' => $rule->package_money_cp,
            'alternative_money_cp' => $rule->alternative_money_cp,
            'include_background_tool' => $rule->include_background_tool,
            'items' => BackgroundStartingEquipmentItem::find()->select(['item_entry_id', 'quantity'])
                ->where(['background_entry_id' => $background->id])->orderBy('item_entry_id')->asArray()->all(),
        ];
    }
}
