<?php
namespace resources\pessoas\categorias;

use \util\Database;
use \util\Permissions;
use \util\RestReturn;

class Categorias {
    /**
     * Remove uma categoria de pessoas.
     * @param array $filters
     *     int 0 - Id clean url
     */
    public static function delete($filters = []) {
        if (!Permissions::check('pessoas/categorias')) {
            return RestReturn::generic('Sem permissão para categorias de pessoas');
        }

        // Obtém os parâmetros e valida
        $id = $filters[0] ?? null;
        if (!$id) {
            return RestReturn::generic('Id não informado');
        }

        // Remove a categoria
        $query = '
            DELETE FROM pessoas_categorias
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
     * Obtém categorias de pessoas.
     * @param array [$filters]
     *     int    [0] - Id clean url
     */
    public static function get($filters = []) {
        if (!Permissions::check('pessoas || pessoas/categorias')) { // Ao cadastrar pessoas vai precisar listar as categorias
            return RestReturn::generic('Sem permissão para categorias de pessoas');
        }

        // Obtém os parâmetros e valida
        $id = $filters[0] ?? null;

        // Consulta as categorias
        $query = '
            SELECT id, nome
            FROM pessoas_categorias
            WHERE id = COALESCE(:id, id)
            ORDER BY nome';
        $result = Database::select($query, [
            'id' => $id
        ]);

        // Consulta as permissões das categorias
        if (Permissions::check('pessoas/categorias')) { // Vai listar as categorias, mas não as permissões
            $permissoes = Permissoes::get();
        }

        // Organiza os dados e retorna
        $data = [];
        foreach ($result['data'] as $categoria) {
            array_push($data, [
                'id' => $categoria['id'],
                'nome' => $categoria['nome'],
                'permissoes' => (isset($permissoes)) ? ($permissoes['data'][$categoria['id']] ?? []) : []
            ]);
        }
        return RestReturn::get($data);
    }

    /**
     * Insere e remove as permissões de uma categoria de pessoa, comparando com o que tem no banco de dados.
     * @param int   $categoria
     * @param int[] $permissoes
     * @return array Retornará array vazio em caso de sucesso
     *     string error
     */
    private static function handlePermissoes($categoria, $permissoes = []) {
        // Consulta as permissões atuais da categoria
        $atuaisResult = Permissoes::get([$categoria]);

        // Inserir novas
        foreach ($permissoes as $permissao) {
            $existe = false;
            foreach ($atuaisResult['data'] as $atual) {
                if ($atual['permissao'] == $permissao) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $result = Permissoes::post([$categoria], [
                    'permissao' => $permissao
                ]);
                if (isset($result['error'])) {
                    return $result;
                }
            }
        }

        // Deleta removidas
        foreach ($atuaisResult['data'] as $atual) {
            $removido = true;
            foreach ($permissoes as $permissao) {
                if ($permissao == $atual['permissao']) {
                    $removido = false;
                    break;
                }
            }
            if ($removido) {
                $deleteResult = Permissoes::delete([
                    'categoria' => $categoria,
                    'permissao' => $atual['permissao']
                ]);
                if (isset($deleteResult['error'])) {
                    return $deleteResult;
                }
            }
        }

        return [];
    }

    /**
     * Cadastra uma categoria de pessoas.
     * @param array [$filters] Não utilizado
     * @param array $values
     *     string nome
     *     int[]  [permissoes]
     */
    public static function post($filters = [], $values = []) {
        if (!Permissions::check('pessoas/categorias')) {
            return RestReturn::generic('Sem permissão para categorias de pessoas');
        }

        // Recebe os parãmetros e valida
        $nome = $values['nome'] ?? null;
        if (!$nome) {
            return RestReturn::generic('Nome não informado');
        }
        $permissoes = $values['permissoes'] ?? [];

        // Insere a categoria
        $query = '
            INSERT INTO pessoas_categorias (nome)
            VALUES (:nome)
            RETURNING id';
        $result = Database::insert($query, [
            'nome' => $nome
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }

        // Permissões
        if (count($permissoes) > 0) {
            $permissoesResult = self::handlePermissoes($result['newId'], $permissoes);
            if (isset($permissoesResult['error'])) {
                return RestReturn::generic($permissoesResult['error']);
            }
        }

        return RestReturn::post($result['newId']);
    }

    /**
     * Edita uma categoria de pessoas.
     * @param array $filters
     *     int 0 - Id clean url
     * @param array $values
     *     string nome
     *     int[]  [permissoes]
     */
    public static function put($filters = [], $values = []) {
        if (!Permissions::check('pessoas/categorias')) {
            return RestReturn::generic('Sem permissão para categorias de pessoas');
        }

        // Obtém os parâmetros e valida
        $id = $filters[0] ?? null;
        if (!$id) {
            return RestReturn::generic('Id não informado');
        }
        $nome = $values['nome'] ?? null;
        if (!$nome) {
            return RestReturn::generic('Nome não informado');
        }
        $permissoes = $values['permissoes'] ?? [];

        // Edita a categoria
        $query = '
            UPDATE pessoas_categorias
            SET nome = :nome
            WHERE id = :id';
        $result = Database::execute($query, [
            'id' => $id,
            'nome' => $nome
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }

        // Permissões
        $permissoesResult = self::handlePermissoes($id, $permissoes);
        if (isset($permissoesResult['error'])) {
            return RestReturn::generic($permissoesResult['error']);
        }

        return RestReturn::generic();
    }
}