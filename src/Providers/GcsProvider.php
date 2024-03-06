<?php

namespace District5\UploadHandler\Providers;


use District5\UploadHandler\Exception\UploadConfigException;
use District5\UploadHandler\Exception\UploadFileExistsException;
use District5\UploadHandler\UploadedDto;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use RuntimeException;
use Slim\Psr7\UploadedFile;
use Throwable;

/**
 * Class GcsProvider
 * @package District5\UploadHandler\Providers
 */
class GcsProvider extends ProviderAbstract
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

            $rawPath = $this->config['path'] ?? null;
            if ($rawPath !== null) {
                $gcsPath = trim($rawPath, '/') . '/' . $fileName;
            } else {
                $gcsPath = $fileName;
            }
            $mime = $file->getClientMediaType();
            $size = $file->getSize();
            $name = $file->getClientFilename();

            if ($this->getConfig('overwrite', true, false) === false && $this->bucket->object($gcsPath)->exists()) {
                throw new UploadFileExistsException(
                    sprintf('File already exists: %s', $fileName)
                );
            }

            $object = $this->bucket->upload(
                $file->getStream(),
                [
                    'name' => $gcsPath,
                    'predefinedAcl' => $this->config['acl']
                ]
            );
            $moreInfo = $object->info();

            $url = null;
            if (str_contains($this->config['acl'], 'public')) {
                $url = 'https://storage.googleapis.com/' . $this->config['bucket'] . '/' . $gcsPath;
            }

            return new UploadedDto(
                $this,
                $url,
                $rawPath,
                $name,
                $fileName,
                $mime,
                $size,
                $moreInfo,
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
        $aclOptions = [
            'authenticatedRead', 'bucketOwnerFullControl', 'bucketOwnerRead', 'private', 'projectPrivate', 'publicRead'
        ];
        if (!in_array($this->config['acl'], $aclOptions)) {
            throw new UploadConfigException(
                'Invalid ACL option for GCS'
            );
        }

        $client = new StorageClient(
            [
                'projectId' => $this->config['projectId'] ?? throw new RuntimeException('Missing projectId from config for GCS'),
                'keyFile' => $this->config['keyFile'] ?? throw new RuntimeException('Missing keyFile from config for GCS')
            ]
        );
        $this->bucket = $client->bucket(
            $this->config['bucket']
        );
    }

    /**
     * Get an array of required config keys. No values, just the keys
     *
     * @return string[]
     */
    protected function getRequiredConfigKeys(): array
    {
        return [
            'projectId',
            'bucket',
            'acl',
            'path',
            'keyFile'
        ];
    }

    /**
     * Get an array of key => default value for optional config keys.
     *
     * @return array
     */
    protected function getOptionalConfigKeys(): array
    {
        return [
            'appendRandom' => true
        ];
    }
}
