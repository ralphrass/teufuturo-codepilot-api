<?php
namespace resources\pessoas;

use \util\Database;
use \util\Mailer;
use \util\Permissions;
use \util\RestReturn;
use \util\Util;

/** Manutenção de pessoas (clientes, fornecedores, fabricantes e usuários). */
class Pessoas {
    /**
     * Garante que o email seja único.
     * Só chamar esta função se o usuário for considerado um usuário.
     * @param string $email
     * @param int    [$pessoa]
     * @return bool - Retornará false se o email já estiver em uso para uma pessoa considerada usuário
     */
    private static function checkEmailUnico($email, $pessoa = null) {
        $query = "
            SELECT COUNT(p.*) AS count
            FROM pessoas p
                JOIN usuarios u ON u.id = p.id
            WHERE p.id != COALESCE(:id, 0) -- Se for uma edição testa outras pessoas
                AND p.email = :email";
        $result = Database::select($query, [
            'id' => $pessoa,
            'email' => $email
        ]);
        if ($result['data'][0]['count'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * Ativa/desativa uma pessoa.
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

        // Ativa/desativa a pessoa
        $query = "
            UPDATE pessoas
            SET ativa = NOT ativa
            WHERE id = :id";
        $result = Database::execute($query, [
            'id' => $id
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }
        return RestReturn::generic();
    }

    /**
     * Obtém pessoas, com paginação.
     * @param array [$filters]
     *     int    [0] - Id clean url
     *     string [filter] - Filtro geral
     *     bool   [clientes] - Informe true para retornar apenas clientes
     *     bool   [fabricantes] - Informe true para retornar apenas fabricantes
     *     int    [limit=25] - Paginação: limite de itens para buscar a partir do offset
     *     int    [offset=0] - Paginação: a partir de qual item buscar
     *     string [props] - 'min' para retornar somente id e nome
     */
    public static function get($filters = []) {
        if (!Permissions::check('pessoas')) { // Alguns cadastros vão precisar listar pessoas para relacionar
            return RestReturn::generic('Sem permissão para pessoas');
        }

        // Obtém os parâmetros e valida
        $id = $filters[0] ?? null;
        $filter = urldecode($filters['filter'] ?? null);
        $clientes = $filters['clientes'] ?? null;
        $fabricantes = $filters['fabricantes'] ?? null;
        $limit = $filters['limit'] ?? 25;
        $offset = $filters['offset'] ?? 0;
        $props = $filters['props'] ?? null;

        // Consulta as pessoas
        if ($props == 'min') {
            $query = '
                SELECT id, nome
                FROM pessoas
                WHERE ativa = true
                    AND id = COALESCE(:id, id)
                    AND (
                        id::varchar = :filter
                        OR str_normalize(nome) LIKE str_normalize(:filterLike)
                    )
                    ' . (($clientes) ? ' AND cliente = true' : '') . '
                    ' . (($fabricantes) ? ' AND fabricante = true' : '') . '
                ORDER BY nome
                LIMIT :limit OFFSET :offset';
        } else {
            $query = '
                SELECT p.id, p.nome, p.email, p.razao_social, p.tipo, p.fornecedor, p.cliente, p.fabricante, p.observacao,
                    p.ativa, p.categoria, c.nome AS categoria_nome, CASE WHEN f.pessoa IS NOT NULL THEN true ELSE false END AS funcionario,
                    f.nome_mae, f.nome_pai, f.data_admissao, f.data_demissao, u.login, p.pessoa, p2.nome AS pessoa_nome
                FROM pessoas p
                    LEFT JOIN pessoas_categorias c ON c.id = p.categoria
                    LEFT JOIN funcionarios f ON f.pessoa = p.id
                    LEFT JOIN usuarios u ON u.id = p.id
                    LEFT JOIN pessoas p2 ON p2.id = p.pessoa
                WHERE p.ativa = true
                    AND p.id = COALESCE(:id, p.id)
                    ' . (($filter) ? '
                    AND (
                        p.id::varchar = :filter
                        OR str_normalize(p.nome) LIKE str_normalize(:filterLike)
                        OR str_normalize(p.email) LIKE str_normalize(:filterLike)
                        OR str_normalize(p.razao_social) LIKE str_normalize(:filterLike)
                        OR str_normalize(c.nome) LIKE str_normalize(:filterLike)
                        OR str_normalize(u.login) LIKE str_normalize(:filterLike)
                        OR p.id IN (SELECT pessoa FROM pessoas_telefones WHERE telefone LIKE :filterLike)
                        OR p.id IN (SELECT pessoa FROM pessoas_documentos WHERE valor LIKE :filterLike)
                        OR str_normalize(p2.nome) LIKE str_normalize(:filterLike)
                    )' : '') . '
                    ' . (($clientes) ? ' AND cliente = true' : '') . '
                    ' . (($fabricantes) ? ' AND fabricante = true' : '') . '
                ORDER BY p.nome
                LIMIT :limit OFFSET :offset';
        }
        $result = Database::select($query, [
            'id' => $id,
            'filterLike' => "%$filter%",
            'filter' => $filter,
            'limit' => $limit,
            'offset' => $offset
        ]);
        $pessoas = [];
        foreach ($result['data'] as $pessoa) {
            array_push($pessoas, $pessoa['id']);
        }

        if (count($pessoas) > 0 && $props != 'min' && Permissions::check('pessoas')) {
            // Consulta os documentos
            $documentos = Documentos::get([
                'pessoas' => $pessoas
            ]);
            if (isset($documentos['error'])) {
                return $documentos;
            }

            // Consulta os endereços
            $enderecos = Enderecos::get([
                'pessoas' => $pessoas
            ]);
            if (isset($enderecos['error'])) {
                return $enderecos;
            }

            // Consulta os telefones
            $telefones = Telefones::get([
                'pessoas' => $pessoas
            ]);
            if (isset($telefones['error'])) {
                return $telefones;
            }
        }

        // Organiza os dados e retorna
        $data = [];
        foreach ($result['data'] as $pessoa) {
            if ($props == 'min') {
                array_push($data, [
                    'id' => $pessoa['id'],
                    'nome' => $pessoa['nome']
                ]);
            } else {
                if (Permissions::check('pessoas')) {
                    $documentosPessoa = [];
                    if (isset($documentos['data'][$pessoa['id']])) {
                        foreach ($documentos['data'][$pessoa['id']] as $documento) {
                            $documentosPessoa[$documento['documento']] = $documento['valor'];
                        }
                    }
                }
                $values = [
                    'id' => $pessoa['id'],
                    'cliente' => $pessoa['cliente'],
                    'documentos' => $documentosPessoa ?? [],
                    'email' => $pessoa['email'],
                    'enderecos' => (isset($enderecos)) ? ($enderecos['data'][$pessoa['id']] ?? []) : [],
                    'fabricante' => $pessoa['fabricante'],
                    'fornecedor' => $pessoa['fornecedor'],
                    'login' => $pessoa['login'],
                    'nome' => $pessoa['nome'],
                    'observacao' => $pessoa['observacao'],
                    'razaoSocial' => $pessoa['razao_social'],
                    'telefones' => (isset($telefones)) ? ($telefones['data'][$pessoa['id']] ?? []) : [],
                    'tipo' => $pessoa['tipo']
                ];
                if ($pessoa['categoria']) {
                    $values['categoria'] = [
                        'id' => $pessoa['categoria'],
                        'nome' => $pessoa['categoria_nome']
                    ];
                }
                if ($pessoa['funcionario']) {
                    $values['funcionario'] = [
                        'nomeMae' => $pessoa['nome_mae'],
                        'nomePai' => $pessoa['nome_pai'],
                        'dataAdmissao' => $pessoa['data_admissao'],
                        'dataDemissao' => $pessoa['data_demissao']
                    ];
                }
                if ($pessoa['pessoa']) { // Pessoa relacionada (matriz se for PF ou empresa relacionada se for PF)
                    $values['pessoa'] = [
                        'id' => $pessoa['pessoa'],
                        'nome' => $pessoa['pessoa_nome']
                    ];
                }
                array_push($data, $values);
            }
        }
        return RestReturn::get($data);
    }

    /**
     * Insere, atualiza e remove os documentos de uma pessoa, comparando com o que tem no banco de dados.
     * @param int      $pessoa
     * @param string[] $documentos Indexado pelo nome do documento
     * @return array Retornará array vazio em caso de sucesso
     *     string error
     */
    private static function handleDocumentos($pessoa, $documentos = []) {
        // Consulta os documentos atuais da pessoa
        $atuaisResult = Documentos::get([
            'pessoa' => $pessoa
        ]);

        // Percorre os documentos recebidos para inserir novos e atualizar alterados
        foreach ($documentos as $key => $documento) {
            $docAtual = null;
            foreach ($atuaisResult['data'] as $atual) {
                if ($atual['documento'] == $key) {
                    $docAtual = $atual;
                    break;
                }
            }
            if ($docAtual) { // Edição
                $result = Documentos::put([$docAtual['id']], [
                    'documento' => $key,
                    'valor' => $documento
                ]);
            } else { // Adição
                $result = Documentos::post([$pessoa], [
                    'documento' => $key,
                    'valor' => $documento
                ]);
            }
            if (isset($result['error'])) {
                return $result;
            }
        }

        // Deleta os removidos
        foreach ($atuaisResult['data'] as $atual) {
            $removido = true;
            foreach ($documentos as $key => $documento) {
                if ($key == $atual['documento']) {
                    $removido = false;
                    break;
                }
            }
            if ($removido) {
                $deleteResult = Documentos::delete([$atual['id']]);
                if (isset($deleteResult['error'])) {
                    return $deleteResult;
                }
            }
        }

        return [];
    }

    /**
     * Insere, atualiza e remove os endereços de uma pessoa, comparando com o que tem no banco de dados.
     * @param int   $pessoa
     * @param array $enderecos
     * @return array Retornará array vazio em caso de sucesso
     *     string error
     */
    private static function handleEnderecos($pessoa, $enderecos = []) {
        // Consulta os endereços atuais
        $atuaisResult = Enderecos::get([
            'pessoa' => $pessoa
        ]);
        
        // Percorre os endereços recebidos para inserir novos e atualizar alterados
        foreach ($enderecos as $endereco) {
            if (!isset($endereco['id'])) {
                $result = Enderecos::post([$pessoa], $endereco);
            } else {
                $result = Enderecos::put([$endereco['id']], $endereco);
            }
            if (isset($result['error'])) {
                return $result;
            }
        }

        // Deleta os removidos
        foreach ($atuaisResult['data'] as $atual) {
            $removido = true;
            foreach ($enderecos as $endereco) {
                if ($endereco['id'] == $atual['id']) {
                    $removido = false;
                    break;
                }
            }
            if ($removido) {
                $deleteResult = Enderecos::delete([$atual['id']]);
                if (isset($deleteResult['error'])) {
                    return $deleteResult;
                }
            }
        }

        return [];
    }

    /**
     * Insere, atualiza ou remove o funcionário vinculado à pessoa.
     * @param int   $pessoa
     * @param array $funcionario
     * @return array Retornará array vazio em caso de sucesso
     *     string error
     */
    private static function handleFuncionario($pessoa, $funcionario) {
        // Consulta o funcionário que possa existir vinculado à pessoa
        $atualQuery = '
            SELECT pessoa FROM funcionarios
            WHERE pessoa = :pessoa';
        $atualResult = Database::select($atualQuery, [
            'pessoa' => $pessoa
        ]);

        // Insere o funcionário
        if (!isset($atualResult['data'][0]) && $funcionario) {
            $insertQuery = '
                INSERT INTO funcionarios (pessoa, nome_mae, nome_pai, data_admissao, data_demissao)
                VALUES (:pessoa, :nomeMae, :nomePai, :dataAdmissao, :dataDemissao)';
            return Database::insert($insertQuery, [
                'pessoa' => $pessoa,
                'nomeMae' => $funcionario['nomeMae'] ?? null,
                'nomePai' => $funcionario['nomePai'] ?? null,
                'dataAdmissao' => $funcionario['dataAdmissao'],
                'dataDemissao' => $funcionario['dataDemissao'] ?? null
            ]);
        }

        // Atualiza o funcionário
        else if (isset($atualResult['data'][0]) && $funcionario) {
            $updateQuery = '
                UPDATE funcionarios
                SET nome_mae = :nomeMae,
                    nome_pai = :nomePai,
                    data_admissao = :dataAdmissao,
                    data_demissao = :dataDemissao
                WHERE pessoa = :pessoa';
            return Database::insert($updateQuery, [
                'pessoa' => $pessoa,
                'nomeMae' => $funcionario['nomeMae'] ?? null,
                'nomePai' => $funcionario['nomePai'] ?? null,
                'dataAdmissao' => $funcionario['dataAdmissao'],
                'dataDemissao' => $funcionario['dataDemissao'] ?? null
            ]);
        }

        // Remove o funcionário
        else if (isset($atualResult['data'][0]) && !$funcionario) {
            $removeQuery = '
                DELETE FROM funcionarios
                WHERE pessoa = :pessoa';
            return Database::execute($removeQuery, [
                'pessoa' => $pessoa
            ]);
        }

        return [];
    }

    /**
     * Insere, atualiza e remove os telefones de uma pessoa, comparando com o que tem no banco de dados.
     * @param int   $pessoa
     * @param array $telefones
     * @return array Retornará array vazio em caso de sucesso
     *     string error
     */
    private static function handleTelefones($pessoa, $telefones = []) {
        // Consulta os telefones atuais
        $atuaisResult = Telefones::get([
            'pessoa' => $pessoa
        ]);

        // Percorre os telefones recebidos para inserir novos e atualizar alterados
        foreach ($telefones as $telefone) {
            if (!isset($telefone['id'])) {
                $result = Telefones::post([$pessoa], $telefone);
            } else {
                $result = Telefones::put([$telefone['id']], $telefone);
            }
            if (isset($result['error'])) {
                return $result;
            }
        }

        // Deleta os removidos
        foreach ($atuaisResult['data'] as $atual) {
            $removido = true;
            foreach ($telefones as $telefone) {
                if ($telefone['id'] == $atual['id']) {
                    $removido = false;
                    break;
                }
            }
            if ($removido) {
                $deleteResult = Telefones::delete([$atual['id']]);
                if (isset($deleteResult['error'])) {
                    return $deleteResult;
                }
            }
        }

        return [];
    }

    /**
     * Insere, atualiza ou remove o usuário vinculado à pessoa.
     * @param int   $pessoa
     * @param array $login
     * @return array Retornará array vazio em caso de sucesso
     *     string error
     */
    private static function handleUsuario($pessoa, $login) {
        // Consulta o usuário que possa existir vinculado à pessoa
        $atualQuery = '
            SELECT p.nome, p.email, u.id AS usuario
            FROM pessoas p
                LEFT JOIN usuarios u ON u.id = p.id
            WHERE p.id = :pessoa';
        $atualResult = Database::select($atualQuery, [
            'pessoa' => $pessoa
        ]);

        // Insere o usuário
        if (!$atualResult['data'][0]['usuario'] && $login) {
            $senha = Util::generateRandom(10);
            $insertQuery = '
                INSERT INTO usuarios (id, login, senha)
                VALUES (:pessoa, :login, :senha)';
            $insertResult = Database::insert($insertQuery, [
                'pessoa' => $pessoa,
                'login' => $login,
                'senha' => password_hash($senha, PASSWORD_BCRYPT)
            ]);
            if (isset($insertResult['error'])) {
                return $insertResult;
            }

            // Envia email com a senha
            $content = '
                <div style="color: #222; font-size: 1rem; max-width: 500px; margin: 0 auto; text-align: center;">
                    <img src="' . LOGO_URL . '" style="margin-top: 1rem;">
                    <p style="color: ' . SECONDARY_COLOR . '; font-size: 1.25rem; font-weight: bold;">
                        Olá ' . $atualResult['data'][0]['nome'] . '<br>
                        Seja bem-vindo(a) à ' . MAIL_FROM . '
                    </p>
                    <p>Seu usuário para acesso ao sistema acabou de ser criado, para acessar o ambiente da Horthidro utilize os seguintes dados:</p>
                    <p style="font-weight: bold">Login: ' . $login . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Senha: ' . $senha . '</p>
                    <br>
                    <a href="' . APP_URL . '" target="_BLANK" style="cursor: pointer;">
                        <button type="button" style="color: #fff; background-color: ' . PRIMARY_COLOR . '; border: 0; padding: 6px 10px; border-radius: 4px; font-size: 1rem;">Clique para acessar</button>
                    </a>
                </div>';
            $mailResult = Mailer::send('Usuário cadastrado', $content, $atualResult['data'][0]['email'], $atualResult['data'][0]['nome']);
            if ($mailResult !== true) {
                return RestReturn::generic($mailResult);
            }

            return $insertResult;
        }

        // Atualiza o usuário
        else if ($atualResult['data'][0]['usuario'] && $login) {
            $updateQuery = '
                UPDATE usuarios
                SET login = :login
                WHERE id = :pessoa';
            return Database::execute($updateQuery, [
                'pessoa' => $pessoa,
                'login' => $login
            ]);
        }

        // Remove o usuário
        else if ($atualResult['data'][0]['usuario'] && !$login) {
            $removeQuery = '
                DELETE FROM usuarios
                WHERE id = :pessoa';
            return Database::execute($removeQuery, [
                'pessoa' => $pessoa
            ]);
        }

        return [];
    }

    /**
     * Cadastra uma pessoa.
     * @param array [$filters] Não utilizado
     * @param array $values
     *     string   nome
     *     int      [categoria]
     *     string   [email]
     *     string   [razaoSocial]
     *     int      [pessoa] - Pessoa relacionada (matriz se for PJ ou empresa relacionada se for PF)
     *     string   [tipo='F'] - F (Física) ou J (Jurídica)
     *     bool     [fornecedor=false] - Indica para aparecer nas listas de fornecedores
     *     bool     [cliente=false] - Indica para aparecer nas listas de clientes
     *     bool     [fabricante=false] - Indica para aparecer nas listas de fabricantes
     *     array    [funcionario]
     *         string [nomeMae]
     *         string [nomePai]
     *         string dataAdmissao - Formato 'YYYY-MM-DD'
     *         string [dataDemissao] - Formato 'YYYY-MM-DD'
     *     string   [observacao]
     *     string[] [documentos] - Indexado pelo nome do documento
     *     array[]  [enderecos] - Itens com:
     *         bool   [principal=false]
     *         string [codigoPostal]
     *         int    cidade
     *         string bairro
     *         string logradouro
     *         string numero
     *         string [complemento]
     *     array[]  [telefones] - Itens com:
     *         bool   [principal=false]
     *         string telefone
     *     string   [login] - Informe para consider a pessoa um usuário (senha será gerada automaticamente e será enviada por email)
     * @return array
     *     string  error
     *     integer newId
     *     string  senha - Senha gerada; Se informou login
     */
    public static function post($filters = [], $values = []) {
        if (!Permissions::check('pessoas')) {
            return RestReturn::generic('Sem permissão para pessoas');
        }

        // Recebe os parâmetros e valida
        $nome = $values['nome'] ?? null;
        if (!$nome) {
            return RestReturn::generic('Nome não informado');
        }
        $categoria = $values['categoria'] ?? null;
        $email = $values['email'] ?? null;
        $razaoSocial = $values['razaoSocial'] ?? null;
        $pessoa = $values['pessoa'] ?? null;
        $tipo = $values['tipo'] ?? 'F';
        if (!in_array($tipo, ['F', 'J'])) {
            return RestReturn::generic('Tipo inválido');
        }
        $fornecedor = $values['fornecedor'] ?? false;
        $cliente = $values['cliente'] ?? false;
        $fabricante = $values['fabricante'] ?? false;
        $funcionario = $values['funcionario'] ?? null;
        if ($funcionario) {
            if (!$funcionario['dataAdmissao']) {
                return RestReturn::generic('Data de admissao não informada');
            }
        }
        $observacao = $values['observacao'] ?? null;
        $documentos = $values['documentos'] ?? null;
        $enderecos = $values['enderecos'] ?? null;
        if ($enderecos) {
            foreach ($enderecos as $endereco) {
                if (!$endereco['cidade']) {
                    return RestReturn::generic('Cidade não informada');
                }
                if (!$endereco['bairro']) {
                    return RestReturn::generic('Bairro não informado');
                }
                if (!$endereco['logradouro']) {
                    return RestReturn::generic('Logradouro não informado');
                }
                if (!$endereco['numero']) {
                    return RestReturn::generic('Número do endereço não informado');
                }
            }
        }
        $telefones = $values['telefones'] ?? null;
        if ($telefones) {
            foreach ($telefones as $telefone) {
                if (!$telefone['telefone']) {
                    return RestReturn::generic('Telefone não informado');
                }
            }
        }
        $login = $values['login'] ?? null;
        if ($login && !$email) {
            return RestReturn::generic('Email não informado');
        }

        if ($login) {
            if (!$email) {
                return RestReturn::generic('Email não informado');
            }
            if (!self::checkEmailUnico($email)) {
                return RestReturn::generic('Email já está em uso por outro usuário');
            }
        }

        // Será executado se der algo errado depois
        $removeQuery = '
            DELETE FROM pessoas
            WHERE id = :id';

        // Insere a pessoa
        $pessoaQuery = '
            INSERT INTO pessoas (nome, categoria, email, razao_social, pessoa, tipo, fornecedor, cliente, fabricante, observacao)
            VALUES (:nome, :categoria, :email, :razaoSocial, :pessoa, :tipo, :fornecedor, :cliente, :fabricante, :observacao)
            RETURNING id';
        $pessoaResult = Database::insert($pessoaQuery, [
            'nome' => $nome,
            'categoria' => $categoria,
            'email' => $email,
            'razaoSocial' => $razaoSocial,
            'pessoa' => $pessoa,
            'tipo' => $tipo,
            'fornecedor' => ($fornecedor) ? 1 : 0,
            'cliente' => ($cliente) ? 1 : 0,
            'fabricante' => ($fabricante) ? 1 : 0,
            'observacao' => $observacao
        ]);
        if (isset($pessoaResult['error'])) {
            return RestReturn::generic($pessoaResult['error']);
        }

        // Funcionário
        if ($funcionario) {
            $funcionarioResult = self::handleFuncionario($pessoaResult['newId'], $funcionario);
            if (isset($funcionarioResult['error'])) {
                Database::execute($removeQuery, [
                    'id' => $pessoaResult['newId']
                ]);
                return RestReturn::generic($funcionarioResult['error']);
            }
        }

        // Documentos
        if ($documentos) {
            $documentosResult = self::handleDocumentos($pessoaResult['newId'], $documentos);
            if (isset($documentosResult['error'])) {
                Database::execute($removeQuery, [
                    'id' => $pessoaResult['newId']
                ]);
                return RestReturn::generic($documentosResult['error']);
            }
        }

        // Endereços
        if ($enderecos) {
            $enderecosResult = self::handleEnderecos($pessoaResult['newId'], $enderecos);
            if (isset($enderecosResult['error'])) {
                Database::execute($removeQuery, [
                    'id' => $pessoaResult['newId']
                ]);
                return RestReturn::generic($enderecosResult['error']);
            }
        }

        // Telefones
        if ($telefones) {
            $telefonesResult = self::handleTelefones($pessoaResult['newId'], $telefones);
            if (isset($telefonesResult['error'])) {
                Database::execute($removeQuery, [
                    'id' => $pessoaResult['newId']
                ]);
                return RestReturn::generic($telefonesResult['error']);
            }
        }

        // Usuário
        if ($login) {
            $usuarioResult = self::handleUsuario($pessoaResult['newId'], $login);
            if (isset($usuarioResult['error'])) {
                Database::execute($removeQuery, [
                    'id' => $pessoaResult['newId']
                ]);
                return RestReturn::generic($usuarioResult['error']);
            }
        }

        return RestReturn::post($pessoaResult['newId']);
    }

    /**
     * Edita uma pessoa.
     * @param array $filters
     *     int 0 - Id clean url
     * @param array $values
     *     string   nome
     *     int      [categoria]
     *     string   [email]
     *     string   [razaoSocial]
     *     int      [pessoa] - Pessoa relacionada (matriz se for PJ ou empresa relacionada se for PF)
     *     string   [tipo='F'] - F (Física) ou J (Jurídica)
     *     boolean  [fornecedor=false] - Indica para aparecer nas listas de fornecedores
     *     boolean  [cliente=false] - Indica para aparecer nas listas de clientes
     *     boolean  [fabricante=false] - Indica para aparecer nas listas de fabricantes
     *     array    [funcionario]
     *         string [nomeMae]
     *         string [nomePai]
     *         string dataAdmissao - Formato 'YYYY-MM-'DD'
     *         string [dataDemissao] - Formato 'YYYY-MM-'DD'
     *     string   [observacao]
     *     string[] [documentos] - Indexado pelo nome do documento
     *     array    [enderecos] - Itens com:
     *         boolean [principal=false]
     *         string  [codigoPostal]
     *         integer cidade
     *         string  bairro
     *         string  logradouro
     *         string  numero
     *         string  [complemento]
     *     array    [telefones] - Itens com:
     *         boolean [principal=false]
     *         string  telefone
     *     string   [login] - Informe para consider a pessoa um usuário (senha será gerada automaticamente e será enviada por email)
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
        $nome = $values['nome'] ?? null;
        if (!$nome) {
            return RestReturn::generic('Nome não informado');
        }
        $categoria = $values['categoria'] ?? null;
        $email = $values['email'] ?? null;
        $razaoSocial = $values['razaoSocial'] ?? null;
        $pessoa = $values['pessoa'] ?? null;
        $tipo = $values['tipo'] ?? 'F';
        if (!in_array($tipo, ['F', 'J'])) {
            return RestReturn::generic('Tipo inválido');
        }
        $fornecedor = $values['fornecedor'] ?? null;
        $cliente = $values['cliente'] ?? null;
        $fabricante = $values['fabricante'] ?? null;
        $funcionario = $values['funcionario'] ?? null;
        if ($funcionario) {
            if (!$funcionario['dataAdmissao']) {
                return RestReturn::generic('Data de admissao não informada');
            }
        }
        $observacao = $values['observacao'] ?? null;
        $documentos = $values['documentos'] ?? [];
        $enderecos = $values['enderecos'] ?? [];
        foreach ($enderecos as $endereco) {
            if (!$endereco['cidade']) {
                return RestReturn::generic('Cidade não informada');
            }
            if (!$endereco['bairro']) {
                return RestReturn::generic('Bairro não informado');
            }
            if (!$endereco['logradouro']) {
                return RestReturn::generic('Logradouro não informado');
            }
            if (!$endereco['numero']) {
                return RestReturn::generic('Número do endereço não informado');
            }
        }
        $telefones = $values['telefones'] ?? [];
        foreach ($telefones as $telefone) {
            if (!$telefone['telefone']) {
                return RestReturn::generic('Telefone não informado');
            }
        }
        $login = $values['login'] ?? null;
        if ($login) {
            if (!$email) {
                return RestReturn::generic('Email não informado');
            }
            if (!self::checkEmailUnico($email, $id)) {
                return RestReturn::generic('Email já está em uso por outro usuário');
            }
        }

        // Edita a pessoa
        $query = '
            UPDATE pessoas
            SET nome = :nome,
                categoria = :categoria,
                email = :email,
                razao_social = :razaoSocial,
                pessoa = :pessoa,
                tipo = :tipo,
                fornecedor = :fornecedor,
                cliente = :cliente,
                fabricante = :fabricante,
                observacao = :observacao
            WHERE id = :id';
        $result = Database::execute($query, [
            'id' => $id,
            'nome' => $nome,
            'categoria' => $categoria,
            'email' => $email,
            'razaoSocial' => $razaoSocial,
            'pessoa' => $pessoa,
            'tipo' => $tipo,
            'fornecedor' => ($fornecedor) ? 1 : 0,
            'cliente' => ($cliente) ? 1 : 0,
            'fabricante' => ($fabricante) ? 1 : 0,
            'observacao' => $observacao
        ]);
        if (isset($result['error'])) {
            return RestReturn::generic($result['error']);
        }

        // Insere, atualiza ou remove o funcionário vinculado
        $funcionarioResult = self::handleFuncionario($id, $funcionario);
        if (isset($funcionarioResult['error'])) {
            return RestReturn::generic($funcionarioResult['error']);
        }

        // Documentos
        $documentosResult = self::handleDocumentos($id, $documentos);
        if (isset($documentosResult['error'])) {
            return RestReturn::generic($documentosResult['error']);
        }

        // Endereços
        $enderecosResult = self::handleEnderecos($id, $enderecos);
        if (isset($enderecosResult['error'])) {
            return RestReturn::generic($enderecosResult['error']);
        }

        // Telefones
        $telefonesResult = self::handleTelefones($id, $telefones);
        if (isset($telefonesResult['error'])) {
            return RestReturn::generic($telefonesResult['error']);
        }

        // Usuário
        $usuarioResult = self::handleUsuario($id, $login);
        if (isset($usuarioResult['error'])) {
            return RestReturn::generic($usuarioResult['error']);
        }

        return RestReturn::generic();
    }
}