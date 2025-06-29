<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use ReactInspector\Filesystem\FilesystemInstrumentation;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(FilesystemInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry ReactPHP Filesystem auto-instrumentation', E_USER_WARNING);

    return;
}

FilesystemInstrumentation::register();
