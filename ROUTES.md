# ROUTING & CONTROLLER


Controllers using 2 methods, standard & annotations.


## STANDARD CONTROLLER

1. **Controller** must be subclass of `TrayDigita\Streak\Source\Controller\Abstracts\AbstractController`
2. Route Method Return(Type|Value) must be `Psr\Http\Message\ResponseInterface`
3. Must be contained method `doRouting`

### Example
```php
<?php
namespace TrayDigita\Streak\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;

class ExampleAnnotationRoute extends AbstractController
{
    /**
     * Controller callback
     * 
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function doRouting(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params
    ) : ResponseInterface {
        // do anything
        return $response;
    }
}

````

## ANNOTATION CONTROLLER

1. **Annotation Controller** must be subclass of `TrayDigita\Streak\Controller\AnnotationRoute`
2. Route Method Return(Type|Value) must be `Psr\Http\Message\ResponseInterface`
3. Route Condition follow the Symfony Expression Language

### Example

```php
<?php
namespace TrayDigita\Streak\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use TrayDigita\Streak\Controller\AnnotationRoute;
// please make sure route has been imported
use TrayDigita\Streak\Source\RouteAnnotations\Annotation\Route;

/**
 * @Route("/groupPath")
 * Pattern using fast-route
 */
class ExampleAnnotationRoute extends AnnotationRoute
{
    /**
     * @Route(
     *  path="/pathtoroute[/]",
     *  methods={"GET", "POST"},
     *  condition="request.getHeaderLine('user-agent') matches '/theRegex/i'",
     *  returnType="application/json", 
     *  priority=10
     * )
     * method can be separated by `|`
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function exampleMethod(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params
    ) : ResponseInterface {
        // do anything
        return $response;
    }
}
```

### Condition expression

```php
[
    'annotation' => 'TrayDigita\Streak\Source\RouteAnnotations\Collector',
    'route'      => 'TrayDigita\Streak\Source\RouteAnnotations\Annotation\Route',
    'controller' => 'TrayDigita\Streak\Source\Controller\DynamicController',
    'request'    => 'Psr\Http\Message\ServerRequestInterface',
    'container'  => 'TrayDigita\Streak\Source\Container'
]
```

## Info

Controller Path located at : [app/Controller](app/Controller)
