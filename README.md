Kodus/TempKit
=============

This package implements a server-side strategy for temporary collection and
recovery of [PSR-7](http://www.php-fig.org/psr/psr-7/) `UploadedFile` objects.

[![PHP Version](https://img.shields.io/badge/php-7.0%2B-blue.svg)](https://packagist.org/packages/kodus/tempkit)

You can use this service to implement controllers that collect uploaded files
posted asynchronously by a browser and return the temporary file UUIDs, then,
on completion, recover the uploaded files and move them to permanent storage.

The filename and MIME-type (as posted by the client) will be preserved. 

Unrecovered files are automatically flushed after a defined expiration period.

## Usage

Bootstrap the service:

```php
$service = new TempFileService(__DIR__ . '/temp');
```

In your asynchronous *file* post-controller, collect posted files and return UUIDs:

```php
$uuids = [];

foreach ($request->getUploadedFiles() as $file) {
    $uuids[] = $service->collect($file);
}

echo json_encode($uuids);
```

In your *form* post-controller, recover the collected files:

```php
foreach ($uuids as $uuid) {
    $file = $service->recover($uuid);

    // get information about recovered file:

    $filename = $file->getClientFilename();
    $media_type = $file->getClientMediaType();

    // move recovered file into permanent storage:

    $file->moveTo(__DIR__ . '/files/' . $file->getClientFilename());
}
```

Alternatively, obtain the path to the temporary file and move/copy the file by other means - for
example, to import an uploaded file into [FlySystem](https://flysystem.thephpleague.com/recipes/):

```php
$file = $service->recover($uuid);

$stream = fopen($file->getTempPath(), "r");

$filesystem->writeStream("uploads/" . $file->getClientFilename(), $stream);

fclose($stream);

$file->flush(); // optionally flush the temporary file after copying
```

Note that, if you don't flush the temporary file, it will of course be garbage-collected after
the defined expiration period.

Also, if you manually rename or move the temporary file, the JSON meta-data file will be collected
and flushed for you immediately when the `TempFile` instance is destroyed.

#### Refer to [`TempFileService`](src/TempFileService.php) for inline documentation.
