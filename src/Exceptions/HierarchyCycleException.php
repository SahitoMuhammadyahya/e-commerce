<?php

namespace EssenceStore\Exceptions;

use InvalidArgumentException;

class HierarchyCycleException extends InvalidArgumentException
{
    public bool $isCycle = true;
}
