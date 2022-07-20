<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit4cb50bf6bb076c563b7e41721010aaef
{
    public static $files = array (
        '538ca81a9a966a6716601ecf48f4eaef' => __DIR__ . '/..' . '/opis/closure/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'O' => 
        array (
            'Ozaretskii\\PhpSnakeMqp\\' => 23,
            'Opis\\Closure\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Ozaretskii\\PhpSnakeMqp\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Opis\\Closure\\' => 
        array (
            0 => __DIR__ . '/..' . '/opis/closure/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit4cb50bf6bb076c563b7e41721010aaef::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit4cb50bf6bb076c563b7e41721010aaef::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit4cb50bf6bb076c563b7e41721010aaef::$classMap;

        }, null, ClassLoader::class);
    }
}
