<?php
use GuzzleHttp\Client;  

// 应用公共文件
function post($url, $header = [], $params = []) 
{
    $client = new Client();
    $response = $client->post($url, [
            'form_params' => $params,    
            'headers' => $header,
            'timeout'  => 5,              
        ]
    ); 
    return $response->getBody()->getContents();
}

/**
 * 发送curl请求
 */
function wget($url, $data = [], $options = [])
{
    // dd($url, $data, $options);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if (env('APP_DEBUG') === true) {
        curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:7897'); // 代理服务器地址和端口
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); // 代理类型
    }

    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    if (!empty($options)) {
        curl_setopt_array($ch, $options);
    }
    
    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    if ($curlErrno) {
        $errorMsg = curl_error($ch);
        curl_close($ch);
        throw new Exception("CURL Error ({$curlErrno}): {$errorMsg}");
    }
    curl_close($ch);

    $retsult = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $response = $retsult;
    }
    return $response;
}
