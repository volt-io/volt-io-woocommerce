<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit846be453e36a05e42a0284084b2b1068
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInit846be453e36a05e42a0284084b2b1068', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInit846be453e36a05e42a0284084b2b1068', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInit846be453e36a05e42a0284084b2b1068::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
