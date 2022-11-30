<?php
namespace resources\pessoas;

use \util\Database;
use \util\Permissions;
use \util\RestReturn;

/** Manutenção de telefones de pessoas. */
class Telefones {
    /**
     * Remove um telefone de uma pessoa.
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

        // Remove o telefone
        $query = '
            DELETE FROM pessoas_telefones
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
     * Obtém telefones de pessoas.
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

        // Consulta os telefones
        $query = '
            SELECT id, pessoa, principal, telefone
            FROM pessoas_telefones
            WHERE id = COALESCE(:id, id)
                AND pessoa = COALESCE(:pessoa, pessoa)'
                . (($pessoas) ? (' AND pessoa IN (' . implode(', ', $pessoas) . ')') : '')
            . ' ORDER BY pessoa, principal';
        $result = Database::select($query, [
            'id' => $id,
            'pessoa' => $pessoa
        ]);

        // Organiza os dados e retorna
        $data = [];
        foreach ($result['data'] as $telefone) {
            $values = [
                'id' => $telefone['id'],
                'principal' => $telefone['principal'],
                'telefone' => $telefone['telefone'],
            ];
            if ($pessoas) {
                if (!isset($data[$telefone['pessoa']])) {
                    $data[$telefone['pessoa']] = [];
                }
                array_push($data[$telefone['pessoa']], $values);
            } else {
                array_push($data, $values);
            }
        }
        return RestReturn::get($data);
    }

    /**
     * Cadastra um telefone para uma pessoa.
     * @param array $filters
     *     int 0 - Id da pessoa clean url
     * @param array $values
     *     int    pessoa - Se não informar em $filters ou clean url, informe aqui
     *     bool   [principal=false]
     *     string telefone
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
        $principal = $values['principal'] ?? false;
        $telefone = $values['telefone'] ?? null;
        if (!$telefone) {
            return RestReturn::generic('Telefone não informado');
        }

        // Insere o telefone
        $query = '
            INSERT INTO pessoas_telefones (pessoa, principal, telefone)
            VALUES (:pessoa, :principal, :telefone)
            RETURNING id';
        $result = Database::insert($query, [
            'pessoa' => $pessoa,
            'principal' => ($principal) ? 1 : 0,
            'telefone' => $telefone
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::post($result['newId']);
    }

    /**
     * Edita um telefone de uma pessoa.
     * @param array $filters
     *     int 0 - Id clean url
     * @param array $values
     *     bool   [principal=false]
     *     string telefone
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
        $principal = $values['principal'] ?? false;
        $telefone = $values['telefone'] ?? null;
        if (!$telefone) {
            return RestReturn::generic('Telefone não informado');
        }

        // Edita o telefone
        $query = '
            UPDATE pessoas_telefones
            SET principal = :principal,
                telefone = :telefone
            WHERE id = :id';
        $result = Database::execute($query, [
            'id' => $id,
            'principal' => ($principal) ? 1 : 0,
            'telefone' => $telefone
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::generic();
    }
}