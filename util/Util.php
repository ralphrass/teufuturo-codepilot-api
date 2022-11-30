<?php
namespace util;

/**
 * Utilidades gerais.
 */
class Util {
    /**
     * Gera uma string aleatória com o tamanho informado.
     * @param int $size
     * @return string
     */
    public static function generateRandom($size) {
        $alphabet = 'abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789';
        $pass = [];
        $alphaLength = strlen($alphabet) - 1;
        for ($i = 0; $i < $size; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass);
    }
}