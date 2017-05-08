<?php

namespace litepubl\core\container\DI;

use Psr\Container\ContainerInterface;

interface ConstructorArgumentsInterface extends ContainerInterface
{
    public function set(string $className, array $args);
}
