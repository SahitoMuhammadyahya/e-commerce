<?php

namespace EssenceStore\Exceptions;

use InvalidArgumentException;

class PublicationException extends InvalidArgumentException
{
    public bool $publicationError = true;
}
