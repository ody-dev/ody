<?php
declare(strict_types=1);

return function () {
    $files = [
        __DIR__ . '/../../../../vendor/autoload.php',  // composer dependency
        __DIR__ . '/../../vendor/autoload.php', // stand-alone package
    ];
    foreach ($files as $file) {
        if (is_file($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
};
