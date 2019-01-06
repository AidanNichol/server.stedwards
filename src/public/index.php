<?php
date_default_timezone_set("Europe/London");
error_reporting(E_ALL);
ini_set('display_errors', 'On');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require __DIR__ . '/../vendor/autoload.php';
/* pathe to the walkdata relative to this file  */
require './config.php';
$steds_walkdata = isDev() ? "/Users/aidan/Websites/htdocsC" : "/home/ajnichol/public_html";

define('WALKDATA', str_replace('\\', '/', realpath($config['walkdata'])).'/');

$app = new \Slim\App(["settings" => $config]);
$container = $app->getContainer();

$container['view'] = new \Slim\Views\PhpRenderer("../templates/");
$container['couchdb'] = $config['couchdb']['host'];

$container['logger'] = function ($c) {
    $logger = new \Monolog\Logger('steds_server');
    $file_handler = new \Monolog\Handler\StreamHandler("../logs/app.log");
    $logger->pushHandler($file_handler);
    return $logger;
};
$container['cpglogger'] = function ($c) {
    $logger = new \Monolog\Logger('steds_server');
    $file_handler = new \Monolog\Handler\StreamHandler("../logs/cpg.log");
    $logger->pushHandler($file_handler);
    return $logger;
};

$container['db'] = function ($c) {

    $db = $c['settings']['db'];
    $pdo = new PDO("sqlite:{$db['host']}/{$db['dbname']}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};
$container['auth'] = function ($c) {
    $db = $c['settings']['auth'];
    return $db;
};

$container['cpg'] = function ($c) {
    $db = $c['settings']['cpg'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};
// Enable CORS
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});
try {

} catch (Exception $e) {

}

$app->get('/info', function (Request $request, Response $response) {
    phpinfo();
    return $response;
});

$app->get('/walks/getWalkRouteGpx/{dat}/{no}', function (Request $request, Response $response) {
    $dat = $request->getAttribute('dat');
    $no = $request->getAttribute('no');
    $mapper = new StedsMapper($this->db, $this->logger);
    list($data, $fname) = $mapper->getWalkRouteGpx($dat, $no);
    $response = $response->withHeader('Content-disposition', "attachment; filename={$fname}.gpx")->withHeader('Content-type', 'application/gpx');
    $response->getBody()->write($data);

    $this->logger->addInfo("Get getWalkRouteGpx", [$fname]);
    return $response;
});

$app->get('/walkdata[/{params:.*}]', function (Request $request, Response $response) {
    $mapper = new StedsMapper($this->db, $this->logger);
    $params = $request->getAttribute('params');
    $this->logger->addInfo("walkdata method " . $params);

    $response = $mapper->walkdata($params, $response);
    return $response;
});

$app->get('/cpg/{method}[/{params:.*}]', function (Request $request, Response $response, $args) {
    $this->cpglogger->addInfo("cpg method", $args);
    $params = explode('/', $request->getAttribute('params'));
    $this->cpglogger->addInfo("cpg params", $params);
    $mapper = new CpgMapper($this->cpg, $this->cpglogger);
    if (!method_exists($mapper, $args['method'])) {
        return $response->withStatus(404);
    }

    $data = call_user_func_array([$mapper, $args['method']], $params);
    return $response->withJson($data);
});

$app->post('/cpg/{method}[/{params:.*}]', function (Request $request, Response $response, $args) {
    $this->cpglogger->info("cpg post - ", $args);
    $mapper = new CpgMapper($this->cpg, $this->cpglogger);
    if (!method_exists($mapper, $args['method'])) {
        return $response->withStatus(404);
    }

    $res = call_user_func_array([$mapper, $args['method']], [$request, $response]);
    $this->cpglogger->info('return', $res);
    return $res;
});

$app->get('/walks/{method}[/{params:.*}]', function (Request $request, Response $response, $args) {
    $this->logger->addInfo("walks method", $args);
    $params = explode('/', $request->getAttribute('params'));
    $this->logger->addInfo("walk params", $params);
    $mapper = new StedsMapper($this->db, $this->logger);
    if (!method_exists($mapper, $args['method'])) {
        return $response->withStatus(404);
    }

    $data = call_user_func_array([$mapper, $args['method']], $params);
    return $response->withJson($data);
});
$app->post('/walks/{method}[/{params:.*}]', function (Request $request, Response $response, $args) {
    $this->logger->addInfo("walks method", $args);
    $params = explode('/', $request->getAttribute('params'));
    $this->logger->addInfo("walk params", $params);
    $mapper = new StedsMapper($this->db, $this->logger);
    if (!method_exists($mapper, $args['method'])) {
        return $response->withStatus(404);
    }

    $params[] = $request->getParsedBody();
    $data = call_user_func_array([$mapper, $args['method']], $params);
    return $response->withJson($data);
});
function processAuth($request, $response, $args)
{
    $this->logger->info("auth post - ", $args);
    $mapper = new AuthMapper($this->auth, $this->logger, $this->couchdb);
    if (!method_exists($mapper, $args['method'])) {
        return $response->withStatus(404);
    }

    $data = call_user_func_array([$mapper, $args['method']], [$request, $response]);
    return $response->withJson($data);

}
$app->get('/auth/{method}[/{params:.*}]', function (Request $request, Response $response, $args) {
    // return processAuth($request, $response, $args);
    $this->logger->addInfo("auth method", $args);
    $this->logger->addInfo("auth couchdb", $this->couchdb);
    $params = explode('/', $request->getAttribute('params'));
    $this->logger->addInfo("auth params", $params);
    $mapper = new AuthMapper($this->auth, $this->logger, $this->couchdb);
    if (!method_exists($mapper, $args['method'])) {
        return $response->withStatus(404);
    }

    return call_user_func_array([$mapper, $args['method']], [$request, $response, $params]);
});

$app->post('/auth/{method}[/{params:.*}]', function (Request $request, Response $response, $args) {
    return processAuth($request, $response, $args);
});

$app->run();
