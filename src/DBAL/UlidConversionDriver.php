<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\DBAL;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class UlidConversionDriver extends AbstractDriverMiddleware
{
    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): Connection {
        return new UlidConversionConnection(parent::connect($params));
    }
}
