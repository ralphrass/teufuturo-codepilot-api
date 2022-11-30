<?php
namespace resources\locais;

use \util\Database;
use \util\RestReturn;

class Estados {
    /**
     * Obtém estados.
     * @param array [$filters] Não utilizado
     */
    public static function get($filters = []) {
        // Consulta os estados
        $query = '
            SELECT id, nome, sigla
            FROM estados
            ORDER BY nome';
        $result = Database::select($query);

        // Organiza os dados e retorna
        $data = [];
        foreach ($result['data'] as $estado) {
            array_push($data, [
                'id' => $estado['id'],
                'nome' => $estado['nome'],
                'sigla' => $estado['sigla']
            ]);
        }
        return RestReturn::get($data);
    }
}