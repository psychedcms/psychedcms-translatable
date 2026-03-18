<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * DBAL Middleware that wraps the driver to intercept SQL parameter binding.
 * Converts ULID base32 strings to UUID format for PostgreSQL compatibility.
 *
 * This fixes Gedmo TranslatableListener which passes entity identifiers
 * as ULID base32 strings (via __toString()) instead of UUID format when
 * binding parameters in DQL queries without type hints.
 */
final class UlidConversionMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new UlidConversionDriver($driver);
    }
}
