// WhatsJuju Chat - Main JavaScript
let currentConversationId = null;
let currentCharacterId = null;
let isTyping = false;
let typingTimeout = null;

document.addEventListener('DOMContentLoaded', function() {
    console.log('WhatsJuju Chat iniciado!');
    
    // Inicializar componentes
    initializeChat();
    setupEventListeners();
    loadUserPreferences();
});

// Inicializar chat
function initializeChat() {
    // Ajustar altura do textarea automaticamente
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', autoResizeTextarea);
    }
    
    // Verificar se há conversa ativa na URL
    const urlParams = new URLSearchParams(window.location.search);
    const conversationId = urlParams.get('conversation');
    const characterId = urlParams.get('character');
    
    if (conversationId) {
        loadConversation(conversationId);
    } else if (characterId) {
        startConversation(characterId);
    }
}

// Configurar event listeners
function setupEventListeners() {
    // Clique fora do menu do usuário
    document.addEventListener('click', function(e) {
        const userMenu = document.getElementById('userMenu');
        const userProfile = document.querySelector('.user-profile');
        
        if (userMenu && !userProfile.contains(e.target)) {
            userMenu.classList.remove('active');
            userProfile.classList.remove('active');
        }
    });
    
    // Redimensionamento da janela
    window.addEventListener('resize', handleWindowResize);
    
    // Atalhos de teclado
    document.addEventListener('keydown', handleKeyboardShortcuts);
}

// Alternar menu do usuário
function toggleUserMenu() {
    const userMenu = document.getElementById('userMenu');
    const userProfile = document.querySelector('.user-profile');
    
    userMenu.classList.toggle('active');
    userProfile.classList.toggle('active');
}

// Alternar sidebar (mobile)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Fechar sidebar (mobile)
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
}

// Buscar personagens
function searchCharacters() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const characters = document.querySelectorAll('.character-item');
    
    characters.forEach(character => {
        const name = character.querySelector('.character-name').textContent.toLowerCase();
        const category = character.querySelector('.character-category').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || category.includes(searchTerm)) {
            character.style.display = 'flex';
        } else {
            character.style.display = 'none';
        }
    });
}

// Filtrar por categoria
function filterByCategory(categorySlug) {
    // Atualizar botões ativos
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-category="${categorySlug}"]`).classList.add('active');
    
    // Filtrar personagens
    const characters = document.querySelectorAll('.character-item');
    
    characters.forEach(character => {
        if (categorySlug === 'all') {
            character.style.display = 'flex';
        } else {
            const characterCategory = character.dataset.category;
            // Buscar categoria pelo ID
            fetch(`api/categories.php?id=${characterCategory}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.category.slug === categorySlug) {
                        character.style.display = 'flex';
                    } else {
                        character.style.display = 'none';
                    }
                });
        }
    });
}

// Iniciar conversa com personagem
function startConversation(characterId) {
    showLoading();
    
    fetch('api/conversations.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'start',
            character_id: characterId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            loadConversation(data.conversation_id);
            updateConversationsList();
        } else {
            showNotification(data.message || 'Erro ao iniciar conversa', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        showNotification('Erro de conexão', 'error');
    });
}

