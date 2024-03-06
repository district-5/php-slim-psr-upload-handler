<?php

namespace District5\UploadHandler;


use DI\Container;
use District5\UploadHandler\Providers\GcsProvider;
use District5\UploadHandler\Providers\LocalFileStorageProvider;
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
    private array $handlerInstances = [];

    /**
     * @var Container
     */
    private Container $container;

    /**
     * @var array
     */
    private array $actionMap = [
        'gcs' => GcsProvider::class,
        'local' => LocalFileStorageProvider::class
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
     */
    public function handle(string $handlerName, UploadedFile|array $data): UploadedDto|array
    {
        if (!array_key_exists($handlerName, $this->handlers)) {
            throw new RuntimeException('Action not found');
        }

        $actionData = $this->handlers[$handlerName];
        $provider = $actionData['provider'];
        if (array_key_exists($handlerName, $this->handlerInstances)) {
            $handler = $this->handlerInstances[$handlerName];
        } else {
            if (!array_key_exists($provider, $this->actionMap)) {
                if (class_exists($provider) === false) {
                    throw new RuntimeException('Provider handler not found');
                } else {
                    $this->actionMap[$provider] = $provider;
                }
            }

            $this->handlerInstances[$handlerName] = new $this->actionMap[$provider]($handlerName, $actionData['config'], $this->options);
            $handler = $this->handlerInstances[$handlerName];
        }

        return $handler->handle($data);
    }
}