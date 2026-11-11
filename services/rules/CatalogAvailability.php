<?php

declare(strict_types=1);

namespace app\services\rules;

use app\models\catalog\CatalogEntry;
use app\models\rules\RuleProfile;

/**
 * A catalog record belongs to exactly one world. Reusing a standard record in a
 * master world is an explicit copy/import operation, never implicit inheritance.
 */
class CatalogAvailability
{
    public function hasCompatibleOrigin(CatalogEntry $entry, RuleProfile $profile): bool
    {
        return (int) $entry->rule_profile_id === (int) $profile->id;
    }

    public function isAvailableInProfile(CatalogEntry $entry, RuleProfile $profile): bool
    {
        return $this->hasCompatibleOrigin($entry, $profile);
    }
}
