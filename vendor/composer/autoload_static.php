<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit846be453e36a05e42a0284084b2b1068
{
    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInit846be453e36a05e42a0284084b2b1068::$classMap;

        }, null, ClassLoader::class);
    }
}
