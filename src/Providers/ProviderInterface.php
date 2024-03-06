<?php

namespace District5\UploadHandler\Providers;


use District5\UploadHandler\Exception\UploadConfigException;
use District5\UploadHandler\Exception\UploadErrorException;
use District5\UploadHandler\UploadedDto;
use Slim\Psr7\UploadedFile;

/**
 * Interface ProviderInterface
 * @package District5\UploadHandler\Providers
 */
interface ProviderInterface
{
    /**
     * @param string $handlerName
     * @param array $config
     * @param array $options (the core library options array)
     */
    function __construct(string $handlerName, array $config, array $options);

    /**
     * @param UploadedFile|UploadedFile[] $fileOrFiles
     * @return UploadedDto|array
     * @throws UploadErrorException
     * @throws UploadConfigException
     */
    function handle(UploadedFile|array $fileOrFiles): UploadedDto|array;
}
