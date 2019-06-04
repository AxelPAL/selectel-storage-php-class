<?php

namespace easmith\selectel\storage;

/**
 * Selectel Storage PHP class
 *
 * PHP version 5
 *
 * @author   Eugene Smith <easmith@mail.ru>
 */
class SelectelStorage
{

    /**
     * Header string in array for authorization.
     *
     * @var array()
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
     * Creating Selectel Storage PHP class
     *
     * @param string $user Account id
     * @param string $key Storage key
     * @param string $format Allowed response formats
     *
     * @throws SelectelStorageException
     */
    public function __construct($user, $key, $format = null)
    {
        $header = SCurl::init('https://auth.selcdn.ru/')
            ->setHeaders(array('Host: auth.selcdn.ru', "X-Auth-User: {$user}", "X-Auth-Key: {$key}"))
            ->request('GET')
            ->getHeaders();

        if ((int)$header['HTTP-Code'] !== 204) {
            if ((int)$header['HTTP-Code'] === 403) {
                $this->error($header['HTTP-Code'], "Forbidden for user '{$user}'");
            }

            $this->error($header['HTTP-Code'], __METHOD__);
        }

        $this->format = (!in_array($format, $this->formats, true) ? $this->format : $format);
        $this->url = $header['x-storage-url'];
        $this->token = array("X-Auth-Token: {$header['x-storage-token']}");
    }

    /**
     * Handle errors
     *
     * @param integer $code
     * @param string $message
     *
     * @throws SelectelStorageException
     */
    protected function error($code, $message)
    {
        throw new SelectelStorageException($message, $code);
    }

    /**
     * Getting storage info
     *
     * @return array
     */
    public function getInfo(): array
    {
        $head = SCurl::init($this->url)
            ->setHeaders($this->token)
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
    protected function getX($headers, $prefix = 'x-'): array
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
     */
    public function listContainers($limit = 10000, $marker = '', $format = null): array
    {
        $params = array(
            'limit' => $limit,
            'marker' => $marker,
            'format' => !in_array($format, $this->formats, true) ? $this->format : $format
        );

        $cont = SCurl::init($this->url)
            ->setHeaders($this->token)
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
    public function createContainer($name, $headers = []): SelectelContainer
    {
        $headers = array_merge($this->token, $headers);
        $info = SCurl::init($this->url . $name)
            ->setHeaders($headers)
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
    public function getContainer($name): SelectelContainer
    {
        $url = $this->url . $name;
        $headers = SCurl::init($url)
            ->setHeaders($this->token)
            ->request('HEAD')
            ->getHeaders();

        if ($headers['HTTP-Code'] !== 204) {
            $this->error($headers['HTTP-Code'], __METHOD__);
        }

        return new SelectelContainer($url, $this->token, $this->format, $this->getX($headers));
    }

    /**
     * Delete container or object by name
     *
     * @param string $name
     *
     * @return array
     * @throws SelectelStorageException
     */
    public function delete($name): array
    {
        $info = SCurl::init($this->url . $name)
            ->setHeaders($this->token)
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
     */
    public function copy($origin, $destination): array
    {
        $url = parse_url($this->url);
        $destination = $url['path'] . $destination;
        $headers = array_merge($this->token, array("Destination: {$destination}"));
        $info = SCurl::init($this->url . $origin)
            ->setHeaders($headers)
            ->request('COPY')
            ->getResult();

        return $info;
    }

    /**
     * @param $name
     * @param $headers
     * @return int
     * @throws SelectelStorageException
     */
    public function setContainerHeaders($name, $headers)
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
    protected function setMetaInfo($name, $headers): int
    {
        if (get_class($this) === 'SelectelStorage') {
            $headers = $this->getX($headers, 'X-Container-Meta-');
        }
        elseif (get_class($this) === 'SelectelContainer') {
            $headers = $this->getX($headers, 'X-Container-Meta-');
        }
        else {
            return 0;
        }

        $info = SCurl::init($this->url . $name)
            ->setHeaders($headers)
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
    public function putArchive($archive, $path = null): array
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
    public function setAccountMetaTempURLKey($key): int
    {
        $url = $this->url;
        $headers = array_merge($this->token, ['X-Account-Meta-Temp-URL-Key: ' . $key]);
        $res = SCurl::init($url)
            ->setHeaders($headers)
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
     * @param string $key X-Account-Meta-Temp-URL-Key specified by setAccountMetaTempURLKey method
     * @param string $path to file, including container name
     * @param integer $expires time in UNIX-format, after this time link will be voided
     * @param string $otherFileName custom filename if needed
     *
     * @return string
     */
    public function getTempURL($key, $path, $expires, $otherFileName = null): string
    {
        $url = substr($this->url, 0, -1);

        $sig_body = "GET\n$expires\n$path";

        $sig = hash_hmac('sha1', $sig_body, $key);

        $res = $url . $path . '?temp_url_sig=' . $sig . '&temp_url_expires=' . $expires;

        if ($otherFileName !== null) {
            $res .= '&filename=' . urlencode($otherFileName);
        }

        return $res;
    }

}
