<?php
namespace util;

/**
 * Classe útil para conexão com o banco de dados.
 */
class Database {
    private static $instance = null;
    private $conn;

    /** Conecta ao banco de dados */
    protected function __construct() {
        $this->conn = new \PDO('pgsql:
            host=' . DB_HOST . ';
            port=' . DB_PORT . ';
            dbname=' . DB_DATABASE . ';
            user=' . DB_USER . ';
            password=' . DB_PASSWORD);
        if (!$this->conn) {
            \util\RestReturn::jsonEncode(\util\RestReturn::error('Não pôde conectar ao banco de dados!', 500));
        }
    }

    /**
     * Executa a query com os parâmetros informados.
     * @param string $query
     * @param array  [$binds]
     * @return array
     *     string error
     *     object statement - Objeto de retorno da execução do query
     */
    public static function execute($query, $binds = []) {
        $query = "$query"; // Garante estar com double quotes, para os binds funcionarem corretamente
        $db = self::getInstance();
        $prepared = $db->conn->prepare($query);
        foreach ($binds as $name => $value) {
            if (strpos($query, ":$name") !== false) {
                $varType = is_null($value) ? \PDO::PARAM_NULL :
                    (is_bool($value) ? \PDO::PARAM_BOOL :
                        (is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
                $prepared->bindValue(":$name", $value ?? null);
            }
        }
        if (!$prepared->execute()) {
            return [
                'error' => $prepared->errorInfo()[2]
            ];
        }
        return [
            'statement' => $prepared
        ];
    }

    /** Obtém um única instância da classe */
    private static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Executa o insert informado, com os parâmetros informados
     * @param string $query
     * @param array  [$binds]
     * @return array
     *     string  error
     *     object  statement - Objeto de retorno da execução do query
     *     integer newId
     */
    public static function insert($query, $binds = []) {
        $query = trim($query);
        if (strtoupper(substr($query, 0, 6)) != 'INSERT') {
            return 'Não é insert';
        }
        $result = self::execute($query, $binds);
        if (isset($result['error'])) {
            return $result;
        }
        $data = $result['statement']->fetch();
        if (isset($data[0])) {
            $result['newId'] = $data[0];
        }
        return $result;
    }

    /**
     * Executa o select informado, com os parâmetros informados.
     * @param string $query
     * @param array  [$binds]
     * @return array
     *     string error
     *     object statement
     *     array  data
     */
    public static function select($query, $binds = []) {
        $query = trim($query);
        if (strtoupper(substr($query, 0, 6)) != 'SELECT') {
            return 'Não é select';
        }
        $result = self::execute($query, $binds);
        if (isset($result['error'])) {
            return $result;
        }
        $result['data'] = $result['statement']->fetchAll(\PDO::FETCH_ASSOC) ?? [];
        return $result;
    }
}