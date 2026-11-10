<?php

declare(strict_types=1);

namespace app\helpers;

class DiagnosticLogTarget extends \yii\log\FileTarget
{
    public function formatMessage($message)
    {
        $message[0] = $this->safeValue($message[0]);
        return parent::formatMessage($message);
    }

    private function safeValue($value, int $depth = 0)
    {
        if ($depth > 8) return '[depth limit]';
        if ($value instanceof \Throwable) {
            $data = ['exception' => get_class($value), 'code' => $value->getCode(),
                'file' => $value->getFile(), 'line' => $value->getLine()];
            if ($value instanceof \yii\web\HttpException) $data['http_status'] = $value->statusCode;
            if ($value instanceof \yii\db\Exception && isset($value->errorInfo[0])) $data['sqlstate'] = $value->errorInfo[0];
            $data['trace'] = array_map(static fn(array $frame): array => array_intersect_key($frame,
                array_flip(['file', 'line', 'class', 'function'])), array_slice($value->getTrace(), 0, 20));
            if ($value->getPrevious() !== null) $data['previous'] = $this->safeValue($value->getPrevious(), $depth + 1);
            return $data;
        }
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $key => $item) {
                $safe[$key] = preg_match('/password|passwd|token|cookie|authorization|secret|csrf|_SERVER|_ENV|_POST|_SESSION/i', (string) $key)
                    ? '[redacted]' : $this->safeValue($item, $depth + 1);
            }
            return $safe;
        }
        if (is_object($value)) return '[object ' . get_class($value) . ']';
        if (!is_string($value)) return $value;
        foreach (['DB_PASSWORD', 'COOKIE_KEY'] as $key) {
            $secret = getenv($key);
            if (is_string($secret) && $secret !== '') $value = str_replace($secret, '[redacted]', $value);
        }
        return preg_replace('/((?:password|passwd|csrf|token|secret|authorization|cookie)[\w-]*\s*[=:]\s*)[^\r\n]+/i', '$1[redacted]', $value);
    }
}
