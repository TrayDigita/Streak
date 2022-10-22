<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Public;

use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Events;

(function () : Application {
    $application = require __DIR__ . '/../Loader.php';
    $application->getContainer(Events::class)->dispatch('Serve:index');
    return $application;
})()->run();
