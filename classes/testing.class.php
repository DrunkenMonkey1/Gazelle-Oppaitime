<?php

declare(strict_types=1);

class Testing
{
    /**
     * @var mixed[]
     */
    private static array $ClassDirectories = ["classes"];
    /**
     * @var mixed[]
     */
    private static array $Classes = [];
    
    /**
     * Initialize the testasble classes into a map keyed by class name
     */
    public static function init(): void
    {
        self::load_classes();
    }
    
    /**
     * Loads all the classes within given directories
     */
    private static function load_classes(): void
    {
        foreach (self::$ClassDirectories as $Directory) {
            $Directory = SERVER_ROOT . "/" . $Directory . "/";
            foreach (glob($Directory . "*.php") as $FileName) {
                self::get_class_name($FileName);
            }
        }
    }
    
    /**
     * Gets the class and adds into the map
     *
     * @param $FileName
     *
     * @throws \ReflectionException
     */
    private static function get_class_name($FileName): void
    {
        $Tokens = token_get_all(file_get_contents($FileName));
        $IsTestable = false;
        $IsClass = false;
        
        foreach ($Tokens as $Token) {
            if (is_array($Token)) {
                if (!$IsTestable && $Token[0] === T_DOC_COMMENT && strpos($Token[1], "@TestClass")) {
                    $IsTestable = true;
                }
                if ($IsTestable && $Token[0] === T_CLASS) {
                    $IsClass = true;
                } elseif ($IsClass && $Token[0] === T_STRING) {
                    $ReflectionClass = new ReflectionClass($Token[1]);
                    if (count(self::get_testable_methods($ReflectionClass))) {
                        self::$Classes[$Token[1]] = new ReflectionClass($Token[1]);
                    }
                    $IsTestable = false;
                    $IsClass = false;
                }
            }
        }
    }
    
    
    /**
     * Get testable methods in a class, a testable method has a @Test
     *
     * @param $Class
     *
     * @return mixed[]
     */
    public static function get_testable_methods($Class): array
    {
        $ReflectionClass = is_string($Class) ? self::$Classes[$Class] : $Class;
        $ReflectionMethods = $ReflectionClass->getMethods();
        $TestableMethods = [];
        foreach ($ReflectionMethods as $Method) {
            if ($Method->isPublic() && $Method->isStatic() && strpos($Method->getDocComment(), "@Test")) {
                $TestableMethods[] = $Method;
            }
        }
        
        return $TestableMethods;
    }
    
    /**
     * Gets the class
     *
     * @return mixed[]
     */
    public static function get_classes(): array
    {
        return self::$Classes;
    }
    
    /**
     * Checks if class exists in the map
     *
     * @param $Class
     *
     * @return bool
     */
    public static function has_class($Class): bool
    {
        return array_key_exists($Class, self::$Classes);
    }
    
    /**
     * Checks if class has a given testable methood
     *
     * @param $Class
     * @param $Method
     *
     * @return bool
     */
    public static function has_testable_method($Class, $Method): bool
    {
        $TestableMethods = self::get_testable_methods($Class);
        foreach ($TestableMethods as $TestMethod) {
            if ($TestMethod->getName() === $Method) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the class comment
     *
     * @param $Class
     *
     * @return string
     */
    public static function get_class_comment($Class): string
    {
        $ReflectionClass = self::$Classes[$Class];
        
        return trim(str_replace(["@TestClass", "*", "/"], "", $ReflectionClass->getDocComment()));
    }
    
    /**
     * Get the undocumented methods in a class
     *
     * @param $Class
     *
     * @return mixed[]
     */
    public static function get_undocumented_methods($Class): array
    {
        $ReflectionClass = self::$Classes[$Class];
        $Methods = [];
        foreach ($ReflectionClass->getMethods() as $Method) {
            if (!$Method->getDocComment()) {
                $Methods[] = $Method;
            }
        }
        
        return $Methods;
    }
    
    /**
     * Get the documented methods
     *
     * @param $Class
     *
     * @return mixed[]
     */
    public static function get_documented_methods($Class): array
    {
        $ReflectionClass = self::$Classes[$Class];
        $Methods = [];
        foreach ($ReflectionClass->getMethods() as $Method) {
            if ($Method->getDocComment()) {
                $Methods[] = $Method;
            }
        }
        
        return $Methods;
    }
    
    /**
     * Get all methods in a class
     *
     * @param $Class
     *
     * @return mixed
     */
    public static function get_methods($Class)
    {
        return self::$Classes[$Class]->getMethods();
    }
    
    /**
     * Get a method  comment
     *
     * @param $Method
     *
     * @return string
     */
    public static function get_method_comment($Method): string
    {
        return trim(str_replace(["*", "/"], "", $Method->getDocComment()));
    }
}
