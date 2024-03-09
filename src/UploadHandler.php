<?php
/**
 * @noinspection PhpUnused
 */

namespace District5\UploadHandler;


use DI\Container;
use District5\UploadHandler\Exception\UploadConfigException;
use District5\UploadHandler\Exception\UploadErrorException;
use District5\UploadHandler\Providers\GcsProvider;
use District5\UploadHandler\Providers\LocalFileStorageProvider;
use District5\UploadHandler\Providers\ProviderInterface;
use District5\UploadHandler\Providers\S3Provider;
use RuntimeException;
use Slim\Psr7\UploadedFile;

/**
 * Class UploadHandler
 * @package District5\UploadHandler
 */
class UploadHandler
{
    /**
     * @var UploadHandler|null
     */
    private static UploadHandler|null $instance = null;

    /**
     * @var array
     */
    private array $handlers = [];

    /**
     * @var array
     */
    private array $options = [];

    /**
     * @var array
     */
    private array $providerInstances = [];

    /**
     * @var Container
     */
    private Container $container;

    /**
     * @var array
     */
    private array $handlerKeyToProviderMap = [
        'gcs' => GcsProvider::class,
        'local' => LocalFileStorageProvider::class,
        's3' => S3Provider::class
    ];

    /**
     * Protected to prevent construction.
     */
    protected function __construct()
    {
    }

    /**
     * @param Container $container
     * @param array $config
     * @return UploadHandler
     */
    public static function create(Container $container, array $config): UploadHandler
    {
        self::$instance = new UploadHandler();
        self::$instance->setContainer($container);
        self::$instance->applyConfig($config);
        return self::$instance;
    }

    /**
     * @param Container $container
     * @return void
     */
    private function setContainer(Container $container): void
    {
        $this->container = $container;
        $this->container->set(UploadHandler::class, function () {
            return self::$instance;
        });
    }

    /**
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @param array $config
     * @return void
     */
    private function applyConfig(array $config): void
    {
        $this->handlers = $config['handlers'];
        $this->options = $config['options'];
    }

    /**
     * @param string $handlerName
     * @param UploadedFile|UploadedFile[] $data
     * @return UploadedDto|UploadedDto[]
     * @throws UploadConfigException
     * @throws UploadErrorException
     */
    public function handleFromUpload(string $handlerName, UploadedFile|array $data): UploadedDto|array
    {
        if (!array_key_exists($handlerName, $this->handlers)) {
            throw new RuntimeException('Action not found');
        }

        $handler = $this->getHandlerInstance($handlerName);

        return $handler->handleFromUpload($data);
    }

    /**
     * @param string $handlerName
     * @param string|string[] $localFilePathOrPaths
     * @return UploadedDto|UploadedDto[]
     * @throws UploadConfigException
     * @throws UploadErrorException
     */
    public function handleFromLocal(string $handlerName, string|array $localFilePathOrPaths): UploadedDto|array
    {
        if (!array_key_exists($handlerName, $this->handlers)) {
            throw new RuntimeException('Action not found');
        }

        $handler = $this->getHandlerInstance($handlerName);

        return $handler->handleFromLocal($localFilePathOrPaths);
    }

    /**
     * @param string $handlerName
     * @return ProviderInterface
     */
    private function getHandlerInstance(string $handlerName): ProviderInterface
    {
        $actionData = $this->handlers[$handlerName];
        $provider = $actionData['provider'];
        if (!array_key_exists($handlerName, $this->providerInstances)) {
            if (!array_key_exists($provider, $this->handlerKeyToProviderMap)) {
                if (class_exists($provider) === false) {
                    throw new RuntimeException('Provider handler not found');
                } else {
                    $this->handlerKeyToProviderMap[$provider] = $provider;
                }
            }

            $this->providerInstances[$handlerName] = new $this->handlerKeyToProviderMap[$provider]($handlerName, $actionData['config'], $this->options);
        }

        return $this->providerInstances[$handlerName];
    }
}
