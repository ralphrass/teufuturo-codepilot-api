<?php
/*
Importador de estados e cidades para o banco de horas, através do serviço do IBGE.

Argumentos:
1: environment - Ambiente do banco de dados para importar (development|dev ou production|prod)
*/

require 'util/Database.php';

use \util\Database;

// Recebe e valida os parâmetros
$environment = @$argv[1];
if (!$environment) {
    echo "Informe o ambiente do banco de dados à importar\r\n";
    exit;
}

// Carrega as configurações de ambiente
if (in_array($environment, ['development', 'dev'])) {
    define('ENV', 'development');
    error_reporting(E_ERROR);
    ini_set('display_errors', 1);
} else if (in_array($environment, ['production', 'prod'])) {
    define('ENV', 'production');
} else {
    echo "Ambiente informado inválido\r\n";
    exit;
}
require ENV . '.php';

// Obtém os estados do IBGE
$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_URL, 'https://servicodados.ibge.gov.br/api/v1/localidades/estados');
$estadosNovos = 0;
$estadosAlterados = 0;
$estadosRemovidos = 0;
$cidadesNovas = 0;
$cidadesAlteradas = 0;
$cidadesRemovidas = 0;
try {
    $estadosTemp = json_decode(curl_exec($curl), true);
    $estadosTerceiro = [];
    foreach ($estadosTemp as $estadoTerceiro) {
        $estadosTerceiro[$estadoTerceiro['id']] = $estadoTerceiro;
    }
    curl_close($curl);
} catch (Exception $e) {
    echo "Erro ao obter os estados\r\n";
    print_r($e);
    exit;
}

// Consulta os estados no banco de horas
$estadosQuery = "
    SELECT id, id_terceiro, nome, sigla
    FROM estados";
$estadosResult = Database::select($estadosQuery);
$estadosBanco = [];
foreach ($estadosResult as $estadoBanco) {
    $estadosBanco[$estadoBanco['id_terceiro']] = $estadoBanco;
}

// Sincroniza estados novos e alterados
foreach ($estadosTerceiro as $estadoTerceiro) {
    $existente = @$estadosBanco[$estadoTerceiro['id']];
    if ($existente) {
        if ($existente['nome'] != $estadoTerceiro['nome'] || $existente['sigla'] != $estadoTerceiro['sigla']) {
            $updateQuery = "
                UPDATE estados
                SET nome = :nome,
                    sigla = :sigla
                WHERE id = :id";
            $updateResult = Database::execute($updateQuery, [
                'nome' => $estadoTerceiro['nome'],
                'sigla' => $estadoTerceiro['sigla'],
                'id' => $existente['id']
            ]);
            if (gettype($updateResult) == 'string') {
                echo "Erro ao alterar um estado\r\n";
                print_r($updateResult);
                exit;
            }
            $estadosAlterados++;
        }
    } else {
        $insertQuery = "
            INSERT INTO estados (id_terceiro, nome, sigla)
            VALUES (:idTerceiro, :nome, :sigla)
            RETURNING id";
        $insertResult = Database::insert($insertQuery, [
            'idTerceiro' => $estadoTerceiro['id'],
            'nome' => $estadoTerceiro['nome'],
            'sigla' => $estadoTerceiro['sigla']
        ]);
        if (gettype($insertResult) == 'string') {
            echo "Erro ao inserir um estado\r\n";
            print_r($insertResult);
            exit;
        }
        $estadosBanco[$estadoTerceiro['id']] = [
            'id' => $insertResult,
            'id_terceiro' => $estadoTerceiro['id'],
            'nome' => $estadoTerceiro['nome'],
            'sigla' => $estadoTerceiro['sigla']
        ];
        $estadosNovos++;
    }
}

// Sincroniza estados removidos
foreach ($estadosBanco as $estadoBanco) {
    if (!$estadosTerceiro[$estadoBanco['id_terceiro']]) {
        $deleteQuery = "
            DELETE FROM estados
            WHERE id = :id";
        $deleteResult = Database::execute($deleteQuery, [
            'id' => $estadoBanco['id']
        ]);
        if (gettype($deleteResult) == 'string') {
            echo "Erro ao remover um estado\r\n";
            print_r($deleteResult);
            exit;
        }
        $estadosRemovidos++;
    }
}

