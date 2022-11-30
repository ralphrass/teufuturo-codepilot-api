<?php
namespace resources\controleAcesso;

use \util\Database;
use \util\RestReturn;
use \util\Permissions;
use \util\Session;

/** Login, e troca e recuperação de senha */
class Autenticacao {
    private static $devs = [1]; // Ids dos devs (sem controle de permissões)

    /** Verifica se o usuário está autenticado */
    public static function checkAuthenticated() {
        // Verifica se tem o basic de autenticação no cabeçalho
        $auth = self::getAuthBasic();
        if (!$auth) {
            RestReturn::jsonEncode(RestReturn::error('Não autenticado', 401));
        }

        // Verifica se tem sessão válida e a senha confere
        $id = Session::get('id');
        if ($id) {
            if (!password_verify($auth['senha'], Session::get('senha'))) {
                RestReturn::jsonEncode(RestReturn::error('Não autenticado', 401));
            }
        } else { // Sessão pode ter expirado, loga novamente
            $get = self::get();
            if (isset($get['error'])) {
                RestReturn::jsonEncode($get);
            }
        }
    }

    /**
     * Limpa a sessão, deslogando o usuário.
     * @param array [$filters]
     */
    public static function delete($filters = []) {
        Session::destroy();
        return RestReturn::generic();
    }
    
    /**
     * Valida login (que também pode ser email) e senha (contidos no cabeçalho Authorization), e obtém uma sessão para o usuário.
     * @param array [$filters]
     * @return array
     *     string   [error]
     *     array    [data]
     *         string   nome
     *         string   session - Id da sessão ganha
     *         string[] permissoes - Chaves de permissão do usuário
     */
    public static function get($filters = []) {
        // Obtem parâmetros e valida
        $auth = self::getAuthBasic();
        if (!$auth) {
            return RestReturn::generic('Autenticação basic não informada');
        }

        // Consulta o usuário
        $query = '
            SELECT u.id, u.senha, p.nome, p.ativa, p.categoria
            FROM usuarios u
                JOIN pessoas p ON p.id = u.id
            WHERE u.login = :login
                OR p.email = :login';
        $result = Database::select($query, [
            'login' => $auth['login']
        ]);
        if (!isset($result['data'][0])) {
            return RestReturn::error('Usuário não encontrado', 404);
        }
        if (!$result['data'][0]['ativa']) {
            return RestReturn::error('Usuário desativado', 401);
        }

        // Verifica a senha
        if (!password_verify($auth['senha'], $result['data'][0]['senha'])) {
            return RestReturn::error('Senha incorreta', 401);
        }

        // Guarda os dados do usuário na sessão
        Session::set('id', $result['data'][0]['id']);
        Session::set('nome', $result['data'][0]['nome']);
        Session::set('senha', $result['data'][0]['senha']);
        Session::set('categoria', $result['data'][0]['categoria']);

        // Monta o retorno
        $return = [];
        $return['nome'] = $result['data'][0]['nome'];
        $return['session'] = session_id();
        $return['permissoes'] = Permissions::load();
        return RestReturn::get($return);
    }

    /**
     * Obtém login e senha do cabeçalho 'Authorization' que deve estar no padrão Basic.
     * @return boolean|array Retorna falso se não foi informado ou array com login e passwd
     */
    private static function getAuthBasic() {
        $headers = apache_request_headers();
        $authorization = $headers['Authorization'];
        if ($authorization) {
            if (preg_match('/Basic\s+(.*)$/i', $authorization, $auth)) {
                list($login, $senha) = explode(':', base64_decode($auth[1]));
                return [
                    'login' => $login,
                    'senha' => $senha
                ];
            }
        }
        return false;
    }
}