<?php
namespace resources\pessoas;

use \util\Database;
use \util\Permissions;
use \util\RestReturn;

/** Manutenção de documentos de pessoas. */
class Documentos {
    /**
     * Remove um documento de uma pessoa.
     * @param array $filters
     *     int 0 - Id clean url
     */
    public static function delete($filters = []) {
        if (!Permissions::check('pessoas')) {
            return RestReturn::generic('Sem permissão para pessoas');
        }

        // Obtém os parâmetros e valida
        $id = $filters[0] ?? null;
        if (!$id) {
            return RestReturn::generic('Id não informado');
        }

        // Remove o documento
        $query = '
            DELETE FROM pessoas_documentos
            WHERE id = :id';
        $result = Database::execute($query, [
            'id' => $id
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::generic();
    }

    /**
     * Obtém documentos de pessoas.
     * @param array $filters - É necessário informar algum filtro
     *     int   [0] - Id clean url
     *     int   [pessoa] - Id de uma pessoa
     *     array [pessoas] - Array com id de várias pessoas; Se informar vai estruturar o retorno pelo id da pessoa
     */
    public static function get($filters = []) {
        if (!Permissions::check('pessoas')) {
            return RestReturn::generic('Sem permissão para pessoas');
        }
        
        // Obtém os parâmetros e valida
        $id = $filters[0] ?? null;
        $pessoa = $filters['pessoa'] ?? null;
        $pessoas = $filters['pessoas'] ?? null;
        if (!$id && !$pessoa && !$pessoas) {
            return RestReturn::generic('Nenhum filtro informado');
        }

        // Consulta os documentos
        $query = '
            SELECT id, pessoa, documento, valor
            FROM pessoas_documentos
            WHERE id = COALESCE(:id, id)
                AND pessoa = COALESCE(:pessoa, pessoa)'
                . (($pessoas) ? (' AND pessoa IN (' . implode(', ', $pessoas) . ')') : '')
            . ' ORDER BY pessoa, documento';
        $result = Database::select($query, [
            'id' => $id,
            'pessoa' => $pessoa
        ]);

        // Organiza os dados e retorna
        $data = [];
        foreach ($result['data'] as $documento) {
            $values = [
                'id' => $documento['id'],
                'documento' => $documento['documento'],
                'valor' => $documento['valor']
            ];
            if ($pessoas) {
                if (!isset($data[$documento['pessoa']])) {
                    $data[$documento['pessoa']] = [];
                }
                array_push($data[$documento['pessoa']], $values);
            } else {
                array_push($data, $values);
            }
        }
        return RestReturn::get($data);
    }

    /**
     * Cadastra um documento para uma pessoa.
     * @param array $filters
     *     int 0 - Id da pessoa clean url
     * @param array $values
     *     string pessoa - Se não informar em $filters ou clean url, informe aqui
     *     string documento
     *     string valor
     */
    public static function post($filters = [], $values = []) {
        if (!Permissions::check('pessoas')) {
            return RestReturn::generic('Sem permissão para pessoas');
        }

        // Recebe os parâmetros e valida
        $pessoa = $filters[0] ?? $values['pessoa'] ?? null;
        if (!$pessoa) {
            return RestReturn::generic('Pessoa não informada');
        }
        $documento = $values['documento'] ?? null;
        if (!$documento) {
            return RestReturn::generic('Documento não informado');
        }
        $valor = $values['valor'] ?? null;
        if (!$valor) {
            return RestReturn::generic('Valor não informado');
        }

        // Insere o documento
        $query = '
            INSERT INTO pessoas_documentos (pessoa, documento, valor)
            VALUES (:pessoa, :documento, :valor)
            RETURNING id';
        $result = Database::insert($query, [
            'pessoa' => $pessoa,
            'documento' => $documento,
            'valor' => $valor
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::post($result['newId']);
    }

    /**
     * Edita um documento de uma pessoa.
     * @param array $filters
     *     int 0 - Id clean url
     * @param array $values
     *     string documento
     *     string valor
     */
    public static function put($filters = [], $values = []) {
        if (!Permissions::check('pessoas')) {
            return RestReturn::generic('Sem permissão para pessoas');
        }

        // Obtém os parâmetros e valida
        $id = $filters[0] ?? null;
        if (!$id) {
            return RestReturn::generic('Id não informado');
        }
        $documento = $values['documento'] ?? null;
        if (!$documento) {
            return RestReturn::generic('Documento não informado');
        }
        $valor = $values['valor'] ?? null;
        if (!$valor) {
            return RestReturn::generic('Valor não informado');
        }

        // Edita o documento
        $query = '
            UPDATE pessoas_documentos
            SET documento = :documento,
                valor = :valor
            WHERE id = :id';
        $result = Database::execute($query, [
            'id' => $id,
            'documento' => $documento,
            'valor' => $valor
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::generic();
    }
}