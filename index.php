<?php
/*
Roteador para módulos da API.

Necessário ter o mod_rewrite do apache ativo.

Sempre que a url não der em nada (ex: api.ralph.com.br/produtos), com a ajuda do .htaccess,
o processamento cairá aqui.

À partir do que foi digitado depois da raiz (api.ralph.com.br) ('produtos' no exemplo)
carrega o classe desejada, dentro de resources. Se der em um diretório, considera a classe com
o mesmo nome do diretório (resources/produtos/Produtos ou resources/Produtos no exemplo).
*/

// Força https no ambiente de produção
/*if (!strpos($_SERVER['SERVER_NAME'], 'localhost') && ($_SERVER['HTTPS'] ?: null) !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}*/

//require 'vendor/autoload.php';

use \util\RestReturn;

// CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit;
}
header('Content-type: application/json; charset=utf-8');

// Autoload para as classes
function autoLoad($className) {
    $fileName = '';
    $namespace = '';
    $includePath = dirname(__FILE__);

    if (false !== ($lastNsPos = strripos($className, '\\'))) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
    $fullFileName = $includePath . DIRECTORY_SEPARATOR . $fileName;

    if (file_exists($fullFileName)) {
        require $fullFileName;
    } else {
        echo $fullFileName;
        RestReturn::jsonEncode(RestReturn::error('Recurso não encontrado', 404));
    }
}
spl_autoload_register('autoLoad');

// Carrega as configurações de ambiente
if (strpos($_SERVER['SERVER_NAME'], 'localhost')) {
    define('ENV', 'development');
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    define('ENV', 'production');
    error_reporting(0);
    ini_set('display_errors', 0);
}
require ENV . '.php';

// Obtém o path (o que veio depois da raiz), e define a rota
$path = $_SERVER['REDIRECT_QUERY_STRING'];
if (!$path) {
    RestReturn::jsonEncode(RestReturn::generic('Rota não informada'));
}
if ($path[strlen($path) - 1] == '/') { // Elimina uma eventual barra no final
    header('Location: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . str_replace($_SERVER['REDIRECT_URL'], substr($_SERVER['REDIRECT_URL'], 0, strlen($_SERVER['REDIRECT_URL']) - 1), $_SERVER['REQUEST_URI']));
}
$path = 'resources/' . $path;

// Obtém os parâmetros clean url
$path = explode('/', $path);
$getParams = [];
$i = 0;
while (true) {
    if (!isset($path[$i])) {
        break;
    }
    if (is_numeric($path[$i])) {
        array_push($getParams, $path[$i] + 0); // O + 0 converte para numérico
        array_splice($path, $i, 1); // Remove o valor do path
    } else {
        $i++;
    }
}

// Se for um diretório deverá carregar o arquivo com mesmo nome
if (is_dir(implode('/', $path))) {
    array_push($path, ucfirst($path[count($path) - 1]));
}

// Coloca o último elemento em UpperCamelCase e converte para namespace (\)
$path[count($path) - 1] = ucfirst($path[count($path) - 1]);
$path = '\\' . implode('\\', $path);

// Verifica se está logado
//if ($path != '\\resources\\controleAcesso\\Autenticacao') {
//    \resources\controleAcesso\Autenticacao::checkAuthenticated();
//}

// Obtém os parâmetros GET
$url = parse_url($_SERVER['REQUEST_URI']);
if (isset($url['query'])) {
    parse_str($url['query'], $getParamsAux);
    $getParams = array_merge($getParamsAux, $getParams);
}

// Chama a classe e método solicitados
$method = strtolower($_SERVER['REQUEST_METHOD']);
if (!method_exists($path, $method)) {
    RestReturn::jsonEncode(RestReturn::error('Método não disponível', 405));
}
switch ($method) {
    case 'get':
    case 'delete':
        RestReturn::jsonEncode($path::$method($getParams));
        break;
    case 'post':
    case 'put':
        $requestBody = file_get_contents('php://input'); // Obtém os parâmetros POST (body json)
        RestReturn::jsonEncode($path::$method($getParams, json_decode($requestBody, true)));
        break;
}