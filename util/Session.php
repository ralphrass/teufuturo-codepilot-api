<?php
namespace util;

/**
 * Classe com utilidades para sessão.
 */
class Session {

    public static function destroy() {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Obtém o valor de uma chave da sessão.
     * @param string $key
     * @return mixed
     */
    public static function get($key) {
        self::start();
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        }
        return null;
    }

    /**
     * Define o valor de uma chave da sessão.
     * @param string $key
     * @param mixed  $value
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }

    /** Inicia uma sessão se ainda não tiver */
    private static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            $headers = apache_request_headers();
            $sessionId = $headers['Session'];
            if ($sessionId) {
                if (preg_match('/^[a-zA-Z0-9]{26}$/', $sessionId)) {
                    session_id($sessionId);
                }
            }
            session_start();
        }
    }
}