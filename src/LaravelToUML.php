<?php

namespace Andyabih\LaravelToUML;

use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class LaravelToUML {
    /**
     * Model relationship types.
     * 
     * @var array
     */
    protected $relationships = [
        'hasOne'         => 'to',
        'hasOneThrough'  => 'to',
        'hasMany'        => 'to',
        'hasManyThrough' => 'to',
        'belongsTo'      => 'from',
        'belongsToMany'  => 'from',
        'morphOne'       => 'to',
        'morphMany'      => 'to',
        'morphToMany'    => 'to',
        'morphedByMany'  => 'from',
    ];

    /**
     * All the classes that were indexed.
     * 
     * @var array
     */
    protected $classes = [];

    /**
     * Create a new instance of the Laravel to UML.
     * 
     * @return \Andyabih\LaravelToUML
     */
    public function create() {
        $files = $this->getListOfFiles();
        $this->loadFiles($files);
        return $this;
    }

    /**
     * Preload all the files to get all the class names.
     * This is helpful to define relationships between classes.
     * 
     * @param Symfony\Component\Finder\Finder $files
     * @return void
     */
    protected function preloadFiles($files) {
        foreach($files as $file) {
            $namespace = $this->prefixWithApp(
                $this->removePHPExtension($file->getRelativePathname())
            );

            $namespace = str_replace("/", "\\", $namespace);
            if(Str::startsWith($namespace, '\\')) $namespace = substr($namespace, 1);

            $this->classes[$namespace] = [];
        }
    }

    /**
     * Loop through and start indexing all the classes.
     * 
     * @param Symfony\Component\Finder\Finder $files
     * @return void
     */
    protected function loadFiles($files) {
        $this->preloadFiles($files);
        foreach($files as $file) {
            $namespace = $this->prefixWithApp(
                $this->removePHPExtension($file->getRelativePathname())
            );
            $path = $this->namespaceToPath($namespace, '.php');
            $this->reflectClass($path);
        }
    }

    /**
     * Turn the classes array into a nomnoml schema.
     * https://github.com/skanaar/nomnoml
     * 
     * @return string
     */
    public function getSource() {
        $sources = [...$this->getStyling()];
        foreach($this->classes as $c) {
            if(! isset($c['properties'])) continue;
            
            $properties = [];
            $methods = [];
            
            foreach($c['properties'] as $property) {
                $properties[] = $property->name;
            }

            foreach($c['methods'] as $method) {
                $methods[] = $method->name . "()";
            }

            $source = "[{$c['name']}";
            if(!empty($properties)) $source .= "|" . implode(';', $properties);
            if(!empty($methods)) $source .=  "|" . implode(';', $methods);
            $source .= "]";
            $sources[] = $source;

            if(!empty($c['fromRelationships'])) {
                foreach($c['fromRelationships'] as $fromClass) {
                    $sources[] = "[{$fromClass}]<-[{$c['name']}]";
                }
            }

            if(!empty($c['relationships'])) {
                foreach($c['relationships'] as $fromClass) {
                    $sources[] = $fromClass['direction'] == 'to' ?
                        "[{$fromClass['name']}] <-  {$fromClass['type']} [{$c['name']}]" :
                        "[{$c['name']}] {$fromClass['type']} -> [{$fromClass['name']}]";
                }
            }
        }

        return implode("\n", $sources);
    }

    /**
     * Returns the configurable nomnoml styling.
     * 
     * @return array
     */
    protected function getStyling() {
        $styles = [];
        foreach(config('laravel-to-uml.style') as $key => $value) {
            $styles[] = "#{$key}: $value";
        }
        return $styles;
    }
    /**
     * Create a Reflection of the class and save methods,
     * properties, and relationships.
     * 
     * @param string $classPath
     * @return void
     */
    protected function reflectClass($classPath) {
        $className     = $this->getClassFromPath($classPath);
        $fullClassName = $this->getNamespaceFromPath($classPath);
        $fileLines     = $this->getLinesInFile($classPath);

        if(! class_exists($fullClassName)) return false;

        $classReflection   = new ReflectionClass($fullClassName);
        $traitMethods      = $this->getTraits($fullClassName);

        $this->classes[$fullClassName] = [
            'name'              => $className,
            'properties'        => $this->getProperties($classReflection, $fullClassName),
            'methods'           => $this->getMethods($classReflection, $fullClassName, $traitMethods),
            'fromRelationships' => $this->getNamespaceRelationships($fileLines),
            'relationships'     => $this->getModelRelationships($fileLines)
        ];
    }

    /**
     * Get the classes related to others.
     * 
     * @param array $fileLines
     * @return array
     */
    protected function getNamespaceRelationships($fileLines) {
        $namespaceRelationships = [];
        foreach($fileLines as $line) {
            if(Str::startsWith($line, 'use ')) {
                $explodedName = explode(" ", $line);
                $classNamespace = str_replace(";", "", end($explodedName));

                if(!in_array($classNamespace, array_keys($this->classes))) continue; // Only track relationships from other tracked classes

                $namespaceExploded = explode("\\", $line);
                $relatedClassName = str_replace(";", "", end($namespaceExploded));
                $namespaceRelationships[$relatedClassName] = $relatedClassName;
            }
        }
        return $namespaceRelationships;
    }

    /**
     * Get the relationships between models.
     * 
     * @param array $fileLines
     * @return array
     */
    protected function getModelRelationships($fileLines) {
        $relationships = [];
        foreach($fileLines as $line) {
            foreach($this->relationships as $relationship => $direction) {
                if(Str::startsWith($line, 'return $this->'.$relationship.'(')) {
                    preg_match("/{$relationship}\((.*)\)/", $line, $matches);
                    if(!isset($matches[1])) {
                        preg_match("/{$relationship}\((.*)\,/", $line, $matches);
                    }
                    $match                 = explode(",", $matches[1]);
                    $relationshipClass     = trim($match[0], '"');
                    $relationshipClass     = trim($relationshipClass, "'");
                    $relationshipClass     = str_replace("::class", "", $relationshipClass);
                    $relationshipClassName = explode("\\", $relationshipClass);

                    $relationships[$relationshipClass] = [
                        'type'      => $relationship,
                        'name'      => end($relationshipClassName),
                        'direction' => $direction
                    ];
                }
            }
        }
        return $relationships;
    }

    /**
     * Get the properties inside a Reflection class.
     * 
     * @param ReflectionClass $reflectionClass
     * @param string $fullClassName
     * @return array
     */
    protected function getProperties($classReflection, $fullClassName) {
        return $this->filterProperties($classReflection->getProperties(), $fullClassName);
    }

    /**
     * Get the properties inside a Reflection class.
     * 
     * @param ReflectionClass $reflectionClass
     * @param string $fullClassName
     * @param array $traitMethods
     * @return array
     */
    protected function getMethods($classReflection, $fullClassName, $traitMethods) {
        return $this->filterProperties($classReflection->getMethods(), $fullClassName, $traitMethods);
    }

    /**
     * Filter to return without parent properties/methods.
     * 
     * @param array $properties
     * @param string $fullClassName
     * @param array $excludeMethods
     * @return array
     */
    protected function filterProperties($properties, $fullClassName, $excludeMethods = []) {
        return array_filter($properties, function($property) use($fullClassName, $excludeMethods) {
            return $property->class == $fullClassName && !in_array($property->name, $excludeMethods);
        });
    }

    /**
     * Find and parse the class traits.
     * 
     * @param string $fullClassName
     * @return array
     */
    protected function getTraits($fullClassName) {
        $traits       = class_uses_recursive($fullClassName);
        $traitMethods = [];
        foreach($traits as $trait) {
            $traitReflection = new ReflectionClass($trait);
            
            foreach($traitReflection->getMethods() as $method) {
                $traitMethods[$method->name] = $method->name;
            }
        }

        return $traitMethods;
    }

    /**
     * Return a list of files to index.
     * 
     * @return Symfony\Component\Finder\Finder
     */
    protected function getListOfFiles() {
        // Using the configuration to know which folders we should exclude.
        $excludes = [];
        foreach(config('laravel-to-uml.directories') as $configKey => $folder) {
            if(!config("laravel-to-uml.{$configKey}")) $excludes[] = $folder;
        }

        $finder = new Finder();
        $finder
            ->files()
            ->in(base_path() . DIRECTORY_SEPARATOR . "app")
            ->name("*.php")
            ->notPath([...config('laravel-to-uml.excludeFiles'), ...$excludes])
            ->sortByName();
        return $finder;
    }

    /**
     * Remove the .php extension.
     * 
     * @param string $path
     * @return string
     */
    protected function removePHPExtension($path) {
        return str_replace(".php", "", $path);
    }

    /**
     * Prefix a path with the App namespace.
     * 
     * @param string $path
     * @return string
     */
    protected function prefixWithApp($path) {
        return 'App\\' . $path;
    }

    /**
     * Turn in a namespace into a full file path.
     * 
     * @param string $namespace
     * @param string $append
     * @return string
     */
    protected function namespaceToPath($namespace, $append = DIRECTORY_SEPARATOR) {
        if(Str::startsWith($namespace, '\\')) $namespace = substr($namespace, 1);
        $namespace = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
        $namespace = preg_replace('/App/', 'app', $namespace, 1);

        return base_path() . DIRECTORY_SEPARATOR . $namespace . $append;
    }

    /**
     * Return the namespace from a file path.
     * 
     * @param string $path
     * @return string
     */
    protected function getNamespaceFromPath($path) {
        $path = str_replace(base_path(), "", $path);
        if(Str::startsWith($path, '\\') || Str::startsWith($path, '/')) $path = substr($path, 1);
        $path = str_replace(".php", "", $path);
        $explodedPath = explode(DIRECTORY_SEPARATOR, $path);
        $explodedPath = array_map(function($p) {
            return ucfirst($p);
        }, $explodedPath);
        $path = implode("\\", $explodedPath);
        return $path;
    }

    /**
     * Return the class name from path.
     * 
     * @param string $path
     * @return string
     */
    protected function getClassFromPath($path) {
        $explodedPath = explode(DIRECTORY_SEPARATOR, $path);
        $finalFile = str_replace(".php", "", end($explodedPath));
        return $finalFile;
    }

    /**
     * Get all the lines in a file.
     * 
     * @param string $filePath
     * @return array
     */
    protected function getLinesInFile($filePath) {
        $fileContents = file_get_contents($filePath);
        $lines = explode("\n", $fileContents);
        return array_map('trim', $lines);
    }
}