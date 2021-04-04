<?php

namespace Andyabih\LaravelToUML;

use Illuminate\Support\Str;
use ReflectionClass;

class LaravelToUML {
    protected $classes = [];

    public function create() {
        $this->load();
        return $this;
    }

    public function load() {
        $this->loadClassesFromNamespace('models', $this->modelsNamespace());
        $this->loadClassesFromNamespace('controllers', config('laravel-to-uml.controllers_namespace'));
    }

    public function getSource() {
        $controllers = [];
        $models = [];
        $relationships = [];

        foreach($this->classes as $c) {
            $type = $c['type'];
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
            $$type[] = $source;

            if(!empty($c['fromRelationships'])) {
                foreach($c['fromRelationships'] as $fromClass) {
                    $$type[] = "[{$fromClass}]->[{$c['name']}]";
                }
            }
        }
        // $controllers = "[<package>Controllers|" . implode("\n", $controllers) . "]";
        // $models = "[<package>Models|" . implode("\n", $models) . "]";
        $controllers = implode("\n", $controllers);
        $models = implode("\n", $models);
        $relationships = implode("\n", $relationships);

        $sources = [$controllers, $models, $relationships];
        return implode("\n", $sources);
    }

    protected function loadClassesFromNamespace($name, $namespace) {
        $path = $this->namespaceToPath($namespace);
        foreach(glob($path . '*.php') as $classPath) {
            $className = $this->getClassFromPath($classPath);
            
            if($className == 'Controller') continue; // Skipping the default Laravel Controller file

            $fullClassName = $namespace . "\\" . $className;
            $classReflection = (new ReflectionClass($fullClassName));
            
            $properties = $classReflection->getProperties();
            $properties = array_filter($properties, function($property) use($fullClassName) {
                return $property->class == $fullClassName;
            });

            $methods = $classReflection->getMethods();
            $methods = array_filter($methods, function($method) use($fullClassName) {
                return $method->class == $fullClassName;
            });

            $fromRelationships = [];
            if($name == 'controllers') {
                $fileContents = file_get_contents($classPath);
                $fileLines = explode("\n", $fileContents);
                foreach($fileLines as $line) {
                    if(Str::startsWith($line, 'use ' . $this->modelsNamespace())) {
                        $namespaceExploded = explode("\\", $line);
                        $relatedClassName = str_replace(";", "", end($namespaceExploded));
                        $fromRelationships[$relatedClassName] = $relatedClassName;
                    }
                }
            }

            $this->classes[$className] = [
                'name'              => $className,
                'type'              => $name,
                'properties'        => $properties,
                'methods'           => $methods,
                'fromRelationships' => $fromRelationships
            ];
        }
    }

    protected function namespaceToPath($namespace) {
        if(Str::startsWith($namespace, '\\')) $namespace = substr($namespace, 1);
        $namespace = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
        $namespace = str_replace('App', 'app', $namespace);

        return base_path() . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR;
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