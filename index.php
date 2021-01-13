<?php
$protocolHost = sprintf('%s://%s', strtolower($_SERVER['HTTPS']) === 'on' ? 'https' : 'http', $_SERVER['HTTP_HOST']);
$requestUri = preg_replace('#/(?=/|$)#ui', '', $_SERVER['REQUEST_URI']);
if ('/favicon.ico' === $requestUri) {
    die;
} else if ('' === $requestUri) {
    echo '<!doctype html><html><head><meta charset="UTF-8"><title>HTTP记录器</title></head><body><pre>';
    ob_start();
    require_once('README.md');
    echo str_ireplace('http://logger.drunkce.com', $protocolHost, ob_get_clean());
    die('<pre><body></html>');
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
        die("File '{$logFilePath}' Not Found");
    }
}

$filePath = __DIR__ . "/_{$queryPath}";
$viewPath = $protocolHost . "/_{$queryPath}";
if (! file_exists($fileDir = dirname($filePath))) {
    mkdir($fileDir, 0766, true);
} else if (is_dir($filePath) || is_file($fileDir)) {
    header('HTTP/1.1 403 Forbidden');
    die(sprintf('无法在 %s 文件下创建子元素, 或无法将 %s 目录创建为文件', dirname($queryPath), $queryPath));
}
$isDebugStorage = isset($_SERVER['HTTP_DCE_DEBUG']);
$isPost = $isDelete = false;
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);

if ('POST' === $requestMethod) {
    if ($isDebugStorage) {
        $postData = file_get_contents('php://input');
    } else {
        $postData = empty($_POST) ? null : var_export($_POST, 1);
        $postData = "Post Data:\n{$postData}\n\n";
    }
    $isPost = true;
} else if ('PUT' === $requestMethod) {
    $postData = file_get_contents('php://input');
    $postData = "Put Data:\n{$postData}\n\n";
} else if ('DELETE' === $requestMethod) {
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    die;
} else {
    $postData = empty($_SERVER['QUERY_STRING']) ? '' : "Query String:\n{$_SERVER['QUERY_STRING']}\n\n";
    $isDelete = true;
}

if ($isDebugStorage && $isPost) {
    $log = $postData;
} else {
    $queryPath = "{$requestMethod} {$queryPath}";
    $serverData = var_export(array_filter($_SERVER, function($k) {
        return preg_match('/^(?:http|request|query|remote)/ui', $k);
    }, ARRAY_FILTER_USE_KEY), 1);
    $log = "{$queryPath}; {$time}\n\n{$postData}{$serverData}\n\n\n";
}

$fileDir = pathinfo($filePath, PATHINFO_DIRNAME);
if (! file_exists($fileDir)) {
    mkdir($fileDir, 0777, 1);
}
file_put_contents($filePath, $log, $isPost ? FILE_APPEND : 0);

echo $viewPath;