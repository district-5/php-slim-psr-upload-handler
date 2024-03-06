<?php

namespace District5\UploadHandler\Exception;

use Exception;

/**
 * Class BaseException
 * @package District5\UploadHandler\Exception
 */
abstract class BaseException extends Exception
{
    /**
     * BaseException constructor.
     * @param string $message
     * @param string $code (optional) default is 0
     */
    public function __construct(string $message, string $code = '0')
    {
        parent::__construct($message, $code);
    }
}
