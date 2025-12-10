## Toast

> use Kingbes\PebView\Toast;

## Methods

### Show

Static function
 - `show` Show toast
    Parameters
  - `string` `$app` Application name
  - `string` `$title` Toast title
  - `string` `$message` Toast message
  - `string` `$icon` Toast icon, default is empty string
    Returns
  - `bool` Whether the toast is shown successfully

Usage:
```PHP
Toast::show("Application name", "Toast title", "Toast message");
```

