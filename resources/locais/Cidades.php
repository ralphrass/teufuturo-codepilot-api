<?php
namespace resources\locais;

use \util\Database;
use \util\RestReturn;

class Cidades {
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