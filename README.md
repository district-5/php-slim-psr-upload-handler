# District5 - Slim Framework PSR Upload Handler

### Introduction...

This is a simple upload handler for Slim Framework. It is designed to be used with the PSR interfaces and is compatible
with Slim 4.

### Installation...

```bash
composer require district5/slim-psr-upload-handler
```

### Supported Providers...

- Local File (Key: `local`)
    - This provider will save the file to a local directory.
    - Configuration:
        - `path` (string) - The directory to save the file to.
        - `overwrite` (bool) - Overwrite the file if it already exists.
        - `appendRandom` (bool) - Append a random string to the file name.
        - `suppressExceptions` (bool) - Ignore errors and return the UploadedDto object regardless. Overrides the global
          `suppressExceptions` option.
- Google Cloud Storage (Key: `gcs`)
    - This provider will save the file to Google Cloud Storage.
    - Configuration:
        - `projectId` (string) - The Google Cloud project id.
        - `bucket` (string) - Your Google Cloud Storage bucket name.
        - `path` (string) - The path within to bucket the file, for example 'uploads' or 'images'. Leave empty (or null) for root.
        - `keyFile` (array) - The JSON decoded (ie, array) version of service account key file.
        - `overwrite` (bool) - Overwrite the file if it already exists.
        - `appendRandom` (bool) - Append a random string to the file name.
        - `acl` (string) - The Google Cloud Storage object ACL.
        - `suppressExceptions` (bool) - Ignore errors and return the UploadedDto object regardless. Overrides the global
          `suppressExceptions` option.

### Usage...

```php
<?php
use \District5\UploadHandler\UploadHandler;
use \Slim\Psr7\Request;
use \Slim\Psr7\Response;

$container = new \Slim\Container();
// ... add any other dependencies to the container before or after the upload handler

$uploadHandlerConfig = [
    'options' => [ // these are the core library options. these are the defaults for all handlers, but can be overridden in the handler specific config
        'suppressExceptions' => false, // ignore errors and return the UploadedDto object regardless.
        'appendRandom' => true, // append a random string (using uniqid) to the file, or use the original name
    ],
    'handlers' => [
        'example-google-cloud-storage-provider' => [ // 'my-gcs-provider' is the action name, this can be anything you want
            'provider' => 'gcs', // Or use the class name of GcsProvider::class
            'config' => [
                'suppressExceptions' => false, // Override the global suppressExceptions option
                'appendRandom' => false, // append a random string (using uniqid) to the file, or use the original name.  Overrides the global appendRandom option
                'overwrite' => false, // overwrite the file if it already exists
                'projectId' => $env->get('GCS_PROJECT_ID'), // your GCP project id
                'bucket' => $env->get('GCS_BUCKET'), // your GCS bucket name
                'path' => $env->get('GCS_OBJECT_PATH'), // or null/empty string for root
                'keyFile' => json_decode($env->get('GCS_KEY_FILE_CONTENT'), true), // your GCP service account key file content,
                'acl' => $env->get('GCS_OBJECT_ACL'), // the GCS object ACL
            ]
        ],
        'example-local-file-provider' => [ // 'generic-file' is the action name, this can be anything you want
            'provider' => 'local', // Or use the class name of LocalFileProvider::class
            'config' => [
                'suppressExceptions' => false, // Override the global suppressExceptions option
                'appendRandom' => false, // append a random string (using uniqid) to the file, or use the original name. Overrides the global appendRandom option
                'overwrite' => false, // overwrite the file if it already exists
                'path' => $env->get('LOCAL_WRITABLE_DIRECTORY'), // the directory to save the file to (trailing slash is stripped)
            ]
        ]
    ]
];
UploadHandler::create($container, $uploadHandlerConfig);

$app = new \Slim\App($container);

$app->post('/upload', function (Request $request, Response $response, $args) {

    $file = $request->getUploadedFiles()['param-key'];
    /* @var $container \DI\Container */
    $uploadHandler = $container->get(UploadHandler::class);
    /* @var $uploadHandler UploadHandler */
    $result = $uploadHandler->handle($file, 'example-google-cloud-storage-provider');
    // or: $result = $uploadHandler->handle($file, 'example-local-file-provider');
    return $response->withJson($result);
});

```

### Creating your own provider...

To create your own provider, you need to extend the `District5\UploadHandler\Provider\ProviderAbstract` class.
The `ProviderAbstract` class provides a lot of the boilerplate code for you.

```php
<?php

use Slim\Psr7\UploadedFile;
use District5\UploadHandler\Providers\ProviderAbstract;
use District5\UploadHandler\UploadedDto;

/**
 * Class MyFileProvider
 */
class MyFileProvider extends ProviderAbstract
{
    /**
     * @param UploadedFile $file
     * @return UploadedDto
     */
    protected function processFile(UploadedFile $file): UploadedDto
    {
        try {
          $fileName = $file->getClientFilename();
          $newFileName = $this->getFileName($fileName);
          
          $localDirectory = $this->getConfig('path');
          $localPath = rtrim($localDirectory, DIRECTORY_SEPARATOR) . '/' . $newFileName;
          
          $file->moveTo($localPath);
          
          return new UploadedDto(
                  $this,
                  null,
                  $localPath,
                  $file->getClientFilename(),
                  $newFileName,
                  $file->getClientMediaType(),
                  $file->getSize(),
                  pathinfo($localPath),
                  true
              );
        } catch (Throwable $e) {
            if ($this->suppressException()) {
                return UploadedDto::createError($this, $e);
            }
            
            throw $e; // re-throw the exception
        }
    }


    /**
     * Get an array of required config keys. No values, just the keys.
     *
     * @return array
     */
    protected function getRequiredConfigKeys(): array
    {
        return ['path'];
    }
}
```

To use this provider, you would add it to the `handlers` array in the `UploadHandler` configuration.

```php
$uploadHandlerConfig = [
    'handlers' => [
        'my-file-provider' => [ // 'my-file-provider' is the action name, this can be anything you want
            'provider' => MyFileProvider::class, // the class name of your provider
            'config' => [ // the configuration for your provider
                'path' => '/tmp'
            ]
        ]
    ]
];
```