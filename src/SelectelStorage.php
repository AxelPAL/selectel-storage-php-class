<?php

namespace axelpal\selectel\storage;

/**
 * Selectel Storage PHP class
 *
 * PHP version 7
 *
 * @author Eugene Smith <easmith@mail.ru>
 * @author Alexander Palchikov <axelpal@gmail.com>
 */
class SelectelStorage
{

    /**
     * Header string in array for authorization.
     *
     * @var array
     */
    protected $token = [];

    /**
     * Storage url
     *
     * @var string
     */
    protected $url = '';

    /**
     * The response format
     *
     * @var string
     */
    protected $format = '';

    /**
     * Allowed response formats
     *
     * @var array
     */
    protected $formats = ['', 'json', 'xml'];

    /**
     * Timeout for getting response from Selectel
     *
     * @var int
     */
    protected $timeout;

    /**
     * Creating Selectel Storage PHP class
     *
     * @param string $user Account id
     * @param string $key Storage key
     * @param string|null $format Allowed response formats
     * @param int|null $timeout Timeout for getting response from Selectel in milliseconds
     *
     * @throws SelectelStorageException
     */
    public function __construct(string $user, string $key, ?string $format = null, ?int $timeout = null)
    {
        $header = SCurl::init('https://auth.selcdn.ru/')
            ->setHeaders(['Host: auth.selcdn.ru', "X-Auth-User: $user", "X-Auth-Key: $key"])
            ->setTimeout($this->timeout)
            ->request('GET')
            ->getHeaders();

        if ((int)$header['HTTP-Code'] !== 204) {
            if ((int)$header['HTTP-Code'] === 403) {
                $this->error($header['HTTP-Code'], "Forbidden for user '$user'");
            }

            $this->error($header['HTTP-Code'], __METHOD__);
        }

        $this->format = (!in_array($format, $this->formats, true) ? $this->format : $format);
        $this->url = $header['x-storage-url'];
        $this->token = ["X-Auth-Token: {$header['x-storage-token']}"];
        $this->timeout = $timeout;
    }

    /**
     * Handle errors
     *
     * @param integer $code
     * @param string $message
     *
     * @throws SelectelStorageException
     */
    protected function error(int $code, string $message): void
    {
        throw new SelectelStorageException($message, $code);
    }

    /**
     * Getting storage info
     *
     * @return array
     * @throws SelectelStorageException
     */
    public function getInfo(): array
    {
        $head = SCurl::init($this->url)
            ->setHeaders($this->token)
            ->setTimeout($this->timeout)
            ->request('HEAD')
            ->getHeaders();
        return $this->getX($head);
    }

    /**
     * Select only 'x-' from headers
     *
     * @param array $headers Array of headers
     * @param string $prefix Prefix for filtering
     *
     * @return array
     */
    protected function getX(array $headers, $prefix = 'x-'): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            if (stripos($key, $prefix) === 0) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Getting containers list
     *
     * @param int $limit Limit (Default 10000)
     * @param string $marker Marker (Default '')
     * @param string $format Format ('', 'json', 'xml') (Default self::$format)
     *
     * @return array
     * @throws SelectelStorageException
     */
    public function listContainers($limit = 10000, $marker = '', $format = null): array
    {
        $params = [
            'limit'  => $limit,
            'marker' => $marker,
            'format' => !in_array($format, $this->formats, true) ? $this->format : $format
        ];

        $cont = SCurl::init($this->url)
            ->setHeaders($this->token)
            ->setTimeout($this->timeout)
            ->setParams($params)
            ->request('GET')
            ->getContent();

        if ($params['format'] === '') {
            return explode("\n", trim($cont));
        }

        return trim($cont);
    }

    /**
     * Create container by name.
     * Headers for
     *
     * @param string $name
     * @param array $headers
     *
     * @return SelectelContainer
     * @throws SelectelStorageException
     */
    public function createContainer(string $name, $headers = []): SelectelContainer
    {
        $headers = array_merge($this->token, $headers);
        $info = SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->setTimeout($this->timeout)
            ->request('PUT')
            ->getInfo();

        if (!in_array((int)$info['http_code'], [201, 202], true)) {
            $this->error($info['http_code'], __METHOD__);
        }

        return $this->getContainer($name);
    }

    /**
     * Select container by name
     *
     * @param string $name
     *
     * @return SelectelContainer
     * @throws SelectelStorageException
     */
    public function getContainer(string $name): SelectelContainer
    {
        $url = $this->url . $name;
        $headers = SCurl::init($url)
            ->setHeaders($this->token)
            ->setTimeout($this->timeout)
            ->request('HEAD')
            ->getHeaders();

        if ($headers['HTTP-Code'] !== 204) {
            $this->error($headers['HTTP-Code'], __METHOD__);
        }

        return new SelectelContainer($url, $this->token, $this->format, $this->getX($headers), $this->timeout);
    }

    /**
     * Delete container or object by name
     *
     * @param string $name
     *
     * @return array
     * @throws SelectelStorageException
     */
    public function delete(string $name): array
    {
        $info = SCurl::init($this->url . $name)
            ->setHeaders($this->token)
            ->setTimeout($this->timeout)
            ->request('DELETE')
            ->getInfo();

        if ((int)$info['http_code'] !== 204) {
            $this->error($info['http_code'], __METHOD__);
        }

        return $info;
    }

