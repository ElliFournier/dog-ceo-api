<?php

// Turn off all error reporting
// See php console or error log to fix issues
@ini_set('display_errors', 0);

require_once realpath(__DIR__.'/vendor/autoload.php');

use config\RoutesMaker;
use config\UrlMatcher;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

try {
    $dotenv = new Dotenv(__DIR__);
    $dotenv->load();
} catch (InvalidPathException $e) {
    echo 'The .env file does not exist.';
}

$request = Request::createFromGlobals();

$routesMaker = new RoutesMaker($request);

$routesMaker->addRoute('/breeds/list', 'breedList', ['type' => 'breedOneDimensional']);
$routesMaker->addRoute('/breeds/list/all', 'breedListAll', ['type' => 'breedTwoDimensional']);
$routesMaker->addRoute('/breed/{breed}/list', 'breedListSub', ['breed' => null, 'type' => 'breedOneDimensional']);
$routesMaker->addRoute('/breeds/image/random', 'breedAllRandomImage', ['alt' => false, 'type' => 'imageSingle']);
$routesMaker->addRoute('/breeds/image/random/{amount}', 'breedAllRandomImages', ['alt' => false, 'type' => 'imageMulti']);
$routesMaker->addRoute('/breed/{breed}/images', 'breedImage', ['breed'  => null, 'breed2' => null, 'all'  => true, 'alt' => false, 'type' => 'imageMulti']);
$routesMaker->addRoute('/breed/{breed}/images/random', 'breedImage', ['breed'  => null, 'breed2' => null, 'alt' => false, 'type' => 'imageSingle']);
$routesMaker->addRoute('/breed/{breed}/images/random/{amount}', 'breedImage', ['breed'  => null, 'breed2' => null, 'alt' => false, 'type' => 'imageMulti']);
$routesMaker->addRoute('/breed/{breed}/{breed2}/images', 'breedImage', ['breed'  => null, 'breed2' => null, 'all' => true, 'alt' => false, 'type' => 'imageMulti']);
$routesMaker->addRoute('/breed/{breed}/{breed2}/images/random', 'breedImage', ['breed'  => null, 'breed2' => null, 'alt' => false, 'type' => 'imageSingle']);
$routesMaker->addRoute('/breed/{breed}/{breed2}/images/random/{amount}', 'breedImage', ['breed'  => null, 'breed2' => null, 'alt' => false, 'type' => 'imageMulti']);
$routesMaker->addRoute('/breed/{breed}', 'breedText', ['breed'  => null, 'breed2' => null, 'type' => 'breedInfo']);
$routesMaker->addRoute('/breed/{breed}/{breed2}', 'breedText', ['breed'  => null, 'breed2' => null, 'type' => 'breedInfo']);

$routesMaker->generateRoutesFromArray()->clearCacheRoute();

$routes = $routesMaker->getRoutes();

$context = new RequestContext();
$context->fromRequest($request);
$matcher = new UrlMatcher($routes, $context);

$controllerResolver = new config\ControllerResolver($routesMaker);
$argumentResolver = new HttpKernel\Controller\ArgumentResolver();

try {
    $request->attributes->add($matcher->match($request->getPathInfo()));

    $controller = $controllerResolver->getController($request);

    $match = (object) $matcher->runMatchCollection($request->getPathInfo());

    $controller[0]->setXml(((isset($match->xml) && $match->xml === true)) or (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/xml'));
    $controller[0]->setAlt((isset($match->alt) && $match->alt === true));
    $controller[0]->setType($match->type);

    $arguments = $argumentResolver->getArguments($request, $controller);

    $response = call_user_func_array($controller, $arguments);
} catch (ResourceNotFoundException $e) {
    if ($request->getPathInfo() == '/') {
        header('Location: https://dog.ceo/dog-api');
        die;
    } else {
        $response = new Response('404 Error, page not found. API documentation is located at https://dog.ceo/dog-api', 404);
    }
} catch (Exception $e) {
    $error = 'Error occurred';

    // don't reveal error message unless debug is enabled
    if (getenv('DOG_CEO_DEBUG') && getenv('DOG_CEO_DEBUG') == 'true') {
        $error = $e->getMessage();
    }

    $response = new Response($error, 500);
}

$response->send();
