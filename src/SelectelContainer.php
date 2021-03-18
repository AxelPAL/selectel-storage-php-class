<?php

namespace axelpal\selectel\storage;

/**
 * Selectel Storage Container PHP class
 *
 * PHP version 7
 *
 * @author Eugene Smith <easmith@mail.ru>
 * @author Alexander Palchikov <axelpal@gmail.com>
 */
class SelectelContainer extends SelectelStorage
{

    /**
     * 'x-' Headers of container
     *
     * @var array
     * @throws SelectelStorageException
     */
    private $info;

    public function __construct($url, $token = [], $format = null, $info = [], ?int $timeout = null)
    {
        $this->url = $url . '/';
        $this->token = $token;
        $this->format = (!in_array($format, $this->formats, true) ? $this->format : $format);
        $this->info = (count($info) === 0 ? $this->getInfo(true) : $info);
        $this->timeout = $timeout;
    }

    /**
     * Getting container info
     *
     * @param boolean $refresh Refresh? Default false
     *
     * @return array
     * @throws SelectelStorageException
     */
    public function getInfo($refresh = false): array
    {
        if (!$refresh) {
            return $this->info;
        }

        $headers = SCurl::init($this->url)
            ->setHeaders($this->token)
            ->setTimeout($this->timeout)
            ->request('HEAD')
            ->getHeaders();

        if (!((int)$headers['HTTP-Code'] === 204)) {
            $this->error($headers['HTTP-Code'], __METHOD__);
        }

        return $this->info = $this->getX($headers);
    }

    /**
     * Getting file with info and headers
     *
     * Supported headers:
     * If-Match
     * If-None-Match
     * If-Modified-Since
     * If-Unmodified-Since
     *
     * @param string $name
     * @param array $headers
     *
     * @return array
     */
    public function getFile(string $name, $headers = []): array
    {
        $headers = array_merge($headers, $this->token);
        return SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->setTimeout($this->timeout)
            ->request('GET')
            ->getResult();
    }

    /**
     * Getting file info
     *
     * @param string $name File name
     *
     * @return array
     */
    public function getFileInfo(string $name): array
    {
        $res = $this->listFiles(1, '', $name, null, null, 'json');
        $info = current(json_decode($res, true));
        return is_array($info) && !empty($info) ? $info : [];
    }

    /**
     * Getting file list
     *
     * @param int $limit Limit
     * @param string $marker Marker
     * @param string $prefix Prefix
     * @param string $path Path
     * @param string $delimiter Delimiter
     * @param string $format Format
     *
     * @return array|string
     */
    public function listFiles(
        $limit = 10000,
        $marker = null,
        $prefix = null,
        $path = null,
        $delimiter = null,
        $format = null
    ) {
        $params = [
            'limit' => $limit,
            'marker' => $marker,
            'prefix' => $prefix,
            'path' => $path,
            'delimiter' => $delimiter,
            'format' => !in_array($format, $this->formats, true) ? $this->format : $format
        ];

        $res = SCurl::init($this->url)
            ->setHeaders($this->token)
            ->setParams($params)
            ->setTimeout($this->timeout)
            ->request('GET')
            ->getContent();

        if ($params['format'] === '') {
            return explode("\n", trim($res));
        }

        return trim($res);
    }

    /**
     * Upload local file
     *
     * @param string $localFileName The name of a local file
     * @param string|null $remoteFileName The name of storage file
     *
     * @param array $headers
     * @return array
     * @throws SelectelStorageException
     */
    public function putFile(string $localFileName, ?string $remoteFileName = null, $headers = []): array
    {
        if ($remoteFileName === null) {
            $pathArray = explode(DIRECTORY_SEPARATOR, $localFileName);
            $remoteFileName = array_pop($pathArray);
        }
        $headers = array_merge($headers, $this->token);
        $info = SCurl::init($this->url . $remoteFileName)
            ->setHeaders($headers)
            ->setTimeout($this->timeout)
            ->putFile($localFileName)
            ->getInfo();

        if (!((int)$info['http_code'] === 201)) {
            $this->error($info['http_code'], __METHOD__);
        }

        return $info;
    }

    /**
     * Upload binary string as file
     *
     * @param string $contents
     * @param string|null $remoteFileName
     * @return array
     * @throws SelectelStorageException
     */
    public function putFileContents(string $contents, ?string $remoteFileName = null): array
    {
        $info = SCurl::init($this->url . $remoteFileName)
            ->setHeaders($this->token)
            ->setTimeout($this->timeout)
            ->putFileContents($contents)
            ->getInfo();

        if (!((int)$info['http_code'] === 201)) {
            $this->error($info['http_code'], __METHOD__);
        }

        return $info;
    }

    /**
     * Set meta info for file
     *
     * @param string $name File name
     * @param array $headers Headers
     *
     * @return int
     * @throws SelectelStorageException
     */
    public function setFileHeaders(string $name, array $headers): int
    {
        $headers = $this->getX($headers, 'X-Container-Meta-');
        if (get_class($this) !== 'SelectelContainer') {
            return 0;
        }

        return $this->setMetaInfo($name, $headers);
    }

    /**
     * Creating directory
     *
     * @param string $name Directory name
     *
     * @return array
     */
    public function createDirectory(string $name): array
    {
        $headers = array_merge(['Content-Type: application/directory'], $this->token);

        return SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->setTimeout($this->timeout)
            ->request('PUT')
            ->getInfo();
    }
}
