<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

if (class_exists(DG\BypassFinals::class)) {
    DG\BypassFinals::enable();
}
