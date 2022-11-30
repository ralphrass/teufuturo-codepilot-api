<?php
namespace util;

/**
 * Classe com utilidades para retornos json para o rest.
 */
class RestReturn {
    
    /**
     * Retorna a estrutura de um erro conforme os parâmetros e seta o cabeçalho para o código http informado.
     * @param string $errorMessage
     * @param int    [$httpCode=400]
     * @param array  [$details]
     * @return array errorMessage e os detalhes informados
     */
    public static function error($errorMessage, $httpCode = 400, $details = []) {
        http_response_code($httpCode);
        return array_merge([
            'error' => $errorMessage
        ], $details);
    }

    /**
     * Retorna a estrutura genérica (http 200) ou de erro (http 400).
     * @param string [$errorMessage]
     * @param array  [$details]
     * @return array errorMessage e os detalhes informados
     */
    public static function generic($errorMessage = null, $details = []) {
        if ($errorMessage) {
            return self::error($errorMessage, 400, $details);
        }
        http_response_code(200);
        return $details;
    }

    /**
     * Retorna a estrutura de um retorno de get.
     * @param array $data
     * @param array [$details]
     * @return array data e os detalhes informados
     */
    public static function get($data, $details = []) {
        http_response_code(200);
        return array_merge(['data' => $data], $details);
    }
    
    /**
     * Converte um array para um string json.
     * @param array   $data
     * @param boolean [$preventExit] O comportamento padrão é imprimir o json na tela e finalizar o script; informe true para apenas retonar o json
     * @return string
     */
    public static function jsonEncode($data, $preventExit = false) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (!$preventExit) {
            echo $json;
            exit;
        }
        return $json;
    }

    /**
     * Retorna a estrutura de um retorno de post e seta o cabeçalho para 201.
     * @param int   [$newId]
     * @param array [$details]
     * @return array data e os detalhes informados
     */
    public static function post($newId = null, $details = []) {
        http_response_code(201);
        if ($newId) {
            return array_merge(['newId' => $newId], $details);
        }
        return $details;
    }
}