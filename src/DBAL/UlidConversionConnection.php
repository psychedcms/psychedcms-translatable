<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\DBAL;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Statement;

final class UlidConversionConnection extends AbstractConnectionMiddleware
{
    public function prepare(string $sql): Statement
    {
        return new UlidConversionStatement(parent::prepare($sql));
    }
}
