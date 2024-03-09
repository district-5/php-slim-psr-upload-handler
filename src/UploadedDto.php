<?php
/**
 * @noinspection PhpUnused
 */
namespace District5\UploadHandler;

use District5\UploadHandler\Providers\ProviderAbstract;
use Exception;
use Throwable;

/**
 * Class UploadedDto
 * @package District5\UploadHandler
 */
class UploadedDto
{
    /**
     * @var string|null
     */
    public string|null $url;

    /**
     * @var string|null
     */
    public string|null $path;

    /**
     * @var string|null
     */
    public string|null $originalName;

    /**
     * @var string|null
     */
    public string|null $newName;

    /**
     * @var string|bool|null
     */
    public string|bool|null $mimeType;

    /**
     * @var int|null
     */
    public int|null $size;

    /**
     * @var array
     */
    public array $extra;

    /**
     * @var bool
     */
    public bool $success;

    /**
     * @var string|null
     */
    public string|null $errorMessage;

    /**
     * @var ProviderAbstract
     */
    public ProviderAbstract $handler;

    /**
     * @param ProviderAbstract $handler
     * @param string|null $url
     * @param string|null $path
     * @param string|null $originalName
     * @param string|null $newName
     * @param string|bool|null $mimeType
     * @param int|null $size
     * @param array $extra
     * @param bool $wasSuccessful
     * @param string|null $errorMessage
     */
    public function __construct(
        ProviderAbstract $handler,
        string|null      $url = null,
        string|null      $path = null,
        string|null      $originalName = null,
        string|null      $newName = null,
        string|bool|null $mimeType = null,
        int|null         $size = null,
        array            $extra = [],
        bool             $wasSuccessful = false,
        string           $errorMessage = null
    )
    {
        $this->handler = $handler;
        $this->url = $url;
        $this->path = $path;
        $this->originalName = $originalName;
        $this->newName = $newName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->extra = $extra;
        $this->success = $wasSuccessful;
        $this->errorMessage = $errorMessage;
    }

    /**
     * @param ProviderAbstract $provider
     * @param Throwable|Exception $e
     * @return UploadedDto
     */
    public static function createError(ProviderAbstract $provider, Throwable|Exception $e): UploadedDto
    {
        return new UploadedDto(
            $provider,
            null,
            null,
            null,
            null,
            null,
            null,
            [],
            false,
            $e->getMessage()
        );
    }

    /**
     * @return bool
     */
    public function wasSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * @return bool
     */
    public function wasError(): bool
    {
        return !$this->wasSuccessful();
    }

    /**
     * @return string|null
     */
    public function getErrorMessage(): string|null
    {
        return $this->errorMessage;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        if ($this->wasSuccessful() === false) {
            return [
                'success' => false,
                'error' => $this->errorMessage
            ];
        }

        return [
            'url' => $this->url,
            'path' => $this->path,
            'originalName' => $this->originalName,
            'newName' => $this->newName,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'extra' => $this->extra,
            'success' => $this->success,
            'error' => $this->errorMessage
        ];
    }
}
