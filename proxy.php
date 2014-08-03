<?php
/**
 * Copyright (c) 2013-2014, LiXianlin <xianlinli at gmail dot com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Proxy
 * @author xianlinli@gmail.com
 */
class Proxy {
    /**
     * socket connect timeout(s)
     * @var float
     */
    const SOCKET_CONNECT_TIMEOUT = 5;

    /**
     * socke read timeout(s)
     * @var int
     */
    const SOCKET_READ_TIMEOUT = -1;

    /**
     * request uri
     * @var string
     */
    private $requestUri = '';

    /**
     * request headers
     * @var array 
     */
    private $requestHeaders = array();

    /**
     * request body
     * @var string 
     */
    private $requestBody = '';

    /**
     * response headers(index array)
     * @var array
     */
    private $responseHeaders = array();

    /**
     * response headers(key-val pairs)
     * @var array
     */
    private $responseHeaders2 = array();

    /**
     * response body
     * @var string
     */
    private $responseBody = '';

    /**
     * decoded response body
     * @var string
     */
    private $decodedResponseBody = NULL;

    /**
     * cache path
     * @var string
     */
    private $cachePath;

    /**
     * mimeType
     * @var array
     */
    private $mimeTypeArr = array(
        'text/html' => 'htm',
        'text/xml' => 'xml',
        'application/xml' => 'xml',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'text/javascript' => 'js',
        'application/javascript' => 'js',
        'application/x-javascript' => 'js',
        'text/json' => 'json',
        'application/json' => 'json',
        'text/css' => 'css',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'application/x-shockwave-flash' => 'swf',
        'application/zip' => 'zip',
        'application/x-rar-compressed' => 'rar',
        'application/x-gzip' => 'gz',
    );

    /**
     * construct
     */
    public function __construct() {
        $this->cachePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'cache';
        set_error_handler(array($this, 'errorHandler'));
        $this->parseRequestUri();
        $this->parseRequestHeaders();
        $this->parseRequestBody();
    }

    /**
     * write log
     * @param string $msg
     * @param ...
     */
    private function log($msg) {
        $args = func_get_args();
        array_shift($args);
        if (!empty($args)) {
            $msg = vsprintf($msg, $args);
        }
        $data = sprintf("[%s]%s\r\n", date('Y-m-d H:i:s'), $msg);
        $filename = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'debug.log';
        file_put_contents($filename, $data, FILE_APPEND);
    }

    /**
     * log error and exit
     * @param string $msg
     * @param ...
     */
    private function logError($msg) {
        call_user_func_array(array($this, 'log'), func_get_args());
        $this->log($this->requestUri);
        //$this->log(var_export($_SERVER, true));
        header('HTTP/1.1 503 Service Unavailable');
        exit;
    }

    /**
     * error handler
     * @param int $errNo
     * @param string $errStr
     * @param string $errFile
     * @param int $errLine
     */
    private function errorHandler($errNo, $errStr, $errFile, $errLine) {
        $msg = sprintf('Error(%d): %s in %s on line %d', $errNo, $errStr, $errFile, $errLine);
        $this->logError($msg);
    }

    /**
     * parse uri
     */
    private function parseRequestUri() {
        if (!isset($_SERVER['HTTP_HOST'])) {
            $this->logError("\$_SERVER['HTTP_HOST'] not exists!");
            return;
        }
        $uri = $_SERVER['REQUEST_URI'];
        if (preg_match('#^http(s)?://#i', $uri)) {
            $this->requestUri = $uri;
        } else if ($_SERVER['REQUEST_METHOD'] === 'CONNECT') {
            $this->requestUri = 'https://' . $_SERVER['HTTP_HOST'];
        } else {
            $this->requestUri = 'http://' . $_SERVER['HTTP_HOST'] . $uri;
        }
    }

