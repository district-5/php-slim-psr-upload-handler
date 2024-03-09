<?php

namespace District5\UploadHandler\Providers;


use Aws\S3\S3Client;
use District5\UploadHandler\Exception\UploadConfigException;
use District5\UploadHandler\Exception\UploadErrorException;
use District5\UploadHandler\Exception\UploadFileExistsException;
use District5\UploadHandler\UploadedDto;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Throwable;

/**
 * Class S3Provider
 * @package District5\UploadHandler\Providers
 */
class S3Provider extends ProviderAbstract
{
    /**
     * @var S3Client
     */
    private S3Client $client;

    /**
     * @param UploadedFile $file
     * @return UploadedDto
     * @throws UploadConfigException
     * @throws UploadFileExistsException
     * @throws UploadErrorException
     * @throws Throwable
     */
    protected function processFileFromUpload(UploadedFile $file): UploadedDto
    {
        try {
            $fileName = $this->getFileName($file);
            if ($fileName === null) {
                throw new UploadErrorException('File name was null');
            }

            $rawPath = $this->config['path'] ?? null;

            $mime = $file->getClientMediaType();
            $size = $file->getSize();
            $name = $file->getClientFilename();

            $s3Key = $this->getS3Key($fileName, $rawPath);

            $stream = $file->getStream();

            return $this->performUploadAndReturnDto($s3Key, $stream, $name, $fileName, $mime, $size, $rawPath);
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
                throw new RuntimeException(
                    sprintf('File does not exist: %s', $filePath)
                );
            }

            $fileName = $this->getFileName($filePath);
            if ($fileName === null) {
                throw new UploadErrorException('File name was null');
            }

            $rawPath = $this->config['path'] ?? null;
            $name = basename($filePath);
            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $filePath);
            $size = filesize($filePath);

            $s3Key = $this->getS3Key($fileName, $rawPath);

            $stream = (new StreamFactory())->createStreamFromFile($filePath);

            return $this->performUploadAndReturnDto($s3Key, $stream, $name, $fileName, $mime, $size, $rawPath);
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
        // check acl is allowed by aws s3
        $aclOptions = [
            'private',
            'public-read',
            'public-read-write',
            'authenticated-read',
            'aws-exec-read',
            'bucket-owner-read',
            'bucket-owner-full-control'
        ];
        if (!in_array($this->config['acl'], $aclOptions)) {
            throw new UploadConfigException(
                'Invalid ACL option for S3'
            );
        }

        // configure the s3 client
        $this->client = new S3Client([
            'version' => $this->config['version'],
            'region' => $this->config['region'],
            'credentials' => [
                'key' => $this->config['accessKey'],
                'secret' => $this->config['secretKey']
            ]
        ]);
        $this->client->registerStreamWrapper();
    }

    /**
     * Get an array of required config keys. No values, just the keys
     *
     * @return string[]
     */
    protected function getRequiredConfigKeys(): array
    {
        return [
            'bucket',
            'acl',
            'path',
            'region',
            'version',
            'accessKey',
            'secretKey'
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

    /**
     * @param string|null $fileName
     * @param mixed $rawPath
     * @return string|null
     * @throws UploadConfigException
     * @throws UploadFileExistsException
     */
    protected function getS3Key(string|null $fileName, string|null $rawPath): ?string
    {
        $s3Key = $fileName;
        if ($rawPath !== null) {
            $s3Key = rtrim($rawPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        }

        if ($this->getConfig('overwrite', true, false) === false && $this->client->doesObjectExist($this->config['bucket'], $s3Key)) {
            throw new UploadFileExistsException(
                sprintf('File already exists: %s', $fileName)
            );
        }
        return $s3Key;
    }

    /**
     * @param string|null $s3Key
     * @param StreamInterface $stream
     * @param string $name
     * @param string $fileName
     * @param bool|string $mime
     * @param bool|int $size
     * @param mixed $rawPath
     * @return UploadedDto
     */
    private function performUploadAndReturnDto(
        ?string         $s3Key,
        StreamInterface $stream,
        string          $name,
        string          $fileName,
        bool|string     $mime,
        bool|int        $size,
        string|null     $rawPath
    ): UploadedDto
    {
        $result = $this->client->putObject([
            'Bucket' => $this->config['bucket'],
            'Key' => $s3Key,
            'Body' => $stream,
            'ContentType' => $mime,
            'ACL' => $this->config['acl'],
            'ContentLength' => $size
        ]);
        // check if the file was uploaded
        if ($result['@metadata']['statusCode'] !== 200) {
            throw new RuntimeException(
                sprintf('Failed to upload file: %s', $fileName)
            );
        }

        // get the url if the file is public
        $url = null;
        if (str_contains($this->config['acl'], 'public') && isset($result['ObjectURL'])) {
            $url = $result['ObjectURL'];
        }

        return new UploadedDto(
            $this,
            $url,
            $rawPath,
            $name,
            $fileName,
            $mime,
            $size,
            $result->toArray(),
            true
        );
    }
}
