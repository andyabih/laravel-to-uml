<?php

namespace Andyabih\LaravelToUML;

use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class LaravelToUML {
    protected $defaultFiles = [
        'Http/Kernel.php',
        'Console/Kernel.php',
        'Exceptions/Handler.php',
        'Http/Controllers/Controller.php',
        'Http/Middleware/Authenticate.php',
        'Http/Middleware/EncryptCookies.php',
        'Http/Middleware/PreventRequestsDuringMaintenance.php',
        'Http/Middleware/RedirectIfAuthenticated.php',
        'Http/Middleware/TrimStrings.php',
        'Http/Middleware/TrustHosts.php',
        'Http/Middleware/TrustProxies.php',
        'Http/Middleware/VerifyCsrfToken.php',
        'Providers/AppServiceProvider.php',
        'Providers/AuthServiceProvider.php',
        'Providers/BroadcastServiceProvider.php',
        'Providers/EventServiceProvider.php',
        'Providers/RouteServiceProvider.php',
    ];

    protected $classes = [];

    public function create() {
        $this->load();
        return $this;
    }

    public function load() {
        $files = $this->getFiles();
        $this->loadFiles($files);
    }

    protected function getFiles() {
        $finder = new Finder();
        $finder
            ->files()
            ->in(base_path() . DIRECTORY_SEPARATOR . "app")
            ->name("*.php")
            ->notPath($this->defaultFiles)
            ->sortByName();
        return $finder;
    }

    public function getSource() {
        $sources = [];
        foreach($this->classes as $c) {
            $properties = [];
            $methods = [];
            
            foreach($c['properties'] as $property) {
                $properties[] = $property->name;
            }

            foreach($c['methods'] as $method) {
                $methods[] = $method->name . "()";
            }

            $source = "[" . $c['name'];
            if(!empty($properties)) $source .= "|" . implode(';', $properties);
            if(!empty($methods)) $source .=  "|" . implode(';', $methods);
            $source .= "]";
            $sources[] = $source;

            if(!empty($c['fromRelationships'])) {
                foreach($c['fromRelationships'] as $fromClass) {
                    $sources[] = "[{$fromClass}]->[{$c['name']}]";
                }
            }

            if(!empty($c['hasManyRelationships'])) {
                foreach($c['hasManyRelationships'] as $fromClass) {
                    $sources[] = "[{$fromClass}] hasMany ->[{$c['name']}]";
                }
            }

            if(!empty($c['belongsToRelationships'])) {
                foreach($c['belongsToRelationships'] as $fromClass) {
                    $sources[] = "[{$fromClass}] belongsTo ->[{$c['name']}]";
                }
            }
        }

        return implode("\n", $sources);
    }

    protected function loadFiles($files) {
        foreach($files as $file) {
            $path = $this->classNamespaceToPath(str_replace(".php", "", 'app\\' . $file->getRelativePathname()));
            $fullClassName = $this->getNamespaceFromPath($path);
            $this->classes[$fullClassName] = [];
        }

        foreach($files as $file) {
            $path = $this->classNamespaceToPath(str_replace(".php", "", 'app\\' . $file->getRelativePathname()));
            $this->reflectClass($path);
        }
    }

    protected function reflectClass($classPath) {
        $className = $this->getClassFromPath($classPath);
        
        $fullClassName = $this->getNamespaceFromPath($classPath);
        $classReflection = (new ReflectionClass($fullClassName));
        $properties = $classReflection->getProperties();
        $properties = array_filter($properties, function($property) use($fullClassName) {
            return $property->class == $fullClassName;
        });

        $methods = $classReflection->getMethods();
        $traits = class_uses_recursive($fullClassName);
        $traitMethods = [];
        foreach($traits as $trait) {
            $traitReflection = new ReflectionClass($trait);
            foreach($traitReflection->getMethods() as $method) {
                $traitMethods[$method->name] = $method->name;
            }
        }

        $methods = array_filter($methods, function($method) use($fullClassName, $traitMethods) {
            return $method->class == $fullClassName && !in_array($method->name, $traitMethods);
        });
        
        $fromRelationships      = [];
        $hasManyRelationships   = [];
        $belongsToRelationships = [];
        
        foreach($methods as $method) {
            foreach($method->getParameters() as $parameter) {
                $typeHint = $parameter->getType();
                if(isset($typeHint) && Str::contains($typeHint->getName(), '\\')) {
                    $typePath = $this->classNamespaceToPath($typeHint->getName());
                    $typeClass = $this->getClassFromPath($typePath);
                    $this->reflectClass($typePath);
                    $fromRelationships[$typeClass] = $typeClass;
                }
            }
        }

        $fileContents = file_get_contents($classPath);
        $fileLines = explode("\n", $fileContents);
        foreach($fileLines as $line) {
            $line = trim($line);
            if(Str::startsWith($line, 'use ')) {
                $explodedName = explode(" ", $line);
                $classNamespace = str_replace(";", "", end($explodedName));
                if(!in_array($classNamespace, array_keys($this->classes))) continue;
                $namespaceExploded = explode("\\", $line);
                $relatedClassName = str_replace(";", "", end($namespaceExploded));
                $fromRelationships[$relatedClassName] = $relatedClassName;
            }
            if(Str::startsWith($line, 'return $this->belongsTo')) {
                preg_match('/belongsTo\((.*)\)/', $line, $matches);
                $belongsToClass = trim($matches[1], '"');
                $belongsToClass = trim($belongsToClass, "'");
                $belongsToClass = str_replace("::class", "", $belongsToClass);
                $belongsToRelationships[$belongsToClass] = $belongsToClass;
            }
            if(Str::startsWith($line, 'return $this->hasMany')) {
                preg_match('/hasMany\((.*)\)/', $line, $matches);
                $hasManyClass = trim($matches[1], '"');
                $hasManyClass = trim($hasManyClass, "'");
                $hasManyClass = str_replace("::class", "", $hasManyClass);
                $hasManyRelationships[$hasManyClass] = $hasManyClass;
            }
        }

        $this->classes[$fullClassName] = [
            'name'                   => $className,
            'properties'             => $properties,
            'methods'                => $methods,
            'fromRelationships'      => $fromRelationships,
            'belongsToRelationships' => $belongsToRelationships,
            'hasManyRelationships'   => $hasManyRelationships,
        ];
    }

    protected function namespaceToPath($namespace) {
        if(Str::startsWith($namespace, '\\')) $namespace = substr($namespace, 1);
        $namespace = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
        $namespace = str_replace('App', 'app', $namespace);

        return base_path() . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR;
    }

    protected function classNamespaceToPath($namespace) {
        if(Str::startsWith($namespace, '\\')) $namespace = substr($namespace, 1);
        $namespace = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
        $namespace = str_replace('App', 'app', $namespace);

        return base_path() . DIRECTORY_SEPARATOR . $namespace . '.php';
    }

    protected function getNamespaceFromPath($path) {
        $path = str_replace(base_path(), "", $path);
        if(Str::startsWith($path, '\\')) $path = substr($path, 1);
        $path = str_replace(".php", "", $path);
        $explodedPath = explode(DIRECTORY_SEPARATOR, $path);
        $explodedPath = array_map(function($p) {
            return ucfirst($p);
        }, $explodedPath);
        $path = implode("\\", $explodedPath);
        return $path;
    }

    protected function getClassFromPath($path) {
        $explodedPath = explode(DIRECTORY_SEPARATOR, $path);
        $finalFile = str_replace(".php", "", end($explodedPath));
        return $finalFile;
    }

    protected function modelsNamespace() {
        return config('laravel-to-uml.models_namespace');
    }
}