// Carregar conversa
function loadConversation(conversationId) {
    currentConversationId = conversationId;
    showLoading();
    
    // Atualizar URL
    const url = new URL(window.location);
    url.searchParams.set('conversation', conversationId);
    window.history.replaceState({}, '', url);
    
    // Carregar dados da conversa
    fetch(`api/conversations.php?id=${conversationId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                currentCharacterId = data.conversation.character_id;
                updateChatHeader(data.conversation);
                loadMessages(conversationId);
                showChatInput();
                markConversationAsActive(conversationId);
            } else {
                showNotification(data.message || 'Erro ao carregar conversa', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Erro:', error);
            showNotification('Erro de conexão', 'error');
        });
}

// Atualizar header do chat
function updateChatHeader(conversation) {
    const chatInfo = document.getElementById('chatInfo');
    
    chatInfo.innerHTML = `
        <div class="chat-character-info">
            <img src="assets/images/characters/${conversation.character_image}" 
                 alt="${conversation.character_name}" 
                 class="chat-character-avatar"
                 onerror="this.src='assets/images/default-character.png'">
            <div class="chat-character-details">
                <h3 class="chat-character-name">${conversation.character_name}</h3>
                <span class="chat-character-status status-${conversation.character_status}">
                    ${conversation.character_status === 'online' ? 'Online' : 
                      conversation.character_status === 'busy' ? 'Ocupado' : 'Offline'}
                </span>
            </div>
        </div>
    `;
    
    // Adicionar estilos para o header do chat
    if (!document.getElementById('chatHeaderStyles')) {
        const styles = document.createElement('style');
        styles.id = 'chatHeaderStyles';
        styles.textContent = `
            .chat-character-info {
                display: flex;
                align-items: center;
                gap: var(--spacing-md);
            }
            
            .chat-character-avatar {
                width: 45px;
                height: 45px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid var(--primary-color);
            }
            
            .chat-character-details {
                display: flex;
                flex-direction: column;
            }
            
            .chat-character-name {
                font-size: 1.1rem;
                font-weight: 600;
                color: var(--text-primary);
                margin: 0;
            }
            
            .chat-character-status {
                font-size: 0.8rem;
                font-weight: 500;
            }
            
            .chat-character-status.status-online { color: var(--success-color); }
            .chat-character-status.status-busy { color: var(--warning-color); }
            .chat-character-status.status-offline { color: var(--gray); }
        `;
        document.head.appendChild(styles);
    }
}

// Carregar mensagens
function loadMessages(conversationId) {
    fetch(`api/messages.php?conversation_id=${conversationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMessages(data.messages);
            } else {
                showNotification(data.message || 'Erro ao carregar mensagens', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showNotification('Erro de conexão', 'error');
        });
}

// Exibir mensagens
function displayMessages(messages) {
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.innerHTML = '';
    
    if (messages.length === 0) {
        chatMessages.innerHTML = `
            <div class="empty-messages">
                <i class="fas fa-comment-dots"></i>
                <p>Nenhuma mensagem ainda</p>
                <small>Comece a conversa enviando uma mensagem!</small>
            </div>
        `;
        return;
    }
    
    messages.forEach(message => {
        const messageElement = createMessageElement(message);
        chatMessages.appendChild(messageElement);
    });
    
    // Scroll para a última mensagem
    scrollToBottom();
}

// Criar elemento de mensagem
function createMessageElement(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${message.sender_type === 'user' ? 'message-sent' : 'message-received'}`;
    messageDiv.dataset.messageId = message.id;
    
    const time = new Date(message.created_at).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    let content = '';
    
    if (message.message_type === 'text') {
        content = `
            <div class="message-content">
                <p>${escapeHtml(message.content)}</p>
            </div>
        `;
    } else if (message.message_type === 'image') {
        content = `
            <div class="message-content">
                <img src="${message.file_path}" alt="Imagem" class="message-image" onclick="openImageModal('${message.file_path}')">
                ${message.content ? `<p>${escapeHtml(message.content)}</p>` : ''}
            </div>
        `;
    } else if (message.message_type === 'file') {
        content = `
            <div class="message-content">
                <div class="message-file">
                    <i class="fas fa-file"></i>
                    <div class="file-info">
                        <span class="file-name">${message.file_name}</span>
                        <span class="file-size">${formatFileSize(message.file_size)}</span>
                    </div>
                    <a href="${message.file_path}" download class="file-download">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
                ${message.content ? `<p>${escapeHtml(message.content)}</p>` : ''}
            </div>
        `;
    }
    
    messageDiv.innerHTML = `
        ${content}
        <div class="message-time">${time}</div>
    `;
    
    return messageDiv;
}

// Mostrar área de input
function showChatInput() {
    const chatInputArea = document.getElementById('chatInputArea');
    chatInputArea.style.display = 'block';
}

// Marcar conversa como ativa
function markConversationAsActive(conversationId) {
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    
    const activeConversation = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (activeConversation) {
        activeConversation.classList.add('active');
    }
}

// Manipular input de mensagem
function handleInputKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function handleInputChange() {
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    
    const hasContent = messageInput.value.trim().length > 0;
    sendButton.disabled = !hasContent;
    
    // Auto-resize
    autoResizeTextarea();
}

// Auto-resize do textarea
function autoResizeTextarea() {
    const textarea = document.getElementById('messageInput');
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
}

// Enviar mensagem
function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    const content = messageInput.value.trim();
    
    if (!content || !currentConversationId) return;
    
    // Limpar input
    messageInput.value = '';
    handleInputChange();
    
    // Adicionar mensagem do usuário imediatamente
    const userMessage = {
        id: 'temp-' + Date.now(),
        sender_type: 'user',
        message_type: 'text',
        content: content,
        created_at: new Date().toISOString()
    };
    
    addMessageToChat(userMessage);
    showTypingIndicator();
    
    // Enviar para o servidor
    fetch('api/messages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'send',
            conversation_id: currentConversationId,
            content: content,
            message_type: 'text'
        })
    })
    .then(response => response.json())
    .then(data => {
        hideTypingIndicator();
        
        if (data.success) {
            // Atualizar ID da mensagem temporária
            const tempMessage = document.querySelector(`[data-message-id="temp-${userMessage.id.split('-')[1]}"]`);
            if (tempMessage) {
                tempMessage.dataset.messageId = data.message_id;
            }
            
            // Adicionar resposta do personagem
            if (data.character_response) {
                setTimeout(() => {
                    addMessageToChat(data.character_response);
                    updateConversationsList();
                }, 1000);
            }
        } else {
            showNotification(data.message || 'Erro ao enviar mensagem', 'error');
        }
    })
    .catch(error => {
        hideTypingIndicator();
        console.error('Erro:', error);
        showNotification('Erro de conexão', 'error');
    });
}

// Adicionar mensagem ao chat
function addMessageToChat(message) {
    const chatMessages = document.getElementById('chatMessages');
    
    // Remover estado vazio se existir
    const emptyState = chatMessages.querySelector('.empty-messages');
    if (emptyState) {
        emptyState.remove();
    }
    
    const messageElement = createMessageElement(message);
    chatMessages.appendChild(messageElement);
    
    // Scroll para baixo
    scrollToBottom();
}

// Mostrar indicador de digitação
function showTypingIndicator() {
    const typingIndicator = document.getElementById('typingIndicator');
    typingIndicator.style.display = 'flex';
}

// Esconder indicador de digitação
function hideTypingIndicator() {
    const typingIndicator = document.getElementById('typingIndicator');
    typingIndicator.style.display = 'none';
}

// Scroll para baixo
function scrollToBottom() {
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Upload de arquivo
function openFileUpload() {
    document.getElementById('fileInput').click();
}

function handleFileUpload(input) {
    const file = input.files[0];
    if (!file) return;
    
    uploadFile(file, 'file');
}

// Upload de imagem
function openImageUpload() {
    document.getElementById('imageInput').click();
}

function handleImageUpload(input) {
    const file = input.files[0];
    if (!file) return;
    
    uploadFile(file, 'image');
}

// Upload genérico
function uploadFile(file, type) {
    if (!currentConversationId) {
        showNotification('Selecione uma conversa primeiro', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('conversation_id', currentConversationId);
    formData.append('message_type', type);
    
    showLoading();
    
    fetch('api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            addMessageToChat(data.message);
            updateConversationsList();
            
            // Resposta do personagem para arquivos
            if (data.character_response) {
                setTimeout(() => {
                    addMessageToChat(data.character_response);
                }, 1500);
            }
        } else {
            showNotification(data.message || 'Erro ao enviar arquivo', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        showNotification('Erro de conexão', 'error');
    });
}

// Atualizar lista de conversas
function updateConversationsList() {
    fetch('api/conversations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const conversationsList = document.getElementById('conversationsList');
                // Atualizar apenas se necessário para evitar flickering
                // Implementação simplificada - recarregar página seria mais simples
                location.reload();
            }
        })
        .catch(error => {
            console.error('Erro ao atualizar conversas:', error);
        });
}

// Deletar conversa
function deleteConversation(conversationId) {
    if (!confirm('Tem certeza que deseja excluir esta conversa?')) return;
    
    fetch(`api/conversations.php?id=${conversationId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remover da lista
            const conversationElement = document.querySelector(`[data-conversation-id="${conversationId}"]`);
            if (conversationElement) {
                conversationElement.remove();
            }
            
            // Se era a conversa ativa, limpar chat
            if (currentConversationId == conversationId) {
                clearChat();
            }
            
            showNotification('Conversa excluída', 'success');
        } else {
            showNotification(data.message || 'Erro ao excluir conversa', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro de conexão', 'error');
    });
}

// Limpar todas as conversas
function clearAllConversations() {
    if (!confirm('Tem certeza que deseja excluir todas as conversas?')) return;
    
    fetch('api/conversations.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'clear_all' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showNotification(data.message || 'Erro ao limpar conversas', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro de conexão', 'error');
    });
}

