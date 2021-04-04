![Laravel To UML Example](https://user-images.githubusercontent.com/10498402/113521629-53055b80-95a3-11eb-8f56-d2e1de856345.png)

# Laravel UML Diagram Generator
Automagically generate UML diagrams of your Laravel code.

# Installation
To install LTU via composer, run the command:
```
composer require andyabih/laravel-to-uml --dev
```

# Usage
LTU will register the `/uml` route by default to a view that displays your UML graph.

You can configure the package and tweak it to fit your needs by publishing the config file using:
```
php artisan vendor:publish --provider="Andyabih\LaravelToUML\LaravelToUMLServiceProvider" --tag="config"
```
This will create a new `laravel-to-uml.php` file in your `config` folder.

# Configuration
The configuration should hopefully be self-explanatory. You can change what type of classes get included in the diagram by changing the `true|false` boolean in the configuration file. 

You can also change the styling of the diagram in the config. LTU uses [nomnoml](https://github.com/skanaar/nomnoml) to generate the diagram, so more information about the different nomnoml styling properties can be found on their Github.

# Exporting the diagram
nomnoml generates the diagram in a canvas, and you can simply right click & save the canvas to an image.

# Importing requirements
Your classes must be imported using the `use` operator.
```php
// This will work and generate everything properly.
use App\Models\Post;

// Using it directly in the code without the use operator won't.
$posts = \App\Models\Post::all();
```
