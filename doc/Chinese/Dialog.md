## 对话框

> use Kingbes\PebView\Dialog;

## 方法

### 消息对话框

静态函数
 - `msg` 消息对话框
    参数
  - `string` `$message` 对话框消息
  - `DialogLevel` `$level` 对话框级别 默认为 DialogLevel::Info
  - `DialogBtn` `$buttons` 对话框按钮 默认为 DialogBtn::Ok
    返回
  - `bool` 是否点击了确定按钮

用法:
```PHP
Dialog::msg("这是一条消息", DialogLevel::Info, DialogBtn::Ok);
```

### 输入对话框

静态函数
 - `prompt` 输入对话框
    参数
  - `string` `$message` 对话框消息
  - `DialogLevel` `$level` 对话框级别 默认为 DialogLevel::Info
  - `string` `$text` 对话框默认文本 默认为空字符串
    返回
  - `string` 用户输入的文本

用法:
```PHP
Dialog::prompt("请输入您的姓名", DialogLevel::Info, "");
```

### 文件选择对话框

静态函数
 - `file` 文件选择对话框
    参数
  - `string` `$dir` 对话框默认目录
  - `string` `$filename` 对话框默认文件名
  - `FileAction` `$action` 文件操作类型
    返回
  - `string` 用户选择的文件路径

用法:
```PHP
Dialog::file("C:\\", "test.txt", FileAction::Open);
```
