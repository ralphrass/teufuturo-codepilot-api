<?php
namespace resources\controleAcesso;

use \util\Database;
use \util\Permissions;
use \util\RestReturn;

/** Manutenção de módulos organizadores/agrupadores para as permissões. */
class Modulos {
    /**
     * Obtém módulos para as permissões.
     * @param array $filters
     *     int [0] - Id clean url
     */
    public static function get($filters = []) {
        if (!Permissions::check('pessoas/categorias')) {
            return RestReturn::generic('Sem permissão para módulos');
        }

        // Obtém os parâmetros e valida
        $id = $filters[0] ?? null;

        // Consulta os módulos
        $query = '
            SELECT id, nome, icone
            FROM permissoes_modulos
            WHERE id = COALESCE(:id, id)';
        $result = Database::select($query, [
            'id' => $id
        ]);

        // Consulta os pontos de controle de permissão
        $permissoes = Permissoes::get();
        if (isset($permissoes['error'])) {
            return $permissoes;
        }

        // Organiza os dados e retorna
        $data = [];
        foreach ($result['data'] as $modulo) {
            $moduloPermissoes = [];
            foreach ($permissoes['data'] as $permissao) {
                if ($permissao['modulo']['id'] == $modulo['id']) {
                    unset($permissao['modulo']);
                    array_push($moduloPermissoes, $permissao);
                }
            }
            if (count($moduloPermissoes) > 0) {
                array_push($data, [
                    'id' => $modulo['id'],
                    'nome' => $modulo['nome'],
                    'icone' => $modulo['icone'],
                    'permissoes' => $moduloPermissoes
                ]);
            }
        }
        return RestReturn::get($data);
    }
}