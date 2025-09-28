## Dialog Box

> use Kingbes\PebView\Dialog;

## Methods

### Message Dialog

Static function
 - `msg` Message dialog box
    Parameters
  - `string` `$message` Dialog message
  - `DialogLevel` `$level` Dialog level, defaults to DialogLevel::Info
  - `DialogBtn` `$buttons` Dialog buttons, defaults to DialogBtn::Ok
    Returns
  - `bool` Whether the Ok button was clicked

用法:
```PHP
Dialog::msg("This is a message", DialogLevel::Info, DialogBtn::Ok);
```

### Input Dialog

Static function
 - `prompt` Input dialog box
    Parameters
  - `string` `$message` Dialog message
  - `DialogLevel` `$level` Dialog level, defaults to DialogLevel::Info
  - `string` `$text` Dialog default text, defaults to empty string
    Returns
  - `string` Text entered by the user

Usage:
```PHP
Dialog::prompt("Please enter your name", DialogLevel::Info, "");
```

### File Select Dialog

Static function
 - `file` File select dialog box
    Parameters
  - `string` `$dir` Dialog default directory
  - `string` `$filename` Dialog default filename
  - `FileAction` `$action` File action type
    Returns
  - `string` Path of the selected file

Usage:
```PHP
Dialog::file("C:\\", "test.txt", FileAction::Open);
```
