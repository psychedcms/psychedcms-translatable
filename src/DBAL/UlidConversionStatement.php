<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\DBAL;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Ulid;

/**
 * Intercepts bindValue() to convert ULID base32 strings to UUID format.
 *
 * Gedmo TranslatableListener extracts entity identifiers via __toString()
 * which produces ULID base32 (e.g. "01KM0VX3HZ8YM0772J1ZPBZWXF").
 * PostgreSQL UUID columns reject this format.
 *
 * This middleware detects ULID base32 strings and converts them to
 * RFC4122 UUID format (e.g. "019d01be-8e3f-47a8-039c-520fecbff3af").
 */
final class UlidConversionStatement extends AbstractStatementMiddleware
{
    public function bindValue(int|string $param, mixed $value, ParameterType $type = ParameterType::STRING): void
    {
        // Convert Ulid objects to UUID string (Gedmo passes entity IDs as objects)
        if ($value instanceof Ulid) {
            $value = $value->toRfc4122();
        }

        // Convert ULID base32 strings to UUID format
        if (\is_string($value) && \strlen($value) === 26 && Ulid::isValid($value)) {
            $value = Ulid::fromString($value)->toRfc4122();
        }

        parent::bindValue($param, $value, $type);
    }
}
