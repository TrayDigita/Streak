# DIRECTORY & FILE STRUCTURES

Default structures.

```
--- / (Application Root)
    |---- app/ (Application Directory)
    |      |---- ACL/ (Acl Roles Directory)
    |      |      |-- Access/
    |      |      |-- Identity/
    |      |---- Controller/ (Controller / Routes Directory)
    |      |---- Middleware/ (Middleware Directory)
    |      |      |-- ErrorMiddlewareHandler.php (Middleware Handle Error)
    |      |      |-- SafePathMiddlewareDebugHandler.php (Middleware Handle Debug)
    |      |      |-- SchedulerRegistrationMiddleware.php (Middleware Handle Task Scheduler)
    |      |---- Model/ (Model Directory)
    |      |---- Module/ (Module Directory)
    |      |---- Scheduler/ (Task Scheduler Directory)
    |      |---- Source/
    |      |       | ..... (Core Dependencies)
    |      |
    |---- bin/ (Binary Directory)
    |      |---- console.php (Console File)
    |      |---- console (console.php Symlink)
    |      |---- cron.php (Cron File)
    |      |---- cron (cron.php Symlink)
    |      |
    |---- docs-md/ (Markdown Directory)
    |      |---- *****.md (Markdown Files)
    |      |
    |---- loader/ (Loader Directory)
    |      | ---- (File must be Init.php To Include On Load)
    |      |
    |---- migrations/ (Database Migrations Directory)
    |---- public/ (Public Document Root)
    |      | ---- index.php (Index File)
    |      |
    |---- storage/ (Storage Directory) 
    |      |
    |---- .gitignore (Gitignore File)
    |---- Example.Config.php (Example Config File)
    |---- LICENSE (License File)
    |---- Loader.php (Application Loader)
    |---- phpcs.xml (PHPCS Rules)
    |---- *****.md (Markdown Files) 
```
