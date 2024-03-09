<?php

namespace District5\UploadHandler\Providers;


use District5\UploadHandler\Exception\UploadConfigException;
use District5\UploadHandler\Exception\UploadErrorException;
use District5\UploadHandler\Exception\UploadFileExistsException;
use District5\UploadHandler\UploadedDto;
use Slim\Psr7\UploadedFile;
use Throwable;

/**
 * Class LocalFileStorageProvider
 * @package District5\UploadHandler\Providers
 */
class LocalFileStorageProvider extends ProviderAbstract
{
    /**
     * @param UploadedFile $file
     * @return UploadedDto
     * @throws UploadConfigException
     * @throws UploadFileExistsException
     * @throws Throwable
     */
    protected function processFileFromUpload(UploadedFile $file): UploadedDto
    {
        try {
            $fileName = $this->getFileName($file);
            $rawPath = $this->config['path'];

            $path = $this->establishPath($fileName, $rawPath);

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
     * @param string $filePath
     * @return UploadedDto
     * @throws UploadConfigException
     * @throws UploadFileExistsException
     * @throws UploadErrorException
     * @throws Throwable
     */
    protected function processFileFromLocal(string $filePath): UploadedDto
    {
        try {
            if (!file_exists($filePath)) {
                throw new UploadFileExistsException(
                    sprintf('File does not exist: %s', $filePath)
                );
            }

            $fileName = $this->getFileName($filePath);
            $rawPath = $this->config['path'];

            $path = $this->establishPath($fileName, $rawPath);

            if ($this->getConfig('overwrite', true, false) === false && file_exists($path) === true) {
                throw new UploadFileExistsException(
                    sprintf('File already exists: %s', $fileName)
                );
            }
            $baseName = basename($filePath);
            $mime = mime_content_type($filePath);
            $size = filesize($filePath);

            copy($filePath, $path);

            return new UploadedDto(
                $this,
                null,
                $rawPath,
                $baseName,
                $fileName,
                $mime,
                $size,
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

    /**
     * @return true[]
     */
    protected function getOptionalConfigKeys(): array
    {
        return [
            'appendRandom' => true
        ];
    }

    /**
     * @param string|null $fileName
     * @param string|null $rawPath
     * @return string|null
     * @throws UploadErrorException
     */
    private function establishPath(string|null $fileName, string|null $rawPath): string|null
    {
        if ($fileName === null) {
            throw new UploadErrorException('File name was null');
        }

        $path = $fileName;
        if ($rawPath !== null) {
            $path = rtrim($rawPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        }

        return $path;
    }
}