// Limpar chat
function clearChat() {
    currentConversationId = null;
    currentCharacterId = null;
    
    const chatInfo = document.getElementById('chatInfo');
    chatInfo.innerHTML = `
        <div class="welcome-state">
            <div class="welcome-content">
                <div class="welcome-logo">
                    <i class="fas fa-comments"></i>
                </div>
                <h2>Bem-vindo ao WhatsJuju Chat!</h2>
                <p>Selecione um personagem na barra lateral para começar a conversar</p>
            </div>
        </div>
    `;
    
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.innerHTML = '';
    
    const chatInputArea = document.getElementById('chatInputArea');
    chatInputArea.style.display = 'none';
    
    // Limpar URL
    const url = new URL(window.location);
    url.searchParams.delete('conversation');
    url.searchParams.delete('character');
    window.history.replaceState({}, '', url);
}

// Funções de modal (serão implementadas em modals.js)
function openProfileModal() {
    console.log('Abrir modal de perfil');
}

function openSettingsModal() {
    console.log('Abrir modal de configurações');
}

function openCharacterModal() {
    console.log('Abrir modal de personagem');
}

function editCharacter(characterId) {
    console.log('Editar personagem:', characterId);
}

function openImageModal(imagePath) {
    console.log('Abrir modal de imagem:', imagePath);
}