    /**
     * parse request headers
     */
    private function parseRequestHeaders() {
        if (function_exists('getallheaders')) {
            $this->requestHeaders = getallheaders();
            return;
        }
        foreach ($_SERVER as $key => $val) {
            if (strpos($key, 'HTTP_') === 0) {
                $newKey = strtolower(str_replace('_', ' ', substr($key, 5)));
                $newKey = str_replace(' ', '-', ucwords($newKey));
                $this->requestHeaders[$newKey] = $val;
            }
        }
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $user = $_SERVER['PHP_AUTH_USER'];
            $pass = $_SERVER['PHP_AUTH_PW'];
            $data = base64_encode($user . ':' . $pass);
            $this->requestHeaders['Authorization'] = 'Basic ' . $data;
        }
        if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
            $data = $_SERVER['PHP_AUTH_DIGEST'];
            $this->requestHeaders['Authorization'] = 'Digest ' . $data;
        }
    }

    /**
     * hack request headers
     * @return array
     */
    private function hackRequestHeaders($requestHeaders) {
        unset($requestHeaders['Proxy-Connection']);
        $requestHeaders['Connection'] = 'Close';
        return $requestHeaders;
    }

    /**
     * parse request body
     */
    private function parseRequestBody() {
        if (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            $this->requestBody = $GLOBALS['HTTP_RAW_POST_DATA'];
        } else {
            $this->requestBody = file_get_contents('php://input');
        }
    }

    /**
     * do http request
     * @param string $method
     * @param string $url
     * @param array $headersArr
     * @param string $body
     * @return string
     */
    private function doHttpRequest($method, $url, $headersArr, $body) {
        $urlArr = parse_url($url);
        $scheme = isset($urlArr['scheme']) ? $urlArr['scheme'] : '';
        switch ($scheme) {
            case 'http':
                $host = 'tcp://' . $urlArr['host'];
                $port = isset($urlArr['port']) ? $urlArr['port'] : 80;
                break;
            case 'https':
                $host = 'ssl://' . $urlArr['host'];
                $port = isset($urlArr['port']) ? $urlArr['port'] : 443;
                break;
            default:
                $this->logError('unsupported scheme(%s)!', $scheme);
                return;
        }
        $path = $urlArr['path'];
        if (isset($urlArr['query'])) {
            $path .= '?' . $urlArr['query'];
        }
        if (!empty($body)) {
            if (!isset($headersArr['Content-Type'])) {
                $headersArr['Content-Type'] = 'application/x-www-form-urlencoded';
            }
            $headersArr['Content-Length'] = strlen($body);
        }
        $dataArr = array();
        $dataArr[] = "{$method} {$path} HTTP/1.1";
        foreach ($headersArr as $key => $val) {
            if (empty($val)) {
                continue;
            }
            $dataArr[] = "{$key}: {$val}";
        }
        if (!empty($body)) {
            $dataArr[] = '';
            $dataArr[] = $body;
        } else {
            $dataArr[] = "\r\n";
        }
        $dataStr = implode("\r\n", $dataArr);
        $fp = fsockopen($host, $port, $errNo, $errStr, self::SOCKET_CONNECT_TIMEOUT);
        if (!$fp) {
            $this->logError('fsockopen(%s:%s) fail(%s)!', $host, $port, $errStr);
            return;
        }
        fwrite($fp, $dataStr);
        if (self::SOCKET_READ_TIMEOUT > 0) {
            stream_set_timeout($fp, self::SOCKET_READ_TIMEOUT);
        }
        $responseData = '';
        $directOutput = NULL;
        while (!feof($fp)) {
            $str = fread($fp, 8192);
            if ($str === false) {
                $statusArr = stream_get_meta_data($fp);
                if ($statusArr['timed_out']) {
                    $this->log('read socket timeout!');
                    $this->log(var_export($statusArr, true));
                    continue;
                }
                break;
            }
            if ($directOutput === NULL) {
                if (preg_match('/Content\-Length:[\s]*([0-9]+)/i', $str, $m)) {
                    $directOutput = $m[1] > 10 * 1024 * 1024;
                    if ($directOutput) {
                        list($header, $body) = explode("\r\n\r\n", $str);
                        $arr = explode("\r\n", $header);
                        foreach ($arr as $val) {
                            header($val);
                        }
                        echo $body;
                        continue;
                    }
                }
            }
            if ($directOutput) {
                echo $str;
            } else {
                $responseData .= $str;
            }
        }
        fclose($fp);
        if ($directOutput) {
            exit;
        }
        return $responseData;
    }

    /**
     * parse response data
     * @param string $responseData
     */
    private function parseResponseData($responseData) {
        if (preg_match('#^HTTP/[0-9]+\.[0-9]+[\s]+100#', $responseData)) {
            $arr = explode("\r\n\r\n", $responseData, 2);
            if (isset($arr[1])) {
                $responseData = $arr[1];
            }
        }
        $arr = explode("\r\n\r\n", $responseData, 2);
        if (isset($arr[1])) {
            $header = $arr[0];
            $body = $arr[1];
        } else {
            $header = $arr[0];
            $body = '';
        }
        $arr = explode("\r\n", $header);
        $status = array_shift($arr);
        $this->responseHeaders[] = $status;
        foreach ($arr as $val) {
            $this->responseHeaders[] = $val;
            list($key2, $val2) = preg_split('/:[\s]?/', $val, 2);
            $this->responseHeaders2[strtolower($key2)] = $val2;
        }
        $this->responseBody = $body;
    }

    /**
     * get specified response property
     * @param string $property
     * @return string
     */
    private function getResponseProperty($property) {
        if (isset($this->responseHeaders2[$property])) {
            return $this->responseHeaders2[$property];
        } else {
            return '';
        }
    }

    /**
     * decode chunked data
     * @param string $data
     * @return string
     */
    private function decodeChunkedData($data) {
        $pos = 0;
        $tmp = '';
        while ($pos < strlen($data)) {
            $len = strpos($data, "\r\n", $pos) - $pos;
            $str = substr($data, $pos, $len);
            $pos += $len + 2;
            $arr = explode(';', $str, 2);
            $len = hexdec($arr[0]);
            $tmp .= substr($data, $pos, $len);
            $pos += $len + 2;
        }
        return $tmp;
    }

    /**
     * decode gzip data
     * @param string $data
     * @return string
     */
    private function decodeGzipData($data) {
        if (function_exists('gzdecode')) {
            return gzdecode($data);
        }
        $filename = dirname(__FILE__) . DIRECTORY_SEPARATOR . sprintf('%d-%d.tmp', time(), rand());
        file_put_contents($filename, $data);
        ob_start();
        readgzfile($filename);
        $data = ob_get_clean();
        unlink($filename);
        return $data;
    }

    /**
     * get decoded response body
     * @return string
     */
    private function getDecodedResponseBody() {
        if ($this->decodedResponseBody !== NULL) {
            return $this->decodedResponseBody;
        }
        $body = $this->responseBody;
        $transferEncoding = $this->getResponseProperty('transfer-encoding');
        if (strcasecmp($transferEncoding, 'chunked') === 0) {
            $body = $this->decodeChunkedData($body);
        }
        $contentEncoding = $this->getResponseProperty('content-encoding');
        if (strcasecmp($contentEncoding, 'gzip') === 0) {
            $body = $this->decodeGzipData($body);
        } else if (strcasecmp($contentEncoding, 'deflate') === 0) {
            $body = gzinflate($body);
        }
        $this->decodedResponseBody = $body;
        return $body;
    }

    /**
     * get file extension by Content-Type
     * @return string
     */
    private function getFileExt() {
        $contentType = $this->getResponseProperty('content-type');
        if ($contentType === '') {
            return 'unknown';
        }
        foreach ($this->mimeTypeArr as $mimeType => $ext) {
            if (stripos($contentType, $mimeType) !== false) {
                return $ext;
            }
        }
        return str_replace('/', '-', $contentType);
    }

    /**
     * get the valid filename
     * @param string $filename
     * @return string
     */
    private function getValidFilename($filename) {
        return str_replace(array(':', '*', '?', '"', '<', '>', '|'), '_', $filename);
    }

    /**
     * save response body
     */
    private function saveResponseBody() {
        if (empty($this->responseBody)) {
            return;
        }
        if (!preg_match('#^HTTP/[0-9]+\.[0-9]+[\s]+200#', $this->responseHeaders[0])) {
            return;
        }
        $urlArr = parse_url($this->requestUri);
        $path = $urlArr['path'];
        $ext = $this->getFileExt();
        $filename = str_replace(':', '-', $urlArr['host']) . $path;
        if (preg_match('#/$#', $path)) {
            $filename .= sprintf('index-%08x.%s', crc32($this->requestUri), $ext);
        } else if (strpos(basename($path), '.') === false) {
            $filename .= '.' . $ext;
        }
        $filename = $this->cachePath . DIRECTORY_SEPARATOR . $this->getValidFilename($filename);
        $dir = dirname($filename);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $body = $this->getDecodedResponseBody();
        file_put_contents($filename, $body);
    }

    /**
     * output
     */
    private function output() {
        $ext = $this->getFileExt();
        if ($ext === 'htm') {
            $hasDecoded = true;
            $body = $this->getDecodedResponseBody();
            $body = preg_replace('#(</body>)#i', '<div style="position:fixed;z-index:9999;right:0;bottom:0;color:#f00;">proxy</div>$1', $body);
            //$body = preg_replace('#(<script[^>]*>[\s\S]*?</script>)#i', '<!--$1-->', $body);
        } else {
            $body = $this->responseBody;
        }
        foreach ($this->responseHeaders as $val) {
            if (stripos($val, 'Content-Length:') === 0) {
                continue;
            }
            if (isset($hasDecoded)) {
                if (stripos($val, 'Content-Encoding:') === 0 || stripos($val, 'Transfer-Encoding') === 0) {
                    continue;
                }
            }
            if (stripos($val, 'X-Powered-By:') === 0) {
                header($val);
            } else {
                header($val, false);
            }
        }
        echo $body;
    }

    /**
     * start proxy
     */
    public function start() {
        $method = $_SERVER['REQUEST_METHOD'];
        $url = $this->requestUri;
        $headersArr = $this->hackRequestHeaders($this->requestHeaders);
        $body = $this->requestBody;
        $responseData = $this->doHttpRequest($method, $url, $headersArr, $body);
        $this->parseResponseData($responseData);
        $this->saveResponseBody();
        $this->output();
    }
}
