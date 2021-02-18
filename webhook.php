<?php
$configs = include_once "./.ignore-git/config.php";
$postRaw = file_get_contents('php://input');
$signature = preg_replace('/^sha256=/', '', $_SERVER['HTTP_X_HUB_SIGNATURE_256']);
$post = json_decode($postRaw, true);
$key = "push:{$post['repository']['name']}";
if (key_exists($key, $configs)) {
    $config = $configs[$key];
    if ($signature === hash_hmac('sha256', $postRaw, $config['secret'])) {
        $branch = preg_replace('|^refs/heads/|', '', $post['ref']);
        // 异步执行脚本
        shell_exec("{$config['script']} {$branch} | at now & disown");
    }
}