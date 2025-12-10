## Toast

> use Kingbes\PebView\Toast;

## 方法

### 通知

静态函数
 - `show` 通知
    参数
  - `string` `$app` 应用名称
  - `string` `$title` 通知标题
  - `string` `$message` 通知消息
  - `string` `$icon` 通知图标 默认为空字符串
    返回
  - `bool` 是否成功

用法:
```PHP
Toast::show("应用名称", "通知标题", "这是一条消息");
```

