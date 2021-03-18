<?php

namespace axelpal\selectel\storage;

/**
 * PHP version 7
 *
 * @category selectel-storage-php-class
 * @package class_package
 * @author Eugene Kuznetcov <easmith@mail.ru>
 * @author Alexander Palchikov <axelpal@gmail.com>
 */
class SCurl
{

    /** @var self */
    private static $instance;

    /**
     * Curl resource
     *
     * @var null|resource
     */
    private $ch;

    /**
     * Current URL
     *
     * @var string
     */
    private $url;

    /**
     * Last request result
     *
     * @var array
     */
    private $result = [];

    /**
     * Request params
     *
     * @var array
     */
    private $params = [];

    /**
     * Curl wrapper
     *
     * @param string $url
     */
    private function __construct(string $url)
    {
        $this->setUrl($url);
        $this->curlInit();
    }

    private function curlInit(): void
    {
        $this->ch = curl_init($this->url);
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
    }

    /**
     *
     * @param string $url
     *
     * @return SCurl
     */
    public static function init(string $url): SCurl
    {
        if (self::$instance === null) {
            self::$instance = new SCurl($url);
        }
        return self::$instance->setUrl($url);
    }

    /**
     * Set url for request
     *
     * @param string $url URL
     *
     * @return SCurl|null
     */
    public function setUrl(string $url): ?SCurl
    {
        $this->url = $url;
        return self::$instance;
    }

    /**
     * @param $file
     * @return SCurl
     * @throws SelectelStorageException
     */
    public function putFile($file): SCurl
    {
        if (!file_exists($file)) {
            throw new SelectelStorageException("File '$file' does not exist");
        }
        $fp = fopen($file, 'rb');
        curl_setopt($this->ch, CURLOPT_INFILE, $fp);
        curl_setopt($this->ch, CURLOPT_INFILESIZE, filesize($file));
        $this->request('PUT');
        fclose($fp);
        return self::$instance;
    }

    /**
     * Set configureMethod and request
     *
     * @param string $method
     *
     * @return SCurl
     */
    public function request(string $method): SCurl
    {
        $this->configureMethod($method);
        $this->params = [];
        curl_setopt($this->ch, CURLOPT_URL, $this->url);

        $response = explode("\r\n\r\n", curl_exec($this->ch));

        $this->result['info'] = curl_getinfo($this->ch);
        $this->result['header'] = $this->parseHead($response[0]);
        unset($response[0]);
        $this->result['content'] = implode("\r\n\r\n", $response);

        $this->curlInit();

        return self::$instance;
    }

    /**
     * Set request configureMethod
     *
     * @param string $method
     */
    private function configureMethod(string $method): void
    {
        switch ($method) {
            case 'GET' :
            {
                $this->url .= '?' . http_build_query($this->params);
                curl_setopt($this->ch, CURLOPT_HTTPGET, true);
                break;
            }
            case 'HEAD' :
            {
                $this->url .= '?' . http_build_query($this->params);
                curl_setopt($this->ch, CURLOPT_NOBODY, true);
                break;
            }
            case 'POST' :
            {
                curl_setopt($this->ch, CURLOPT_POST, true);
                curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($this->params));
                break;
            }
            case 'PUT' :
            {
                curl_setopt($this->ch, CURLOPT_PUT, true);
                break;
            }
            default :
            {
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
                break;
            }
        }
    }

    /**
     * Header Parser
     *
     * @param string $head
     *
     * @return array
     */
    private function parseHead(string $head): array
    {
        $result = [];
        $code = explode("\r\n", $head);
        preg_match('/HTTP.+ (\d\d\d)/', $code[0], $codeMatches);
        $result['HTTP-Code'] = (int)$codeMatches[1];
        preg_match_all("/([A-z\-]+): (.*)\r\n/", $head, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $result[strtolower($match[1])] = $match[2];
        }

        return $result;
    }

    /**
     * @param $contents
     * @return SCurl
     */
    public function putFileContents($contents): SCurl
    {
        $fp = fopen('php://temp', 'rb+');
        fwrite($fp, $contents);
        rewind($fp);
        curl_setopt($this->ch, CURLOPT_INFILE, $fp);
        curl_setopt($this->ch, CURLOPT_INFILESIZE, strlen($contents));
        $this->request('PUT');
        fclose($fp);
        return self::$instance;
    }

    /**
     * Set headers
     *
     * @param array $headers
     *
     * @return SCurl
     */
    public function setHeaders(array $headers): SCurl
    {
        $headers = array_merge(['Expect:'], $headers);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        return self::$instance;
    }

    /**
     * @param int|null $timeout Timeout in milliseconds
     * @return SCurl
     */
    public function setTimeout(?int $timeout): SCurl
    {
        if ($timeout !== null) {
            curl_setopt($this->ch, CURLOPT_TIMEOUT_MS, $timeout);
        }
        return self::$instance;
    }

    /**
     * Set request parameters
     *
     * @param array $params
     *
     * @return SCurl
     */
    public function setParams(array $params): SCurl
    {
        $this->params = $params;
        return self::$instance;
    }

    /**
     * Getting info, headers and content of last response
     *
     * @return array
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * Getting headers of last response
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->result['header'] ?? [];
    }

    /**
     * Getting content of last response
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->result['content'] ?? '';
    }

    /**
     * Getting info of last response
     *
     * @return array
     */
    public function getInfo(): array
    {
        return $this->result['info'];
    }

    private function __clone()
    {

    }

}
