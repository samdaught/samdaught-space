<?php

declare(strict_types=1);

namespace app\controllers\catalog;

use Yii;
use app\controllers\ApiController;
use app\models\catalog\CatalogEntry;
use app\models\rules\{RuleProfile, ProfileEntryOverride};
use app\services\rules\CatalogAvailability;
use yii\filters\{AccessControl, VerbFilter};
use yii\web\{BadRequestHttpException, NotFoundHttpException, ForbiddenHttpException, UnauthorizedHttpException, ConflictHttpException};

class ProfileAvailabilityApiController extends ApiController
{
    public function behaviors(): array
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [['allow' => true, 'roles' => ['manageCatalog']]],
                'denyCallback' => static function (): void {
                    if (Yii::$app->user->isGuest) throw new UnauthorizedHttpException('Войдите в аккаунт.');
                    throw new ForbiddenHttpException('Недостаточно прав для управления архивом.');
                }],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['view' => ['GET'], 'update' => ['PATCH']]],
        ];
    }

    public function actionView(int $id): array
    {
        return ['data' => $this->rows($this->entry($id))];
    }

    public function actionUpdate(int $id, int $profileId): array
    {
        $data = Yii::$app->request->getBodyParams();
        $modes = ['inherit', 'allow', 'deny'];
        if (!is_array($data) || count($data) !== 2 || !isset($data['mode'], $data['previous_mode'])
            || !in_array($data['mode'], $modes, true) || !in_array($data['previous_mode'], $modes, true)) {
            throw new BadRequestHttpException('Передайте mode и previous_mode: inherit, allow или deny.');
        }
        return Yii::$app->db->transaction(function () use ($id, $profileId, $data): array {

            $locked = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE', [':id' => $id])->queryScalar();
            if ($locked === false) throw new NotFoundHttpException('Запись архива не найдена.');
            $entry = $this->entry($id);
            $profile = RuleProfile::findOne($profileId);
            if ($profile === null) throw new NotFoundHttpException('Профиль не найден.');
            $availability = new CatalogAvailability();
            if ($data['mode'] === 'allow' && !$availability->hasCompatibleOrigin($entry, $profile)) {
                throw new BadRequestHttpException('Запись другого мира нельзя разрешить вне её профиля или наследования.');
            }
            $override = ProfileEntryOverride::findOne(['profile_id' => $profileId, 'catalog_entry_id' => $id]);
            $previous = $override === null ? 'inherit' : ($override->is_allowed ? 'allow' : 'deny');
            if ($previous !== $data['previous_mode']) throw new ConflictHttpException('Настройка уже изменена. Перезагрузите доступность.');
            if ($data['mode'] === 'inherit') {
                if ($override !== null && $override->delete() === false) throw new \RuntimeException('Не удалось удалить исключение.');
            } else {
                $override ??= new ProfileEntryOverride();
                $override->profile_id = $profileId;
                $override->catalog_entry_id = $id;
                $override->is_allowed = $data['mode'] === 'allow';
                if (!$override->save(false)) throw new \RuntimeException('Не удалось сохранить доступность.');
            }
            return ['data' => $this->rows($entry)];
        });
    }

    private function entry(int $id): CatalogEntry
    {
        $entry = CatalogEntry::findOne($id);
        if ($entry === null) throw new NotFoundHttpException('Запись архива не найдена.');
        return $entry;
    }

    private function rows(CatalogEntry $entry): array
    {
        $service = new CatalogAvailability();
        $rows = [];
        foreach (RuleProfile::find()->orderBy(['id' => SORT_ASC])->all() as $profile) {
            $override = ProfileEntryOverride::findOne(['profile_id' => $profile->id, 'catalog_entry_id' => $entry->id]);
            $rows[] = ['profile_id' => (int) $profile->id, 'title' => $profile->title,
                'mode' => $override === null ? 'inherit' : ($override->is_allowed ? 'allow' : 'deny'),
                'compatible_origin' => $service->hasCompatibleOrigin($entry, $profile),
                'available' => $service->isAvailableInProfile($entry, $profile)];
        }
        return $rows;
    }
}
