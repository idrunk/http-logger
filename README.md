# HTTP记录器

Http记录器, 主用于异步编程时, 调试记录回调接口, 方便根据记录的异步内容解决程序问题

## 记录

### GET方式

同POST方式，但仅捕获打印url参数

```sh
curl http://logger.drunkce.com/path/childpath?query_key=key
```

### POST方式

- 常规POST，将捕获记录POST内容及记录时间与$_SERVER变量中请求端的相关信息
```sh
curl -X POST -d "key1=1&key2=2" http://logger.drunkce.com/path/childpath
```
- Dce debug POST，仅捕获记录POST内容，不自动拼装记录时间及$_SERVER变量数据
```sh
curl -X POST -H "Dce-Debug:1" -d $'key1=1\nkey2=2' http://logger.drunkce.com/path/childpath
```

- 尾部追加模式（默认）
```sh
curl -X POST -H "Dce-Debug:1" -H "Log-Type:append" -d $'key1=1\nkey2=2' http://logger.drunkce.com/path/childpath
```
- 头部追加模式
```sh
curl -X POST -H "Dce-Debug:1" -H "Log-Type:prepend" -d $'key1=1\nkey2=2' http://logger.drunkce.com/path/childpath
```
- 替换模式，每次新请求会自动清空原内容并写入当前POST内容
```sh
curl -X POST -H "Dce-Debug:1" -H "Log-Type:replace" -d $'key1=1\nkey2=2' http://logger.drunkce.com/path/childpath
```

### DELETE方式

发送DELETE请求记录时，若请求路径记录文件已存在，则会被删除，否则不会做任何操作

```sh
curl -X DELETE http://logger.drunkce.com/path/childpath
```

## 查看记录

每次记录都会返回一个路径，这个路径就是记录结果的查看路径

你也可以手动将"_"作为根路径插入原请求记录路径，即为查看该记录结果路径，不区分请求方式，如:

```sh
curl http://logger.drunkce.com/_/path/childpath
```