kodus/tempkit
=============

This package implements a server-side strategy for temporary collection and
recovery of [PSR-7](http://www.php-fig.org/psr/psr-7/) `UploadedFile` objects.

You can use this service to implement controllers that collect uploaded files
posted asynchronously by a browser and return the temporary file UUIDs, then,
on completion, recover the uploaded files and move them to permanent storage.

The filename and MIME-type (as posted by the client) will be preserved. 

Unrecovered files are automatically flushed after a defined expiration period.

#### Refer to [`TempFileService`](src/TempFileService.php) for inline documentation.