    /**
     * Copy
     *
     * @param string $origin Origin object
     * @param string $destination Destination
     *
     * @return array
     * @throws SelectelStorageException
     */
    public function copy(string $origin, string $destination): array
    {
        $url = parse_url($this->url);
        $destination = $url['path'] . $destination;
        $headers = array_merge($this->token, ["Destination: $destination"]);
        return SCurl::init($this->url . $origin)
            ->setHeaders($headers)
            ->setTimeout($this->timeout)
            ->request('COPY')
            ->getResult();
    }

    /**
     * @param $name
     * @param $headers
     * @return int
     * @throws SelectelStorageException
     */
    public function setContainerHeaders($name, $headers): int
    {
        $headers = $this->getX($headers, 'X-Container-Meta-');
        if (get_class($this) !== 'SelectelStorage') {
            return 0;
        }

        return $this->setMetaInfo($name, $headers);
    }

    /**
     * Setting meta info
     *
     * @param string $name Name of object
     * @param array $headers Headers
     *
     * @return int
     * @throws SelectelStorageException
     */
    protected function setMetaInfo(string $name, array $headers): int
    {
        if (get_class($this) === 'SelectelStorage') {
            $headers = $this->getX($headers, 'X-Container-Meta-');
        } elseif (get_class($this) === 'SelectelContainer') {
            $headers = $this->getX($headers, 'X-Container-Meta-');
        } else {
            return 0;
        }

        $info = SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->setTimeout($this->timeout)
            ->request('POST')
            ->getInfo();

        if ((int)$info['http_code'] !== 204) {
            $this->error($info['http_code'], __METHOD__);
        }

        return (int)$info['http_code'];
    }

    /**
     * Upload  and extract archive
     *
     * @param string $archive The name of a local file
     * @param string $path The path to extract archive
     * @return array
     * @throws SelectelStorageException
     */
    public function putArchive(string $archive, $path = null): array
    {
        $url = $this->url . $path . '?extract-archive=' . pathinfo($archive, PATHINFO_EXTENSION);


        switch ($this->format) {
            case 'json':
                $headers = array_merge($this->token, ['Accept: application/json']);
                break;
            case 'xml':
                $headers = array_merge($this->token, ['Accept: application/xml']);
                break;
            default:
                $headers = array_merge($this->token, ['Accept: text/plain']);
                break;
        }

        $info = SCurl::init($url)
            ->setHeaders($headers)
            ->setTimeout($this->timeout)
            ->putFile($archive)
            ->getContent();

        if ($this->format === '') {
            return explode("\n", trim($info));
        }


        return $this->format === 'json' ? json_decode($info, true) : trim($info);
    }

    /**
     * Set X-Account-Meta-Temp-URL-Key for temp file download link generation. Run it once and use key forever.
     *
     * @param string $key
     *
     * @return integer
     * @throws SelectelStorageException
     */
    public function setAccountMetaTempURLKey(string $key): int
    {
        $url = $this->url;
        $headers = array_merge($this->token, ['X-Account-Meta-Temp-URL-Key: ' . $key]);
        $res = SCurl::init($url)
            ->setHeaders($headers)
            ->setTimeout($this->timeout)
            ->request('POST')
            ->getHeaders();

        if ((int)$res['HTTP-Code'] !== 202) {
            $this->error($res ['HTTP-Code'], __METHOD__);
        }

        return $res['HTTP-Code'];
    }

    /**
     * Get temp file download link
     *
     * @param string $key X-Account-Meta-Temp-URL-Key specified by setAccountMetaTempURLKey configureMethod
     * @param string $path to file
     * @param integer $expires time in UNIX-format, after this time link will be voided
     * @param string|null $otherFileName custom filename if needed
     *
     * @return string
     */
    public function getTempURL(string $key, string $path, int $expires, ?string $otherFileName = null): string
    {
        $urlData = parse_url($this->url);
        $url = $urlData['scheme'] . '://' . $urlData['host'];
        $containerPath = $urlData['path'];

        $sigBody = "GET\n$expires\n$containerPath$path";

        $sig = hash_hmac('sha1', $sigBody, $key);

        $res = $url . $containerPath . $path . '?temp_url_sig=' . $sig . '&temp_url_expires=' . $expires;

        if ($otherFileName !== null) {
            $res .= '&filename=' . urlencode($otherFileName);
        }

        return $res;
    }

    /**
     * @param string $linkPath to file
     * @param string $filePath to file
     * @return mixed
     * @throws SelectelStorageException
     */
    public function createLink(string $linkPath, string $filePath)
    {
        $urlData = parse_url($this->url);
        $containerPath = $urlData['path'];
        $headers = array_merge($this->token, [
                "X-Object-Meta-Location: $containerPath$filePath",
                'Content-Type: x-storage/symlink',
                'Content-Length: 0',
            ]
        );
        $baseUrl = $urlData['scheme'] . '://' . $urlData['host'];
        $result = SCurl::init($baseUrl . "$containerPath$linkPath")
            ->setHeaders($headers)
            ->setTimeout($this->timeout)
            ->request('PUT');
        $responseHeaders = $result->getHeaders();

        if ((int)$responseHeaders['HTTP-Code'] !== 201) {
            $this->error($responseHeaders ['HTTP-Code'], __METHOD__);
        }

        return $responseHeaders['HTTP-Code'];
    }

}
