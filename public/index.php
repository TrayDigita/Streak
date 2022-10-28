<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Public;

use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;

(function () : ?Application {
    /**
     * @var Application $application
     */
    $application = require __DIR__ . '/../Loader.php';
    $vendorDir = Consolidation::vendorDirectory();
    $scriptName = isset($_SERVER['SCRIPT_FILENAME'])
        ? realpath($_SERVER['SCRIPT_FILENAME'])
        : null;
    // prevent direct access to this file if on vendor
    if ($scriptName && str_starts_with($scriptName, $vendorDir)) {
        return null;
    }
    $application->eventDispatch('Serve:index', $application);
    return $application;
})()?->run();
