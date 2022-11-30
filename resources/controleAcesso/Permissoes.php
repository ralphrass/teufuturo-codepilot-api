<?php
namespace resources\controleAcesso;

use \util\Database;
use \util\Permissions;
use \util\RestReturn;
use \util\Session;

/** Manutenção de pontos de controle de permissão. */
class Permissoes {
    /**
     * Obtém pontos de controle de permissão de módulos.
     * @param array $filters
     *     int [0] - Id clean url
     *     int [modulo] - Id de um módulo
     */
    public static function get($filters = []) {
        if (!Permissions::check('pessoas/categorias')) {
            return RestReturn::generic('Sem permissão para pontos de controle de permissão');
        }

        // Obtém os parâmetros e valida
        $id = $filters[0] ?? null;
        $modulo = $filters['modulo'] ?? null;

        // Consulta os pontos de controle de permissão
        $query = '
            SELECT p.id, p.modulo, m.nome AS modulo_nome, p.nome, p.chave
            FROM permissoes p
                JOIN permissoes_modulos m ON m.id = p.modulo
                ' . ((!in_array(Session::get('id'), Permissions::$devs)) ? ('JOIN pessoas_categorias_permissoes pcp ON pcp.permissao = p.id AND pcp.pessoa_categoria = ' . Session::get('categoria')) : '') . '
            WHERE p.id = COALESCE(:id, p.id)
                AND p.modulo = COALESCE(:modulo, p.modulo)
            ORDER BY m.nome, p.nome';
        $result = Database::select($query, [
            'id' => $id,
            'modulo' => $modulo
        ]);

        // Organiza os dados e retorna
        $data = [];
        foreach ($result['data'] as $permissao) {
            array_push($data, [
                'id' => $permissao['id'],
                'modulo' => [
                    'id' => $permissao['modulo'],
                    'nome' => $permissao['modulo_nome']
                ],
                'nome' => $permissao['nome'],
                'chave' => $permissao['chave']
            ]);
        }
        return RestReturn::get($data);
    }
}