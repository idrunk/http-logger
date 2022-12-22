<?php
$requestSchema = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : (strtolower($_SERVER['HTTPS']) === 'on' ? 'https' : 'http');
$protocolHost = sprintf('%s://%s', $requestSchema, $_SERVER['HTTP_HOST']);
$requestUri = preg_replace('#/(?=/|$)#ui', '', $_SERVER['REQUEST_URI']);
if ('/favicon.ico' === $requestUri) {
    die;
} else if ('' === $requestUri) {
    echo '<!doctype html><html lang="zh"><head><meta charset="UTF-8"><title>HTTP记录器</title></head><body><pre>';
    ob_start();
    require_once('README.md');
    echo str_ireplace('http://logger.drunkce.com', $protocolHost, ob_get_clean());
    die('<pre><body></html>');
} else if (fnmatch('/webhook.php*', $requestUri)) {
    // hack github webhook
    require_once './webhook.php';
    die;
}

ini_set('post_max_size', '100K');
ini_set('date.timezone', 'PRC');
header('Content-type: text/plain');

$time = date('Y-m-d H:i:s');
$queryPath = parse_url($requestUri, PHP_URL_PATH);
$prefix = substr($queryPath, 0, 3);

if ('/_/' === $prefix) {
    // 凡是_下的路径, 都当作查看请求调试反馈输出, 非该路径下的都作为调试输入, 防止输入输出混淆
    $logFilePath = __DIR__ . '/_' . substr($queryPath, 2);

    if (is_file($logFilePath)) {
        die(file_get_contents($logFilePath));
    } else {
        header('HTTP/1.1 404 Not Found');
        die("File '$queryPath' Not Found");
    }
}

$filePath = __DIR__ . "/_$queryPath";
$viewPath = $protocolHost . "/_$queryPath";
if (! file_exists($fileDir = dirname($filePath))) {
    mkdir($fileDir, 0766, true);
} else if (is_dir($filePath) || is_file($fileDir)) {
    header('HTTP/1.1 403 Forbidden');
    die(sprintf('无法在 %s 文件下创建子元素, 或无法将 %s 目录创建为文件', dirname($queryPath), $queryPath));
}
$isDebugStorage = isset($_SERVER['HTTP_DCE_DEBUG']);
$logType = isset($_SERVER['HTTP_LOG_TYPE']) ? $_SERVER['HTTP_LOG_TYPE'] : 'append';
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);

if ('DELETE' === $requestMethod) {
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    die;
} else if ('GET' === $requestMethod) {
    $postData = empty($_SERVER['QUERY_STRING']) ? '' : "Query String:\n{$_SERVER['QUERY_STRING']}\n";
} else if ($isDebugStorage) {
    $postData = file_get_contents('php://input');
} else {
    $postData = empty($_POST) ? file_get_contents('php://input') : var_export($_POST, 1);
    $postData = ($postData === '' ? '' : "Post Data:\n$postData") . "\n";
}

$log = $postData;
if (! $isDebugStorage) {
    $queryPath = "$requestMethod $queryPath";
    $requestInfo = var_export(array_merge(['REQUEST_SUMMARY' => "$queryPath; $time"], array_filter($_SERVER, function($k) {
        return preg_match('/^(?:http|request|query|remote)/ui', $k);
    }, ARRAY_FILTER_USE_KEY)), 1);
    $log = "Request Info: $requestInfo\n$log\n\n";
}

$fileDir = pathinfo($filePath, PATHINFO_DIRNAME);
if (! file_exists($fileDir)) {
    mkdir($fileDir, 0777, 1);
}
if ($logType === 'prepend') {
    $content = @file_get_contents($filePath);
    $log .= false === $content ? '' : $content;
}
file_put_contents($filePath, $log, $logType === 'append' ? FILE_APPEND : 0);

echo $viewPath;