<?php

namespace District5\UploadHandler\Providers;


use District5\UploadHandler\Exception\UploadConfigException;
use District5\UploadHandler\Exception\UploadFileExistsException;
use District5\UploadHandler\UploadedDto;
use Google\Cloud\Storage\Bucket;
use Slim\Psr7\UploadedFile;
use Throwable;

/**
 * Class LocalFileStorageProvider
 * @package District5\UploadHandler\Providers
 */
class LocalFileStorageProvider extends ProviderAbstract
{
    /**
     * @var Bucket
     */
    private Bucket $bucket;

    /**
     * @param UploadedFile $file
     * @return UploadedDto
     * @throws UploadConfigException
     * @throws UploadFileExistsException
     * @throws Throwable
     */
    protected function processFile(UploadedFile $file): UploadedDto
    {
        try {
            $fileName = $this->getFileName($file);

            $rawPath = $this->config['path'];
            $path = rtrim($rawPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
            if ($this->getConfig('overwrite', true, false) === false && file_exists($path) === true) {
                throw new UploadFileExistsException(
                    sprintf('File already exists: %s', $fileName)
                );
            }

            $file->moveTo($path);

            return new UploadedDto(
                $this,
                null,
                $rawPath,
                $file->getClientFilename(),
                $fileName,
                $file->getClientMediaType(),
                $file->getSize(),
                pathinfo($path),
                true
            );
        } catch (Throwable $e) {
            if ($this->suppressException()) {
                return UploadedDto::createError($this, $e);
            }

            throw $e;
        }
    }

    /**
     * @return void
     * @throws UploadConfigException
     */
    protected function setupComplete(): void
    {
        $path = $this->config['path'];
        if (!is_dir($path)) {
            throw new UploadConfigException('Path does not exist');
        }
    }

    /**
     * Get an array of required config keys. No values, just the keys
     *
     * @return string[]
     */
    protected function getRequiredConfigKeys(): array
    {
        return [
            'path'
        ];
    }
}
