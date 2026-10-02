<?php

namespace EssenceStore\Exceptions;

use InvalidArgumentException;

class ValidationException extends InvalidArgumentException
{
    public bool $isValidation = true;
}
