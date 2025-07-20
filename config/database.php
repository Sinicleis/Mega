<?php
// Incluir funções auxiliares
require_once __DIR__ . '/../includes/functions.php';

// Configuração do banco de dados
class Database {
    private static $instance = null;
    private $connection;
    
    // Configurações do banco
    private $host = 'localhost';
    private $dbname = 'whatsjuju_chat';
    private $username = 'root';
    private $password = '';
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            // Tentar criar banco se não existir
            try {
                $tempConnection = new PDO(
                    "mysql:host={$this->host};charset=utf8mb4",
                    $this->username,
                    $this->password
                );
                $tempConnection->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Tentar conectar novamente
                $this->connection = new PDO(
                    "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                
                // Executar schema se banco foi criado
                $this->initializeDatabase();
                
            } catch (PDOException $e2) {
                die("Erro de conexão: " . $e2->getMessage());
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Executar query
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            logError("Database query error: " . $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            throw $e;
        }
    }
    
    // Buscar um registro
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // Buscar múltiplos registros
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Inserir registro
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            logError("Database insert error: " . $e->getMessage(), ['table' => $table, 'data' => $data]);
            throw $e;
        }
    }
    
    // Atualizar registro
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $key) {
            $setClause[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $params = array_merge($data, $whereParams);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            logError("Database update error: " . $e->getMessage(), ['table' => $table, 'data' => $data]);
            throw $e;
        }
    }
    
    // Deletar registro
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($whereParams);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            logError("Database delete error: " . $e->getMessage(), ['table' => $table, 'where' => $where]);
            throw $e;
        }
    }
    
    // Inicializar banco de dados
    private function initializeDatabase() {
        $schema = file_get_contents(__DIR__ . '/install.sql');
        if ($schema) {
            $this->connection->exec($schema);
        }
    }
    
    // Verificar se tabela existe
    public function tableExists($tableName) {
        $sql = "SHOW TABLES LIKE ?";
        $stmt = $this->query($sql, [$tableName]);
        return $stmt->rowCount() > 0;
    }
    
    // Começar transação
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    // Confirmar transação
    public function commit() {
        return $this->connection->commit();
    }
    
    // Reverter transação
    public function rollback() {
        return $this->connection->rollback();
    }
}

// Configurações da aplicação
define('SITE_URL', 'http://localhost:8000');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('ASSETS_PATH', __DIR__ . '/../assets/');

// Configurações de sessão
define('SESSION_TIMEOUT', 3600); // 1 hora

// Configurações de API
define('OPENAI_API_KEY', ''); // Será configurado pelo admin
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
?>

