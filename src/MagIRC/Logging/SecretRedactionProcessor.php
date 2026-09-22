<?php

declare(strict_types=1);

namespace MagIRC\Logging;

use Monolog\LogRecord;

final class SecretRedactionProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: preg_replace('/((?:password|passwd|token|secret|session(?:_id)?|dsn)\s*[=:]\s*)[^\s,;]+/i', '$1[REDACTED]', $record->message) ?? $record->message,
            context: $this->redact($record->context),
        );
    }

    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/password|passwd|token|secret|session|dsn/i', (string) $key)) {
                $context[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }
        return $context;
    }
}
