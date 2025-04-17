<?php
class R2Manager
{
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $endpoint;
    private $host;
    private $cdnUrl;
    private $region = 'auto';
    public function __construct($config)
    {
        $this->accessKey = $config['access_key'];
        $this->secretKey = $config['secret_key'];
        $this->bucket = $config['bucket'];
        $this->endpoint = rtrim($config['endpoint'], '/');
        $this->cdnUrl = rtrim($config['cdn_url'], '/');
        $this->host = parse_url($this->endpoint, PHP_URL_HOST);
    }

    private function sign($key, $msg)
    {
        return hash_hmac('sha256', $msg, $key, true);
    }

    private function generateAuthHeaders($method, $path, $bodyHash, $date)
    {
        $shortDate = gmdate('Ymd', strtotime($date));
        $scope = "$shortDate/{$this->region}/s3/aws4_request";

        $canonicalUri = '/' . $this->bucket;
        if (!empty($path)) {
            $canonicalUri .= '/' . ltrim($path, '/');
        }

        $canonicalQueryString = '';
        if ($method === 'GET' && $path === '') {
            $canonicalQueryString = 'prefix=';
        }

        $canonicalHeaders = "host:{$this->host}\n" .
            "x-amz-content-sha256:$bodyHash\n" .
            "x-amz-date:$date\n";
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";

        $canonicalRequest = "$method\n$canonicalUri\n$canonicalQueryString\n$canonicalHeaders\n$signedHeaders\n$bodyHash";

        $stringToSign = "AWS4-HMAC-SHA256\n$date\n$scope\n" . hash('sha256', $canonicalRequest);

        $kDate = $this->sign("AWS4{$this->secretKey}", $shortDate);
        $kRegion = $this->sign($kDate, $this->region);
        $kService = $this->sign($kRegion, 's3');
        $kSigning = $this->sign($kService, 'aws4_request');

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        $authHeader = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/$scope, SignedHeaders=$signedHeaders, Signature=$signature";

        return [
            "Authorization: $authHeader",
            "x-amz-date: $date",
            "x-amz-content-sha256: $bodyHash"
        ];
    }


    private function request($method, $path, $body = '', $extraHeaders = [])
    {
        $date = gmdate('Ymd\THis\Z');
        $bodyHash = hash('sha256', $body);
        $headers = array_merge(
            $this->generateAuthHeaders($method, $path, $bodyHash, $date),
            $extraHeaders
        );

        $url = "{$this->endpoint}/$path";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $body
        ]);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        return ['status' => $info['http_code'], 'body' => $response];
    }

    public function upload($localFilePath, $remotePath)
    {
        $data = file_get_contents($localFilePath);
        $mime = mime_content_type($localFilePath);
        return $this->request('PUT', $remotePath, $data, [
            "Content-Type: $mime"
        ]);
    }

    public function delete($remotePath)
    {
        return $this->request('DELETE', $remotePath);
    }

    public function getUrl($remotePath)
    {
        return "{$this->cdnUrl}/$remotePath";
    }

    public function list($prefix = '')
    {
        $query = http_build_query(['prefix' => $prefix]);
        $url = "{$this->endpoint}/{$this->bucket}?" . $query;
        $date = gmdate('Ymd\THis\Z');
        $bodyHash = hash('sha256', '');
        $headers = $this->generateAuthHeaders('GET', '', $bodyHash, $date);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true
        ]);
        $xml = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($info['http_code'] !== 200) {
            return [];
        }

        $list = [];
        if ($xml) {
            $parsed = simplexml_load_string($xml);
            if ($parsed && isset($parsed->Contents)) {
                foreach ($parsed->Contents as $item) {
                    $list[] = [
                        'Key' => (string)$item->Key,
                        'LastModified' => (string)$item->LastModified,
                        'Size' => (int)$item->Size
                    ];
                }
            }
        }

        return $list;
    }
}
