<?php
require_once 'includes/auth.php';

// Verificar se está logado
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Obter configurações
$siteName = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site_name'")['setting_value'] ?? 'WhatsJuju Chat';
$siteLogo = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'")['setting_value'] ?? 'logo.png';

// Obter categorias e personagens
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY order_index");
$characters = $db->fetchAll("
    SELECT c.*, cat.name as category_name, cat.color as category_color 
    FROM characters c 
    JOIN categories cat ON c.category_id = cat.id 
    WHERE c.is_active = 1 
    ORDER BY cat.order_index, c.name
");

// Obter conversas do usuário
$conversations = $db->fetchAll("
    SELECT conv.*, c.name as character_name, c.profile_image as character_image, c.status as character_status
    FROM conversations conv
    JOIN characters c ON conv.character_id = c.id
    WHERE conv.user_id = ? AND conv.is_archived = 0
    ORDER BY conv.last_message_at DESC, conv.created_at DESC
", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - Chat</title>
    <link rel="stylesheet" href="css/chat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="theme-<?php echo htmlspecialchars($user['theme']); ?>">
    <div class="chat-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <!-- Header da Sidebar -->
            <div class="sidebar-header">
                <div class="user-profile" onclick="toggleUserMenu()">
                    <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                         alt="Perfil" class="user-avatar" 
                         onerror="this.src='assets/images/default-avatar.png'">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="user-status">Online</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                
                <!-- Menu do usuário -->
                <div class="user-menu" id="userMenu">
                    <a href="#" onclick="openProfileModal()">
                        <i class="fas fa-user"></i> Meu Perfil
                    </a>
                    <a href="#" onclick="openSettingsModal()">
                        <i class="fas fa-cog"></i> Configurações
                    </a>
                    <?php if ($user['is_admin']): ?>
                    <a href="admin/">
                        <i class="fas fa-shield-alt"></i> Painel Admin
                    </a>
                    <?php endif; ?>
                    <div class="menu-divider"></div>
                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>

            <!-- Busca -->
            <div class="search-section">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Buscar personagens..." id="searchInput" onkeyup="searchCharacters()">
                </div>
            </div>

            <!-- Filtros de Categoria -->
            <div class="category-filters">
                <button class="category-btn active" data-category="all" onclick="filterByCategory('all')">
                    <i class="fas fa-star"></i> Todos
                </button>
                <?php foreach ($categories as $category): ?>
                <button class="category-btn" data-category="<?php echo $category['slug']; ?>" 
                        style="--category-color: <?php echo $category['color']; ?>"
                        onclick="filterByCategory('<?php echo $category['slug']; ?>')">
                    <i class="<?php echo $category['icon']; ?>"></i> 
                    <?php echo htmlspecialchars($category['name']); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Lista de Conversas -->
            <div class="conversations-section">
                <div class="section-header">
                    <h3><i class="fas fa-comments"></i> Conversas Recentes</h3>
                    <button class="btn-icon" onclick="clearAllConversations()" title="Limpar todas">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="conversations-list" id="conversationsList">
                    <?php if (empty($conversations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <p>Nenhuma conversa ainda</p>
                        <small>Selecione um personagem para começar</small>
                    </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item" data-conversation-id="<?php echo $conv['id']; ?>" 
                             onclick="loadConversation(<?php echo $conv['id']; ?>)">
                            <img src="assets/images/characters/<?php echo htmlspecialchars($conv['character_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($conv['character_name']); ?>" 
                                 class="conversation-avatar"
                                 onerror="this.src='assets/images/default-character.png'">
                            <div class="conversation-info">
                                <div class="conversation-header">
                                    <span class="conversation-name"><?php echo htmlspecialchars($conv['character_name']); ?></span>
                                    <span class="conversation-time"><?php echo date('H:i', strtotime($conv['last_message_at'] ?? $conv['created_at'])); ?></span>
                                </div>
                                <div class="conversation-preview">
                                    <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'Nova conversa', 0, 50)); ?>
                                    <?php if (strlen($conv['last_message'] ?? '') > 50): ?>...<?php endif; ?>
                                </div>
                            </div>
                            <div class="conversation-actions">
                                <button class="btn-icon" onclick="deleteConversation(<?php echo $conv['id']; ?>); event.stopPropagation();" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lista de Personagens -->
            <div class="characters-section">
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Personagens</h3>
                    <button class="btn-icon" onclick="openCharacterModal()" title="Adicionar personagem">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="characters-list" id="charactersList">
                    <?php foreach ($characters as $character): ?>
                    <div class="character-item" data-character-id="<?php echo $character['id']; ?>" 
                         data-category="<?php echo $character['category_id']; ?>"
                         onclick="startConversation(<?php echo $character['id']; ?>)">
                        <div class="character-avatar-container">
                            <img src="assets/images/characters/<?php echo htmlspecialchars($character['profile_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($character['name']); ?>" 
                                 class="character-avatar"
                                 onerror="this.src='assets/images/default-character.png'">
                            <div class="character-status status-<?php echo $character['status']; ?>"></div>
                        </div>
                        <div class="character-info">
                            <span class="character-name"><?php echo htmlspecialchars($character['name']); ?></span>
                            <span class="character-category" style="color: <?php echo $character['category_color']; ?>">
                                <?php echo htmlspecialchars($character['category_name']); ?>
                            </span>
                        </div>
                        <div class="character-actions">
                            <button class="btn-icon" onclick="editCharacter(<?php echo $character['id']; ?>); event.stopPropagation();" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Área Principal de Chat -->
        <div class="chat-main">
            <!-- Header do Chat -->
            <div class="chat-header" id="chatHeader">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="chat-info" id="chatInfo">
                    <div class="welcome-state">
                        <div class="welcome-content">
                            <div class="welcome-logo">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h2>Bem-vindo ao <?php echo htmlspecialchars($siteName); ?>!</h2>
                            <p>Selecione um personagem na barra lateral para começar a conversar</p>
                            
                            <div class="features-grid">
                                <div class="feature-card">
                                    <i class="fas fa-robot"></i>
                                    <h4>IA Avançada</h4>
                                    <p>Conversas naturais com personalidades únicas</p>
                                </div>
                                <div class="feature-card">
                                    <i class="fas fa-images"></i>
                                    <h4>Mídia Suportada</h4>
                                    <p>Envie imagens e arquivos para os personagens</p>
                                </div>
                                <div class="feature-card">
                                    <i class="fas fa-palette"></i>
                                    <h4>Temas Personalizáveis</h4>
                                    <p>Escolha entre 5 temas diferentes</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chat-actions">
                    <button class="btn-icon" onclick="toggleTheme()" title="Alterar tema">
                        <i class="fas fa-palette"></i>
                    </button>
                    <button class="btn-icon" onclick="openSettingsModal()" title="Configurações">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>

            <!-- Área de Mensagens -->
            <div class="chat-messages" id="chatMessages">
                <!-- Mensagens serão carregadas aqui via JavaScript -->
            </div>

            <!-- Área de Input -->
            <div class="chat-input-area" id="chatInputArea" style="display: none;">
                <div class="chat-input-container">
                    <div class="input-actions">
                        <button class="btn-icon" onclick="openFileUpload()" title="Enviar arquivo">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <button class="btn-icon" onclick="openImageUpload()" title="Enviar imagem">
                            <i class="fas fa-image"></i>
                        </button>
                        <button class="btn-icon" onclick="toggleAudioRecording()" title="Gravar áudio">
                            <i class="fas fa-microphone"></i>
                        </button>
                    </div>
                    
                    <div class="message-input-container">
                        <textarea id="messageInput" placeholder="Digite sua mensagem..." 
                                  onkeydown="handleInputKeydown(event)" 
                                  oninput="handleInputChange()"></textarea>
                        <button class="btn-icon emoji-btn" onclick="toggleEmojiPicker()" title="Emojis">
                            <i class="fas fa-smile"></i>
                        </button>
                    </div>
                    
                    <button class="send-button" id="sendButton" onclick="sendMessage()" disabled>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                
                <!-- Indicador de digitação -->
                <div class="typing-indicator" id="typingIndicator" style="display: none;">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="typing-text">Escrevendo...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Overlay para mobile -->
    <div class="mobile-overlay" id="mobileOverlay" onclick="closeSidebar()"></div>

    <!-- Inputs ocultos para upload -->
    <input type="file" id="fileInput" accept="*/*" style="display: none;" onchange="handleFileUpload(this)">
    <input type="file" id="imageInput" accept="image/*" style="display: none;" onchange="handleImageUpload(this)">

    <!-- Modais serão carregados via JavaScript -->
    <div id="modalContainer"></div>

    <script src="js/chat.js"></script>
    <script src="js/modals.js"></script>
    <script src="js/themes.js"></script>
</body>
</html>

