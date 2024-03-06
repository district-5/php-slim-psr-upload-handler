<?php

namespace District5\UploadHandler\Providers;


use District5\UploadHandler\Exception\UploadConfigException;
use District5\UploadHandler\Exception\UploadErrorException;
use District5\UploadHandler\UploadedDto;
use RuntimeException;
use Slim\Psr7\UploadedFile;

/**
 * Class ProviderAbstract
 * @package District5\UploadHandler\Providers
 */
abstract class ProviderAbstract implements ProviderInterface
{
    /**
     * @var string
     */
    protected string $handlerName;

    /**
     * @var array
     */
    protected array $config;

    /**
     * @param string $handlerName
     * @param array $config
     * @param array $options (the core library options array)
     */
    final public function __construct(string $handlerName, array $config, array $options)
    {
        $this->handlerName = $handlerName;
        $this->setup($config, $options);
    }

    /**
     * @param array $config
     * @param array $options (the core library options array)
     * @return void
     */
    protected function setup(array $config, array $options): void
    {
        foreach ($options as $key => $value) {
            if (!array_key_exists($key, $config)) {
                $config[$key] = $value;
            }
        }

        $requiredKeys = $this->getRequiredConfigKeys();
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                throw new RuntimeException(sprintf(
                    'Missing %s from config for handler "' . $this->handlerName . '"',
                    $key
                ));
            }
        }
        $optionalKeys = $this->getMergedOptionalConfigKeys();
        foreach ($optionalKeys as $key => $value) {
            if (!array_key_exists($key, $config)) {
                $config[$key] = $value;
            }
        }
        $this->config = $config;

        $this->setupComplete();
    }

    /**
     * @param UploadedFile $file
     * @return UploadedDto
     */
    abstract protected function processFileFromUpload(UploadedFile $file): UploadedDto;

    /**
     * @param string $filePath
     * @return UploadedDto
     */
    abstract protected function processFileFromLocal(string $filePath): UploadedDto;

    /**
     * @param UploadedFile $file
     * @return UploadedFile
     * @throws UploadErrorException
     */
    protected function checkFile(UploadedFile $file): UploadedFile
    {
        /* @var $file UploadedFile */
        if ($file->getError() !== UPLOAD_ERR_OK) {
            if ($file->getError() !== UPLOAD_ERR_INI_SIZE) {
                throw new UploadErrorException(sprintf(
                    '%s : File too large',
                    $this->handlerName
                ), UPLOAD_ERR_INI_SIZE);
            } elseif ($file->getError() !== UPLOAD_ERR_FORM_SIZE) {
                throw new UploadErrorException(sprintf(
                    '%s : File too large',
                    $this->handlerName
                ), UPLOAD_ERR_FORM_SIZE);
            } elseif ($file->getError() !== UPLOAD_ERR_PARTIAL) {
                throw new UploadErrorException(sprintf(
                    '%s : File was only partially uploaded',
                    $this->handlerName
                ), UPLOAD_ERR_PARTIAL);
            } elseif ($file->getError() !== UPLOAD_ERR_NO_FILE) {
                throw new UploadErrorException(sprintf(
                    '%s : No file was uploaded',
                    $this->handlerName
                ), UPLOAD_ERR_NO_FILE);
            } elseif ($file->getError() !== UPLOAD_ERR_NO_TMP_DIR) {
                throw new UploadErrorException(sprintf(
                    '%s : Missing a temporary folder',
                    $this->handlerName
                ), UPLOAD_ERR_NO_TMP_DIR);
            } elseif ($file->getError() !== UPLOAD_ERR_CANT_WRITE) {
                throw new UploadErrorException(sprintf(
                    '%s : Failed to write file to disk',
                    $this->handlerName
                ), UPLOAD_ERR_CANT_WRITE);
            } elseif ($file->getError() !== UPLOAD_ERR_EXTENSION) {
                throw new UploadErrorException(sprintf(
                    '%s : A PHP extension stopped the file upload',
                    $this->handlerName
                ), UPLOAD_ERR_EXTENSION);
            }
        }

        return $file;
    }

    /**
     * @param string|string[] $filePathOrPaths
     * @return UploadedDto|array
     * @throws UploadErrorException
     * @throws UploadConfigException
     */
    final public function handleFromLocal(array|string $filePathOrPaths): UploadedDto|array
    {
        if (is_array($filePathOrPaths)) {
            $response = [];
            foreach ($filePathOrPaths as $file) {
                $response[] = $this->handleFromLocal($file);
            }
            return $response;
        }

        return $this->processFileFromLocal(
            $filePathOrPaths
        );
    }

    /**
     * @param UploadedFile|UploadedFile[] $fileOrFiles
     * @return UploadedDto|UploadedDto[]
     * @throws UploadErrorException
     * @throws UploadConfigException
     */
    final public function handleFromUpload(UploadedFile|array $fileOrFiles): UploadedDto|array
    {
        if (is_array($fileOrFiles)) {
            $response = [];
            foreach ($fileOrFiles as $file) {
                $response[] = $this->handleFromUpload($file);
            }
            return $response;
        }

        return $this->processFileFromUpload(
            $this->checkFile($fileOrFiles)
        );
    }

    /**
     * Get an array of required config keys. No values, just the keys.
     *
     * @return array
     */
    abstract protected function getRequiredConfigKeys(): array;

    /**
     * Get an array of key => default value for optional config keys.
     *
     * @return array
     */
    protected function getOptionalConfigKeys(): array
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getMergedOptionalConfigKeys(): array
    {
        $optionalKeys = $this->getOptionalConfigKeys();
        return array_merge([
            'appendRandom' => true
        ], $optionalKeys);
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @param bool $required
     * @return mixed
     * @throws UploadConfigException
     */
    protected function getConfig(string $key, mixed $default = null, bool $required = true): mixed
    {
        if (!array_key_exists($key, $this->config)) {
            if ($required === false) {
                return null;
            }

            throw new UploadConfigException(sprintf(
                'Missing %s from config for handler "' . $this->handlerName . '"',
                $key
            ));
        }
        return $this->config[$key];
    }

    /**
     * @param UploadedFile $file
     * @return string|null
     */
    protected function getFileName(UploadedFile|string $file): ?string
    {
        if (is_string($file)) {
            $fileName = basename($file);
        } else {
            $fileName = $file->getClientFilename();
        }
        if ($this->config['appendRandom'] === true) {
            $unique = uniqid('', true);
            $baseName = explode('.', $fileName)[0];
            $restOfName = '';
            for ($i = 1; $i < count(explode('.', $fileName)); $i++) {
                $restOfName .= '.' . explode('.', $fileName)[$i];
            }
            $fileName = $baseName . '-' . $unique . $restOfName;
        }

        return $fileName;
    }

    /**
     * @return bool
     */
    protected function suppressException(): bool
    {
        if (array_key_exists('suppressExceptions', $this->config)) {
            return $this->config['suppressExceptions'];
        }

        return false;
    }

    /**
     * Called after construction and verification of required config keys
     * @return void
     */
    abstract protected function setupComplete(): void;
}
