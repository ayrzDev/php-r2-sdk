# R2Manager

R2Manager is a PHP class for managing files in an S3-compatible object storage service. It provides methods for uploading, deleting, listing, and generating URLs for files stored in the bucket.

## Features

- Upload files to the bucket
- Delete files from the bucket
- List files in the bucket with an optional prefix
- Generate public URLs for files

## Requirements

- PHP 7.4 or higher
- cURL extension enabled

## Installation

Simply include the `R2Manager.php` file in your project.

```php
require_once 'R2Manager.php';
```

## Usage

### Initialization

Create an instance of the `R2Manager` class by passing a configuration array:

```php
$config = [
    'access_key' => 'your-access-key',
    'secret_key' => 'your-secret-key',
    'bucket' => 'your-bucket-name',
    'endpoint' => 'https://your-endpoint.com',
    'cdn_url' => 'https://your-cdn-url.com'
];

$r2Manager = new R2Manager($config);
```

### Upload a File

```php
$response = $r2Manager->upload('path/to/local/file.txt', 'remote/path/file.txt');
print_r($response);
```

### Delete a File

```php
$response = $r2Manager->delete('remote/path/file.txt');
print_r($response);
```

### List Files

```php
$fileList = $r2Manager->list('optional-prefix/');
print_r($fileList);
```

### Get File URL

```php
$url = $r2Manager->getUrl('remote/path/file.txt');
echo $url;
```

## Debugging

The class outputs debugging information such as request URLs, headers, and response status codes to help with troubleshooting.

## License

This project is licensed under the MIT License.
