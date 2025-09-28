## Window Class

> use Kingbes\PebView\Window;

## Methods

### Create a Window Object

Constructor
 - `new Window` Creates a window object.
    Parameters
  - `bool` `$debug` Whether to enable debug mode. Default: false
    Returns
  - `Window` Returns a window object.

Usage:
```PHP
$win = new Window(true);
```

### Destroy Window

Public Method
 - `destroy` Destroys the window.
    Parameters
  - None
    Returns
  - `void` No return value.

Usage:
```PHP
// Must be called after the run() method.
$win->destroy();
```

### Run Window

Public Method
 - `run` Runs the window.
    Parameters
  - None
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called after the setHtml() or navigate() method.
$win->run();
```

### Terminate Window

Public Method
 - `terminate` Terminates the window.
    Parameters
  - None
    Returns
  - `void` No return value.

Usage:
```PHP
// Used to close the application.
$win->terminate();
```

### Dispatch Function

Schedule the functions to be called on the thread that has a running/event loop.
This feature can be used, for example, to interact with libraries or native objects.

Public Method
 - `dispatch` Dispatches the function to be called on the thread that has a running/event loop.
    Parameters
  - `callable` `$callback` The function to be called.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Used to interact with libraries or native objects.
$win->dispatch(function($win, $arg) {
    // Interact with libraries or native objects.
    // $win is the window object.
    // $arg is the argument passed to the dispatch() method.
});
```

### Set Window Icon

Windows requires the ICO format; Linux requires the PNG format; MacOs requires the ICO format.
Usually, only the Windows system will work, while Linux and MacOs do not have this effect.
 
Public Method
 - `setIcon` Sets the window icon.
    Parameters
  - `string` `$path` The path to the icon file.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->setIcon("path/to/icon.ico");
```

### Set Window Title

Public Method
 - `setTitle` Sets the window title.
    Parameters
  - `string` `$title` The window title name.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->setTitle("PebView");
```

### Set Window Size

Public Method
 - `setSize` Sets the window size.
    Parameters
  - `int` `$width` The window width.
  - `int` `$height` The window height.
  - `WindowHint` `$hint` The window hint. Default: WindowHint::None
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->setSize(800, 600, WindowHint::None);
```

### Initialize JavaScript

Will load the js code before the window.onload event.

Public Method
 - `init` Initializes the JavaScript.
    Parameters
  - `string` `$js` The JavaScript code to be initialized.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->init("console.log('hello PebView!');");
```

### Evaluate JavaScript

Public Method
 - `eval` Evaluates the JavaScript code.
    Parameters
  - `string` `$js` The JavaScript code to be evaluated.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
$win->eval("console.log('hello PebView!');");
```

### Set Window HTML Content

Public Method
 - `setHtml` Sets the window HTML content.
    Parameters
  - `string` `$html` The HTML content to be set.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->setHtml("<h1>hello PebView!</h1>");
```

### Navigate Window to Specified URL

Public Method
 - `navigate` Navigates the window to the specified URL.
    Parameters
  - `string` `$url` The URL to navigate to.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// The  and  methods must be called before the run() method. And only one of the two can be selected.
$win->navigate("https://www.baidu.com");
```

### Bind JS Function

Public function
 - `bind` Binds a JS function.
    Parameters
  - `string` `$name` The function name.
  - `callable` `$callback` The function to call.
    Returns
  - `Window` Returns the window object.

用法：
```PHP
$win->bind("hello", function(...$params) {
    // $win is the window object
    // $params are the parameters passed to the hello() method
    // Use params[0],params[1], ... to access the parameters
    // Example: params[0] is the first parameter, params[1] is the second, etc.
    return $params[0] . " " . $params[1];
});
```

### Unbind JS Function

Public function
 - `unbind` Unbinds a JS function.
    Parameters
  - `string` `$name` The function name.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
$win->unbind("hello");
```

### Set Window Close Event

Public function
 - `setCloseCallback` Sets the window close event callback.
    Parameters
  - `callable` `$callback` The function to call.
    Returns
  - `Window` Returns the window object.

用法：
```PHP
$win->setCloseCallback(function($win) {
    // $win is the window object
    // Perform some operations when closing the window
    return true; // Return true to allow closing the window
    return false; // Return false to prevent closing the window
});
```

### Show Window

Public function
 - `show` Shows the window.
    Parameters
  - None
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
$win->show();
```

### Hide Window

Public function
 - `hide` Hides the window.
    Parameters
  - None
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
$win->hide();
```

### Create Tray

Windows requires the ICO format; Linux requires the PNG format; MacOs requires the ICO format.
Usually, only the Windows system will work, while Linux and MacOs do not have this effect.

Public function
 - `tray` Creates the tray icon.
    Parameters
  - `string` `$path` The path to the icon file.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->tray("path/to/icon.ico");
```

### Tray Menu

Public function
 - `trayMenu` Creates the tray menu.
    Parameters
  - `array` `$menu` The menu array.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->trayMenu([
    "text" => "menu1", // Menu name
    "disabled" => 0, // Whether clickable 0 clickable
    "cb" => function($win) { // The function to call when the menu is clicked
        // $win is the window object
        // You can perform some operations when the menu is clicked
    },
    "text" => "menu2",
    "cb" => function($win) {
        // $win is the window object
        // You can perform some operations when the menu is clicked
    },
    ...
]);
```