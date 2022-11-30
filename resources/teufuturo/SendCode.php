<?php
namespace resources\teufuturo;

use \util\Database;
use \util\RestReturn;

class SendCode {

    /**
     * Obtém um codigo-fonte enviado por um aluno do Moodle.
     * @param array $filters
     *     int 0 - Id da pessoa clean url
     * @param array $values
     *     string pessoa - Se não informar em $filters ou clean url, informe aqui
     *     string documento
     *     string valor
     */
    public static function post($filters = [], $values = []) {
        // if (!Permissions::check('pessoas')) {
        //     return RestReturn::generic('Sem permissão para pessoas');
        // }

        //return RestReturn::post($values);

        // Recebe os parâmetros e valida
        $user_code = $filters[0] ?? $values['code'] ?? null;
        if (!$user_code) {
            return RestReturn::generic('User Code não informado');
        }
        $user_login = $values['user'] ?? null;
        if (!$user_login) {
            return RestReturn::generic('User Login não informado');
        }
        $user_fullname = $values['fullname'] ?? null;
        if (!$user_fullname) {
            return RestReturn::generic('User Fullname não informado');
        }
        $user_moodleid = $values['moodle_id'] ?? null;
        if (!$user_moodleid) {
            return RestReturn::generic('User Moodle Id não informado');
        }

        // Insere o código
        $query = '
            INSERT INTO user_code (moodle_id, moodle_user, moodle_fullname, code)
            VALUES (:user_moodleid, :user_login, :user_fullname, :user_code)
            RETURNING id';
        $result = Database::insert($query, [
            'user_moodleid' => $user_moodleid,
            'user_login' => $user_login,
            'user_fullname' => $user_fullname,
            'user_code' => $user_code
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        
        // return RestReturn::post($values);

        // 2. Analisar o código do usuário, qual comando mais se repete?
        $array = explode(" ", $user_code);
        $array = array_count_values($array);
        arsort($array);

        // 3. Buscar a lista de conteúdos de acordo com o código do usuário.

        // 4. Retornar a lista de conteúdos.

        // return RestReturn::post($result['newId']);
        return RestReturn::post($array);
    }

    /**
     * Obtém cidades de um estado ou de um código de terceiro.
     * @param array $filters
     *     int estado
     */
    public static function get($filters = []) {
        // Obtém os parâmetros e valida
        $estado = $filters['estado'] ?? null;
        $idTerceiro = $filters['idTerceiro'] ?? null;
        if (!$estado && !$idTerceiro) {
            return RestReturn::generic('Estado nem idTerceiro foram informados');
        }

        // Consulta as cidades
        if ($estado) {
            $query = '
                SELECT id, nome
                FROM cidades
                WHERE estado = :estado
                ORDER BY nome';
            $result = Database::select($query, [
                'estado' => $estado
            ]);
        } else {
            $query = '
                SELECT id, estado, nome
                FROM cidades
                WHERE id_terceiro = :idTerceiro';
            $result = Database::select($query, [
                'idTerceiro' => $idTerceiro
            ]);
        }

        // Organiza os dados e retorna
        $data = [];
        if ($estado) {
            foreach ($result['data'] as $cidade) {
                array_push($data, [
                    'id' => $cidade['id'],
                    'nome' => $cidade['nome']
                ]);
            }
        } else if ($result['data'][0]) {
            array_push($data, [
                'id' => $result['data'][0]['id'],
                'estado' => $result['data'][0]['estado'],
                'nome' => $result['data'][0]['nome']
            ]);
        }
        return RestReturn::get($data);
    }
}