// Funções de tema (serão implementadas em themes.js)
function toggleTheme() {
    console.log('Alternar tema');
}

// Atalhos de teclado
function handleKeyboardShortcuts(event) {
    // Ctrl/Cmd + K - Buscar
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault();
        document.getElementById('searchInput').focus();
    }
    
    // Esc - Fechar sidebar no mobile
    if (event.key === 'Escape') {
        closeSidebar();
    }
}

// Redimensionamento da janela
function handleWindowResize() {
    // Fechar sidebar no mobile se a tela ficar grande
    if (window.innerWidth > 768) {
        closeSidebar();
    }
}

// Carregar preferências do usuário
function loadUserPreferences() {
    // Implementar carregamento de preferências
    console.log('Carregando preferências do usuário');
}

// Funções utilitárias
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showLoading() {
    document.body.classList.add('loading');
}

function hideLoading() {
    document.body.classList.remove('loading');
}

function showNotification(message, type = 'info') {
    // Implementação básica - pode ser melhorada
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#2ecc71' : type === 'error' ? '#e74c3c' : '#3498db'};
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        animation: slideInRight 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Adicionar estilos para mensagens
const messageStyles = document.createElement('style');
messageStyles.textContent = `
    .message {
        margin-bottom: var(--spacing-md);
        display: flex;
        flex-direction: column;
        max-width: 70%;
        animation: fadeInUp 0.3s ease;
    }
    
    .message-sent {
        align-self: flex-end;
        align-items: flex-end;
    }
    
    .message-received {
        align-self: flex-start;
        align-items: flex-start;
    }
    
    .message-content {
        background: var(--bg-secondary);
        padding: var(--spacing-md);
        border-radius: var(--border-radius);
        box-shadow: 0 2px 5px var(--shadow);
        word-wrap: break-word;
    }
    
    .message-sent .message-content {
        background: var(--primary-color);
        color: var(--white);
    }
    
    .message-content p {
        margin: 0;
        line-height: 1.4;
    }
    
    .message-image {
        max-width: 100%;
        border-radius: var(--border-radius-small);
        cursor: pointer;
        margin-bottom: var(--spacing-sm);
    }
    
    .message-file {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-sm);
        background: var(--light-gray);
        border-radius: var(--border-radius-small);
        margin-bottom: var(--spacing-sm);
    }
    
    .message-file i {
        font-size: 1.5rem;
        color: var(--primary-color);
    }
    
    .file-info {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .file-name {
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .file-size {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .file-download {
        color: var(--primary-color);
        text-decoration: none;
        padding: var(--spacing-xs);
        border-radius: 50%;
        transition: background 0.3s ease;
    }
    
    .file-download:hover {
        background: var(--primary-color);
        color: var(--white);
    }
    
    .message-time {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: var(--spacing-xs);
        padding: 0 var(--spacing-xs);
    }
    
    .empty-messages {
        text-align: center;
        padding: var(--spacing-xl);
        color: var(--text-secondary);
    }
    
    .empty-messages i {
        font-size: 3rem;
        margin-bottom: var(--spacing-md);
        color: var(--border-color);
    }
    
    .empty-messages p {
        font-weight: 600;
        margin-bottom: var(--spacing-xs);
    }
    
    .empty-messages small {
        font-size: 0.8rem;
    }
    
    @media (max-width: 768px) {
        .message {
            max-width: 85%;
        }
    }
`;
document.head.appendChild(messageStyles);

