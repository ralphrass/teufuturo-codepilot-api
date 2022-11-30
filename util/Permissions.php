<?php
namespace util;

/**
 * Classe com utilidades para controle permissões.
 */
class Permissions {
    public static $devs = [1]; // Ids dos devs (sem controle de permissões)

    public static function destroy() {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Carrega as permissões do usuário para a sessão e também retorna.
     * @return string[]
     */
    public static function load() {
        if (in_array(Session::get('id'), self::$devs)) {
            $permissoesQuery = 'SELECT chave FROM permissoes';
            $permissoesResult = Database::select($permissoesQuery);
        } else if (Session::get('categoria')) {
            $permissoesResult = \resources\pessoas\categorias\Permissoes::get([Session::get('categoria')]);
        }
        $permissoes = [];
        if (isset($permissoesResult)) {
            foreach ($permissoesResult['data'] as $permissao) {
                array_push($permissoes, $permissao['chave']);
            }
        }
        Session::set('permissoes', $permissoes);
        return $permissoes;
    }

    /**
     * Verifica se tem permissão para a chave informada.
     * @param string $chave
     * @return boolean
     */
    public static function check($chave) {
        if (strpos($chave, ' || ') !== false) {
            $chaves = explode(' || ', $chave);
            $check = false;
            foreach ($chaves as $chave2) {
                if (in_array($chave2, Session::get('permissoes'))) {
                    $check = true;
                    break;
                }
            }
            return $check;
        } else if (strpos($chave, ' && ') !== false) {
            $chaves = explode(' && ', $chave);
            $check = true;
            foreach ($chaves as $chave2) {
                if (!in_array($chave2, Session::get('permissoes'))) {
                    $check = false;
                    break;
                }
            }
            return $check;
        }
        return in_array($chave, Session::get('permissoes'));
    }
}