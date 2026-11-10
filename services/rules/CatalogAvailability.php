<?php

declare(strict_types=1);

namespace app\services\rules;

use app\models\catalog\CatalogEntry;
use app\models\rules\ProfileEntryOverride;
use app\models\rules\RuleProfile;

class CatalogAvailability
{
    public function hasCompatibleOrigin(CatalogEntry $entry, RuleProfile $profile): bool
    {
        return (int) $entry->origin_profile_id === (int) $profile->id
            || ($profile->base_profile_id !== null && (int) $entry->origin_profile_id === (int) $profile->base_profile_id);
    }

    public function isAvailableInProfile(CatalogEntry $entry, RuleProfile $profile): bool
    {
        $isInherited = $profile->base_profile_id !== null
            && (int) $entry->origin_profile_id === (int) $profile->base_profile_id;

        if (!$this->hasCompatibleOrigin($entry, $profile)) {
            return false;
        }

        $localOverride = ProfileEntryOverride::findOne([
            'profile_id' => $profile->id,
            'catalog_entry_id' => $entry->id,
        ]);

        if ($localOverride !== null) {
            return (bool) $localOverride->is_allowed;
        }

        if ($isInherited) {
            $baseOverride = ProfileEntryOverride::findOne([
                'profile_id' => $profile->base_profile_id,
                'catalog_entry_id' => $entry->id,
            ]);

            if ($baseOverride !== null) {
                return (bool) $baseOverride->is_allowed;
            }
        }

        return true;
    }
}
