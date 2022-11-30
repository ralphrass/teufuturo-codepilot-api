<?php
namespace resources\pessoas\categorias;

use \util\Database;
use \util\Permissions;
use \util\RestReturn;

/** Manutenção de permissões de categorias de pessoas. */
class Permissoes {
    /**
     * Remove uma permissões de uma categoria de pessoas.
     * @param array $filters
     *     int categoria
     *     int permissao
     */
    public static function delete($filters = []) {
        if (!Permissions::check('pessoas/categorias')) {
            return RestReturn::generic('Sem permissão para categorias de pessoas');
        }

        // Obtém os parâmetros e valida
        $categoria = $filters['categoria'] ?? null;
        if (!$categoria) {
            return RestReturn::generic('Categoria não informada');
        }
        $permissao = $filters['permissao'] ?? null;
        if (!$permissao) {
            return RestReturn::generic('Permissão não informada');
        }

        // Remove a permissão
        $query = '
            DELETE FROM pessoas_categorias_permissoes
            WHERE pessoa_categoria = :categoria
                AND permissao = :permissao';
        $result = Database::execute($query, [
            'categoria' => $categoria,
            'permissao' => $permissao
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::generic();
    }

    /**
     * Obtém permissões de categorias de pessoas.
     * @param array $filters
     *     int [0] - Id clean url de uma categoria de pessoa, se não informar o retorno será estruturado pelo id da categoria
     *     int [categoria] - Igual o de cima, mas parâmetro padrão
     */
    public static function get($filters = []) {
        // Obtém os parâmetros e valida
        $categoria = $filters[0] ?? $filters['categoria'] ?? null;

        if (!$categoria && !Permissions::check('pessoas/categorias')) { // Só será permitido retornar as categorias se informar uma categoria
            return RestReturn::generic('Sem permissão para categorias de pessoas');
        }

        // Consulta as permissões
        $query = "
            SELECT pcp.pessoa_categoria, pcp.permissao, p.chave
            FROM pessoas_categorias_permissoes pcp
                JOIN permissoes p ON p.id = pcp.permissao
            WHERE pcp.pessoa_categoria = COALESCE(:categoria, pcp.pessoa_categoria)";
        $result = Database::select($query, [
            'categoria' => $categoria
        ]);

        // Organiza os dados e retorna
        $data = [];
        foreach ($result['data'] as $permissao) {
            $values = [
                'permissao' => $permissao['permissao'],
                'chave' => $permissao['chave']
            ];
            if ($categoria) {
                array_push($data, $values);
            } else {
                if (!isset($data[$permissao['pessoa_categoria']])) {
                    $data[$permissao['pessoa_categoria']] = [];
                }
                array_push($data[$permissao['pessoa_categoria']], $values);
            }
        }
        return RestReturn::get($data);
    }

    /**
     * Cadastra uma permissão para uma categoria de pessoas.
     * @param array $filters
     *     int 0 - Id da categoria de pessoas clean url
     * @param array $values
     *     int categoria - Se não informar em $filters ou clean url, informe aqui
     *     int permissao
     */
    public static function post($filters = [], $values = []) {
        if (!Permissions::check('pessoas/categorias')) {
            return RestReturn::generic('Sem permissão para categorias de pessoas');
        }
        
        // Obtém os parâmetros e valida
        $categoria = $filters[0] ?? $values['categoria'] ?? null;
        if (!$categoria) {
            return RestReturn::generic('Categoria não informada');
        }
        $permissao = $values['permissao'] ?? null;
        if (!$permissao) {
            return RestReturn::generic('Permissão não informada');
        }

        // Insere a permissão
        $query = "
            INSERT INTO pessoas_categorias_permissoes (pessoa_categoria, permissao)
            VALUES (:categoria, :permissao)";
        $result = Database::insert($query, [
            'categoria' => $categoria,
            'permissao' => $permissao
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::post();
    }
}