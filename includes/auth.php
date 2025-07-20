<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Iniciar sessão
    public function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    // Fazer login
    public function login($username, $password, $remember = false) {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?)",
            [$username, $username]
        );
        
        if ($user && password_verify($password, $user["password"])) {
            $this->startSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
            
            return true;
        }
        
        return false;
    }
    
    // Fazer logout
    public function logout() {
        $this->startSession();
        session_destroy();
        
        // Remover cookie se existir
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        return true;
    }
    
    // Verificar se está logado
    public function isLoggedIn() {
        $this->startSession();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    // Obter usuário atual
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }
    
    // Obter ID do usuário atual
    public function getCurrentUserId() {
        $this->startSession();
        return $_SESSION['user_id'] ?? null;
    }
    
    // Registrar novo usuário (sem email)
    public function register($data) {
        // Verificar se username já existe
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = ?",
            [$data['username']]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => 'Nome de usuário já existe'];
        }
        
        // Validar dados
        if (strlen($data['username']) < 3) {
            return ['success' => false, 'message' => 'Nome de usuário deve ter pelo menos 3 caracteres'];
        }
        
        if (strlen($data['password']) < 6) {
            return ['success' => false, 'message' => 'Senha deve ter pelo menos 6 caracteres'];
        }
        
        // Criar usuário (sem email)
        $userData = [
            'username' => sanitize($data['username']),
            'email' => $data['username'] . '@whatsjuju.local', // Email automático
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'full_name' => sanitize($data['username']),
            'profile_image' => 'default-avatar.png'
        ];
        
        $userId = $this->db->insert('users', $userData);
        
        if ($userId) {
            return ['success' => true, 'message' => 'Usuário criado com sucesso', 'user_id' => $userId];
        }
        
        return ['success' => false, 'message' => 'Erro ao criar usuário'];
    }
    
    // Middleware para páginas que requerem login
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            if (isAjax()) {
                jsonResponse(['success' => false, 'message' => 'Login necessário'], 401);
            } else {
                redirect('login.php');
            }
        }
    }
}

// Instância global
$auth = new Auth();
?>

