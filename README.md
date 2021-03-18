selectel-storage-php-class
==========================

composer.json

```json

"require": {
    "axelpal/selectel-storage": "~2.0",
}

```

```php

<?php
    
require_once("vendor/autoload.php");
$selectelStorage = new axelpal\selectel\storage\SelectelStorage("user", "pass");

```

### Create Container 
```php
$container = $selectelStorage->createContainer('selectel', array("X-Container-Meta-Type: public"));
// get container info
$container->getInfo();
```

### Containers list
```php
$containerList = $selectelStorage->listContainers();
```

### Create directory
```php
$container->createDirectory('php/test');
```

### List
```php
$dirList = $container->listFiles($limit = 10000, $marker = null, $prefix = null, $path = "");
// files
$fileList = $container->listFiles($limit = 10000, $marker = null, $prefix = null, $path = 'php/');
```

### Put File
```php
$res = $container->putFile(__FILE__, 'example.php',["Content-Type: text/html"]);
```

### File info
```php
$fileInfo = $container->getFileInfo('example.php');
```

### Get file
```php
$file = $container->getFile($fileList[0]);
```

### Copy file
```php
$copyRes = $container->copy('example.php', 'php/test/Examples_copy.php');
```

### Create link
```php
$copyRes = $container->createLink('links/example.php', 'example.php');
```

### Delete
```php
$deleteRes = $container->delete('example.php');
```

### Passing Timeout to request
```php
$format = null;
$timeoutInMilliseconds = 5000;
$selectelStorage = new axelpal\selectel\storage\SelectelStorage("user", "pass", $format, $timeoutInMilliseconds);
```