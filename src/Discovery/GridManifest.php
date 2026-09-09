<?php

namespace Popov\DatagridBundle\Discovery;

class GridManifest
{
    public static function dump(array $grids, $file)
    {
        $dirname = dirname($file);

        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
        }

        $content = "<?php\n\nreturn "
            . var_export($grids, true)
            . ";\n";

        $tmp = $file . '.' . uniqid('', true);

        file_put_contents($tmp, $content, LOCK_EX);

        rename($tmp, $file);
    }

    public static function load($file)
    {
        return require $file;
    }
}
