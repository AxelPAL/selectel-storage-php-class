<?php

require_once("vendor/autoload.php");

try {
    $containerName = 'selectel';
    $fileName = 'example.php';
    $copyFilePath = 'php/test/Examples_copy.php';
    $linkFilePath = 'example-link.php';
    $selectelStorage = new axelpal\selectel\storage\SelectelStorage("user", "pass");

    echo "\n\nCreate Container:\n";
    $container = $selectelStorage->createContainer($containerName, array("X-Container-Meta-Type: private"));
    print_r($container->getInfo());

    echo "Containers list\n";
    $containerList = $selectelStorage->listContainers();
    print_r($containerList);

    echo "\n\nContainer Info:\n";
    $cInfo = $selectelStorage->getContainer($containerName)->getInfo();
    print_r($cInfo);

    echo "\n\nCreate directory:\n";
    $container = $selectelStorage->getContainer($containerName);
    $container->createDirectory('php/test');

    echo "\n\nDirectories:\n";
    $dirList = $container->listFiles($limit = 10000, $marker = null, $prefix = null, $path = "");
    print_r($dirList);

    echo "\n\nPutting File:\n";
    $res = $container->putFile(__FILE__, $fileName);
    print_r($res);

    echo "\n\nFiles in directory:\n";
    $fileList = $container->listFiles($limit = 10000, $marker = null, $prefix = null, $path = 'php/');
    print_r($fileList);

    echo "\n\nFile info:\n";
    $fileInfo = $container->getFileInfo($fileName);
    print_r($fileInfo);

    echo "\n\nGetting file (base64):\n";
    $file = $container->getFile($fileList[0]);
    $file['content'] = base64_encode($file['content']);
    print_r($file);

    echo "\n\nCopy: \n";
    $copyRes = $container->copy($fileName, $copyFilePath);
    print_r($copyRes);

    echo "\n\nAccountMetaTempURL: \n";
    $MetaTempURLKeyRes = $container->setAccountMetaTempURLKey("example");
    print_r($MetaTempURLKeyRes);

    echo "\n\nGetting temp url: \n";
    $publicUrl = $container->getTempURL('example', $fileName, time() + 3600);
    print_r($publicUrl . PHP_EOL);
    print_r('File length: ' . strlen(file_get_contents($publicUrl)) . PHP_EOL);

    echo "\n\nCreating link: \n";
    echo $container->createLink($linkFilePath, $fileName) . PHP_EOL;

    echo "\n\nDelete: \n";
    $deleteRes = $container->delete($fileName);
    print_r($deleteRes);
    $deleteRes = $container->delete($copyFilePath);
    print_r($deleteRes);
    $deleteRes = $container->delete('php/test');
    print_r($deleteRes);
    $deleteRes = $container->delete($linkFilePath);
    print_r($deleteRes);

} catch (Exception $e) {
    print_r($e->getTrace());
}

