<?php

declare(strict_types=1);

namespace app\services\users;

use Yii;

class SessionState
{
    public static function current(bool $rotateCsrf = false): array
    {
        $user = Yii::$app->user;
        $authenticated = !$user->isGuest;
        $forced = $authenticated && (bool) $user->identity->must_change_password;
        return [
            'authenticated' => $authenticated,
            'username' => $authenticated ? (string) $user->identity->username : null,
            'must_change_password' => $forced,
            'can_manage_catalog' => $authenticated && !$forced && $user->can('manageCatalog'),
            'can_manage_news' => $authenticated && !$forced && $user->can('manageNews'),
            'csrf_token' => Yii::$app->request->getCsrfToken($rotateCsrf),
            'login_url' => '/login',
            'logout_url' => '/api/v1/auth/logout',
            'return_url' => self::returnUrl($user->getReturnUrl('/cabinet')),
        ];
    }

    private static function returnUrl(string $url): string
    {
        if (!str_starts_with($url, '/') || str_starts_with($url, '//') || str_contains($url, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $url)
            || preg_match('~^/(?:site|api|login|signup|force-password-change)(?:[/?#]|$)~', $url)) {
            return '/cabinet';
        }
        return $url;
    }
}
