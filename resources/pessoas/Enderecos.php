<?php
namespace resources\pessoas;

use \util\Database;
use \util\Permissions;
use \util\RestReturn;

/** Manutenção de endereços de pessoas. */
class Enderecos {
    /**
     * Remove um endereço de uma pessoa.
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

        // Remove o endereço
        $query = '
            DELETE FROM pessoas_enderecos
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
     * Obtém endereços de pessoas.
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

        // Consulta os endereços
        $query = '
            SELECT e.id, e.pessoa, e.principal, e.codigo_postal, e.cidade, e.bairro, e.logradouro, e.numero, e.complemento,
                c.nome AS cidade_nome, c.estado, est.sigla AS estado_sigla
            FROM pessoas_enderecos e
                JOIN cidades c ON c.id = e.cidade
                JOIN estados est ON est.id = c.estado
            WHERE e.id = COALESCE(:id, e.id)
                AND e.pessoa = COALESCE(:pessoa, e.pessoa)'
                . (($pessoas) ? (' AND e.pessoa IN (' . implode(', ', $pessoas) . ')') : '')
            . ' ORDER BY e.pessoa, e.principal';
        $result = Database::select($query, [
            'id' => $id,
            'pessoa' => $pessoa
        ]);

        // Organiza os dados e retorna
        $data = [];
        foreach ($result['data'] as $endereco) {
            $values = [
                'id' => $endereco['id'],
                'bairro' => $endereco['bairro'],
                'cidade' => [
                    'id' => $endereco['cidade'],
                    'nome' => $endereco['cidade_nome']
                ],
                'complemento' => $endereco['complemento'],
                'codigoPostal' => $endereco['codigo_postal'],
                'estado' => [
                    'id' => $endereco['estado'],
                    'sigla' => $endereco['estado_sigla']
                ],
                'logradouro' => $endereco['logradouro'],
                'numero' => $endereco['numero'],
                'principal' => $endereco['principal']
            ];
            if ($pessoas) {
                if (!isset($data[$endereco['pessoa']])) {
                    $data[$endereco['pessoa']] = [];
                }
                array_push($data[$endereco['pessoa']], $values);
            } else {
                array_push($data, $values);
            }
        }
        return RestReturn::get($data);
    }

    /**
     * Cadastra um endereço para uma pessoa.
     * @param array $filters
     *     int 0 - Id da pessoa clean url
     * @param array $values
     *     string pessoa - Se não informar em $filters ou clean url, informe aqui
     *     bool   [principal=false]
     *     string [codigoPostal]
     *     int    cidade
     *     string bairro
     *     string logradouro
     *     string numero
     *     string [complemento]
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
        $codigoPostal = $values['codigoPostal'] ?? null;
        $cidade = $values['cidade'] ?? null;
        if (!$cidade) {
            return RestReturn::generic('Cidade não informada');
        }
        $bairro = $values['bairro'] ?? null;
        if (!$bairro) {
            return RestReturn::generic('Bairro não informado');
        }
        $logradouro = $values['logradouro'] ?? null;
        if (!$logradouro) {
            return RestReturn::generic('Logradouro não informado');
        }
        $numero = $values['numero'] ?? null;
        if (!$numero) {
            return RestReturn::generic('Número do endereço não informado');
        }
        $complemento = $values['complemento'] ?? null;

        // Insere o endereço
        $query = '
            INSERT INTO pessoas_enderecos (pessoa, principal, codigo_postal, cidade, bairro, logradouro, numero, complemento)
            VALUES (:pessoa, :principal, :codigoPostal, :cidade, :bairro, :logradouro, :numero, :complemento)
            RETURNING id';
        $result = Database::insert($query, [
            'pessoa' => $pessoa,
            'principal' => ($principal) ? 1 : 0,
            'codigoPostal' => $codigoPostal,
            'cidade' => $cidade,
            'bairro' => $bairro,
            'logradouro' => $logradouro,
            'numero' => $numero,
            'complemento' => $complemento
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::post($result['newId']);
    }

    /**
     * Edita um endereço de uma pessoa.
     * @param array $filters
     *     int 0 - Id clean url
     * @param array $values
     *     bool   [principal=false]
     *     string [codigoPostal]
     *     int    cidade
     *     string bairro
     *     string logradouro
     *     string numero
     *     string [complemento]
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
        $codigoPostal = $values['codigoPostal'] ?? null;
        $cidade = $values['cidade'] ?? null;
        if (!$cidade) {
            return RestReturn::generic('Cidade não informada');
        }
        $bairro = $values['bairro'] ?? null;
        if (!$bairro) {
            return RestReturn::generic('Bairro não informado');
        }
        $logradouro = $values['logradouro'] ?? null;
        if (!$logradouro) {
            return RestReturn::generic('Logradouro não informado');
        }
        $numero = $values['numero'] ?? null;
        if (!$numero) {
            return RestReturn::generic('Número não informado');
        }
        $complemento = $values['complemento'] ?? null;

        // Edita o endereço
        $query = '
            UPDATE pessoas_enderecos
            SET principal = :principal,
                codigo_postal = :codigoPostal,
                cidade = :cidade,
                bairro = :bairro,
                logradouro = :logradouro,
                numero = :numero,
                complemento = :complemento
            WHERE id = :id';
        $result = Database::execute($query, [
            'id' => $id,
            'principal' => ($principal) ? 1 : 0,
            'codigoPostal' => $codigoPostal,
            'cidade' => $cidade,
            'bairro' => $bairro,
            'logradouro' => $logradouro,
            'numero' => $numero,
            'complemento' => $complemento
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::generic();
    }
}