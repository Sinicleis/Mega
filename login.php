<?php
require_once 'includes/auth.php';

// Se já estiver logado, redirecionar
if ($auth->isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

// Processar login
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;
        
        if (empty($username) || empty($password)) {
            $error = 'Por favor, preencha todos os campos';
        } else {
            if ($auth->login($username, $password, $remember)) {
                redirect('index.php');
            } else {
                $error = 'Usuário ou senha incorretos';
            }
        }
    } elseif ($_POST['action'] === 'register') {
        $data = [
            'username' => sanitize($_POST['reg_username'] ?? ''),
            'password' => $_POST['reg_password'] ?? ''
        ];
        
        if (empty($data['username']) || empty($data['password'])) {
            $error = 'Por favor, preencha todos os campos';
        } else {
            $result = $auth->register($data);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Obter configurações do site
$db = Database::getInstance();
$siteName = 'WhatsJuju Chat';
$siteLogo = 'logo.png';

// Tentar obter do banco
try {
    $siteNameSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
    if ($siteNameSetting) {
        $siteName = $siteNameSetting['setting_value'];
    }
    
    $siteLogoSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'");
    if ($siteLogoSetting) {
        $siteLogo = $siteLogoSetting['setting_value'];
    }
} catch (Exception $e) {
    // Usar valores padrão se houver erro
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - Login</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One:wght@400&family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <!-- Desenhos flutuantes infantis -->
        <div class="floating-decorations">
            <div class="floating-item star1">⭐</div>
            <div class="floating-item heart1">💖</div>
            <div class="floating-item rainbow1">🌈</div>
            <div class="floating-item cloud1">☁️</div>
            <div class="floating-item balloon1">🎈</div>
            <div class="floating-item star2">✨</div>
            <div class="floating-item flower1">🌸</div>
            <div class="floating-item sun1">☀️</div>
            <div class="floating-item butterfly1">🦋</div>
            <div class="floating-item candy1">🍭</div>
            <div class="floating-item rocket1">🚀</div>
            <div class="floating-item planet1">🪐</div>
            <div class="floating-item unicorn1">🦄</div>
            <div class="floating-item cake1">🎂</div>
            <div class="floating-item gift1">🎁</div>
        </div>
        
        <div class="login-content">
            <!-- Logo e Título -->
            <div class="logo-section">
                <div class="logo-container">
                    <div class="logo-fallback">
                        <i class="fas fa-comments"></i>
                    </div>
                </div>
                <h1 class="site-title"><?php echo htmlspecialchars($siteName); ?></h1>
                <p class="site-subtitle">🎭 Converse com seus personagens favoritos! ✨</p>
            </div>

            <!-- Formulário de Login -->
            <div class="form-container" id="loginForm">
                <div class="form-header">
                    <h2><i class="fas fa-sign-in-alt"></i> Entrar</h2>
                    <p>Entre para conversar com personagens incríveis</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-form">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" name="username" placeholder="Nome de usuário" required>
                    </div>

                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="password" placeholder="Senha" required>
                        <div class="password-toggle" onclick="togglePassword(this)">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>

                    <div class="remember-me">
                        <label class="checkbox-container">
                            <input type="checkbox" name="remember">
                            <span class="checkmark"></span>
                            <span class="checkbox-text">🌟 Lembrar de mim</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-rocket"></i>
                        Entrar na Aventura!
                    </button>
                </form>

                <div class="form-footer">
                    <p>Não tem uma conta? 
                        <a href="#" onclick="showRegisterForm()" class="link-primary">
                            Criar conta <i class="fas fa-sparkles"></i>
                        </a>
                    </p>
                </div>
            </div>

            <!-- Formulário de Registro -->
            <div class="form-container" id="registerForm" style="display: none;">
                <div class="form-header">
                    <h2><i class="fas fa-user-plus"></i> Criar Conta</h2>
                    <p>Junte-se à nossa comunidade mágica!</p>
                </div>

                <form method="POST" class="register-form">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" name="reg_username" placeholder="Nome de usuário" required>
                    </div>

                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="reg_password" placeholder="Senha (mín. 6 caracteres)" required minlength="6">
                        <div class="password-toggle" onclick="togglePassword(this)">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-magic"></i>
                        Criar Conta Mágica!
                    </button>
                </form>

                <div class="form-footer">
                    <p>Já tem uma conta? 
                        <a href="#" onclick="showLoginForm()" class="link-primary">
                            Fazer login <i class="fas fa-sign-in-alt"></i>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="js/login.js"></script>
</body>
</html>