// Percorre os estados para sincronizar as cidades
foreach ($estadosBanco as $estadoBanco) {
    // Obtém as cidades do estado
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_URL, 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' . $estadoBanco['id_terceiro'] . '/municipios');
    try {
        $cidadesTemp = json_decode(curl_exec($curl), true);
        $cidadesTerceiro = [];
        foreach ($cidadesTemp as $cidadeTerceiro) {
            $cidadesTerceiro[$cidadeTerceiro['id']] = $cidadeTerceiro;
        }
        curl_close($curl);
    } catch (Exception $e) {
        echo "Erro ao obter as cidades\r\n";
        print_r($e);
        exit;
    }

    // Consulta as cidades do estado no banco de horas
    $cidadesQuery = "
        SELECT id, id_terceiro, nome
        FROM cidades
        WHERE estado = :estado";
    $cidadesResult = Database::select($cidadesQuery, [
        'estado' => $estadoBanco['id']
    ]);
    $cidadesBnaco = [];
    foreach ($cidadesResult as $cidadeBanco) {
        $cidadesBanco[$cidadeBanco['id_terceiro']] = $cidadeBanco;
    }

    // Sincroniza cidades novas e alteradas
    foreach ($cidadesTerceiro as $cidadeTerceiro) {
        $existente = @$cidadesBanco[$cidadeTerceiro['id']];
        if ($existente) {
            if ($existente['nome'] != $cidadeTerceiro['nome']) {
                $updateQuery = "
                    UPDATE cidades
                    SET nome = :nome
                    WHERE id = :id";
                $updateResult = Database::execute($updateQuery, [
                    'nome' => $cidadeTerceiro['nome'],
                    'id' => $existente['id']
                ]);
                if (gettype($updateResult) == 'string') {
                    echo "Erro ao alterar uma cidade\r\n";
                    print_r($updateResult);
                    exit;
                }
                $cidadesAlteradas++;
            }
        } else {
            $insertQuery = "
                INSERT INTO cidades (id_terceiro, estado, nome)
                VALUES (:idTerceiro, :estado, :nome)
                RETURNING id";
            $insertResult = Database::insert($insertQuery, [
                'idTerceiro' => $cidadeTerceiro['id'],
                'estado' => $estadoBanco['id'],
                'nome' => $cidadeTerceiro['nome']
            ]);
            if (gettype($insertResult) == 'string') {
                echo "Erro ao inserir uma cidade\r\n";
                print_r($insertResult);
                exit;
            }
            array_push($cidadesResult, [
                'id' => $insertResult,
                'id_terceiro' => $cidadeTerceiro['id'],
                'estado' => $estadoBanco['id'],
                'nome' => $cidadeTerceiro['nome']
            ]);
            $cidadesNovas++;
        }
    }

    // Sincroniza cidades removidas
    foreach ($cidadesResult as $cidadeBanco) {
        if (!$cidadesTerceiro[$cidadeBanco['id_terceiro']]) {
            $deleteQuery = "
                DELETE FROM cidades
                WHERE id = :id";
            $deleteResult = Database::execute($deleteQuery, [
                'id' => $cidadeBanco['id']
            ]);
            if (gettype($deleteResult) == 'string') {
                echo "Erro ao remover uma cidade\r\n";
                print_r($deleteResult);
                exit;
            }
            $cidadesRemovidas++;
        }
    }
}

// Relatório
if ($estadosNovos > 0) {
    echo "$estadosNovos estados inseridos\r\n";
}
if ($estadosAlterados > 0) {
    echo "$estadosAlterados estados alterados\r\n";
}
if ($estadosRemovidos > 0) {
    echo "$estadosRemovidos estados removidos\r\n";
}
if ($cidadesNovas > 0) {
    echo "$cidadesNovas cidades inseridas\r\n";
}
if ($cidadesAlteradas > 0) {
    echo "$cidadesAlteradas cidades alteradas\r\n";
}
if ($cidadesRemovidas > 0) {
    echo "$cidadesRemovidas cidades removidas\r\n";
}