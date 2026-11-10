<?php

namespace app\services\users;

class LoginAttemptLimiter
{
    public function __construct(private readonly \yii\db\Connection $db) {}

    private const LIMITS = [
        'username' => ['capacity' => 8, 'secondsPerToken' => 75],
        'ip' => ['capacity' => 60, 'secondsPerToken' => 10],
        'change_user' => ['capacity' => 5, 'secondsPerToken' => 180],
        'signup_ip' => ['capacity' => 5, 'secondsPerToken' => 720],
    ];

    public function consume(string $scope, string $identifier): bool
    {
        $settings = self::LIMITS[$scope] ?? null;

        if ($settings === null) {
            throw new \InvalidArgumentException('Неизвестный тип ограничения');
        }

        $capacity = $settings['capacity'];
        $secondsPerToken = $settings['secondsPerToken'];

        return $this->db->transaction(
            function (\yii\db\Connection $db) use ($scope, $identifier, $capacity, $secondsPerToken): bool {
                $db->createCommand()->upsert(
                    '{{%login_rate_limit}}',
                    [
                        'scope' => $scope,
                        'identifier' => $identifier,
                        'tokens' => $capacity,
                        'updated_at' => time(),
                    ],
                    false
                )->execute();

                $row = $db->createCommand(
                    'SELECT tokens, updated_at
             FROM {{%login_rate_limit}}
             WHERE scope = :scope AND identifier = :identifier
             FOR UPDATE',
                    [
                        ':scope' => $scope,
                        ':identifier' => $identifier,
                    ]
                )->queryOne();

                if ($row === false) {
                    throw new \RuntimeException('Не удалось найти запись ограничения входа');
                }

                $now = time();
                $elapsed = max(0, $now - (int) $row['updated_at']);

                $available = min(
                    $capacity,
                    (float) $row['tokens'] + $elapsed / $secondsPerToken
                );

                if ($available < 1) {
                    return false;
                }

                $db->createCommand()->update(
                    '{{%login_rate_limit}}',
                    [
                        'tokens' => $available - 1,
                        'updated_at' => $now,
                    ],
                    [
                        'scope' => $scope,
                        'identifier' => $identifier,
                    ]
                )->execute();

                return true;
            }
        );
    }
}
