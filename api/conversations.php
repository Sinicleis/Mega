<?php
require_once '../includes/auth.php';

// Verificar se está logado
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getCurrentUserId();

// Definir método HTTP
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
    }
} catch (Exception $e) {
    error_log("Erro na API de conversas: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
}

function handleGet() {
    global $db, $userId;
    
    if (isset($_GET['id'])) {
        // Buscar conversa específica
        $conversationId = (int)$_GET['id'];
        
        $conversation = $db->fetchOne("
            SELECT conv.*, c.name as character_name, c.profile_image as character_image, 
                   c.status as character_status, c.personality as character_personality
            FROM conversations conv
            JOIN characters c ON conv.character_id = c.id
            WHERE conv.id = ? AND conv.user_id = ?
        ", [$conversationId, $userId]);
        
        if (!$conversation) {
            jsonResponse(['success' => false, 'message' => 'Conversa não encontrada'], 404);
        }
        
        jsonResponse(['success' => true, 'conversation' => $conversation]);
    } else {
        // Listar todas as conversas do usuário
        $conversations = $db->fetchAll("
            SELECT conv.*, c.name as character_name, c.profile_image as character_image, 
                   c.status as character_status
            FROM conversations conv
            JOIN characters c ON conv.character_id = c.id
            WHERE conv.user_id = ? AND conv.is_archived = 0
            ORDER BY conv.last_message_at DESC, conv.created_at DESC
        ", [$userId]);
        
        jsonResponse(['success' => true, 'conversations' => $conversations]);
    }
}

function handlePost() {
    global $db, $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
    }
    
    switch ($input['action']) {
        case 'start':
            startConversation($input);
            break;
        case 'update':
            updateConversation($input);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Ação inválida'], 400);
    }
}

function handleDelete() {
    global $db, $userId;
    
    if (isset($_GET['id'])) {
        // Deletar conversa específica
        $conversationId = (int)$_GET['id'];
        
        // Verificar se a conversa pertence ao usuário
        $conversation = $db->fetchOne("
            SELECT id FROM conversations WHERE id = ? AND user_id = ?
        ", [$conversationId, $userId]);
        
        if (!$conversation) {
            jsonResponse(['success' => false, 'message' => 'Conversa não encontrada'], 404);
        }
        
        // Deletar mensagens da conversa
        $db->delete('messages', 'conversation_id = ?', [$conversationId]);
        
        // Deletar conversa
        $success = $db->delete('conversations', 'id = ? AND user_id = ?', [$conversationId, $userId]);
        
        if ($success) {
            jsonResponse(['success' => true, 'message' => 'Conversa excluída com sucesso']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Erro ao excluir conversa'], 500);
        }
    } else {
        // Deletar todas as conversas (via JSON)
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($input && isset($input['action']) && $input['action'] === 'clear_all') {
            // Buscar todas as conversas do usuário
            $conversations = $db->fetchAll("SELECT id FROM conversations WHERE user_id = ?", [$userId]);
            
            foreach ($conversations as $conv) {
                $db->delete('messages', 'conversation_id = ?', [$conv['id']]);
            }
            
            $success = $db->delete('conversations', 'user_id = ?', [$userId]);
            
            if ($success) {
                jsonResponse(['success' => true, 'message' => 'Todas as conversas foram excluídas']);
            } else {
                jsonResponse(['success' => false, 'message' => 'Erro ao excluir conversas'], 500);
            }
        } else {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }
    }
}

function startConversation($input) {
    global $db, $userId;
    
    if (!isset($input['character_id'])) {
        jsonResponse(['success' => false, 'message' => 'ID do personagem é obrigatório'], 400);
    }
    
    $characterId = (int)$input['character_id'];
    
    // Verificar se o personagem existe
    $character = $db->fetchOne("SELECT * FROM characters WHERE id = ? AND is_active = 1", [$characterId]);
    
    if (!$character) {
        jsonResponse(['success' => false, 'message' => 'Personagem não encontrado'], 404);
    }
    
    // Verificar se já existe uma conversa com este personagem
    $existingConversation = $db->fetchOne("
        SELECT id FROM conversations 
        WHERE user_id = ? AND character_id = ? AND is_archived = 0
    ", [$userId, $characterId]);
    
    if ($existingConversation) {
        jsonResponse(['success' => true, 'conversation_id' => $existingConversation['id']]);
        return;
    }
    
    // Criar nova conversa
    $conversationData = [
        'user_id' => $userId,
        'character_id' => $characterId,
        'title' => 'Conversa com ' . $character['name'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $conversationId = $db->insert('conversations', $conversationData);
    
    if ($conversationId) {
        // Criar mensagem de boas-vindas do personagem
        $welcomeMessage = generateWelcomeMessage($character);
        
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_type' => 'character',
            'sender_id' => $characterId,
            'message_type' => 'text',
            'content' => $welcomeMessage,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('messages', $messageData);
        
        // Atualizar conversa com a última mensagem
        $db->update('conversations', [
            'last_message' => $welcomeMessage,
            'last_message_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$conversationId]);
        
        jsonResponse(['success' => true, 'conversation_id' => $conversationId]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Erro ao criar conversa'], 500);
    }
}

function updateConversation($input) {
    global $db, $userId;
    
    if (!isset($input['conversation_id'])) {
        jsonResponse(['success' => false, 'message' => 'ID da conversa é obrigatório'], 400);
    }
    
    $conversationId = (int)$input['conversation_id'];
    
    // Verificar se a conversa pertence ao usuário
    $conversation = $db->fetchOne("
        SELECT id FROM conversations WHERE id = ? AND user_id = ?
    ", [$conversationId, $userId]);
    
    if (!$conversation) {
        jsonResponse(['success' => false, 'message' => 'Conversa não encontrada'], 404);
    }
    
    $updateData = [];
    
    if (isset($input['title'])) {
        $updateData['title'] = sanitize($input['title']);
    }
    
    if (isset($input['is_pinned'])) {
        $updateData['is_pinned'] = $input['is_pinned'] ? 1 : 0;
    }
    
    if (isset($input['is_archived'])) {
        $updateData['is_archived'] = $input['is_archived'] ? 1 : 0;
    }
    
    if (empty($updateData)) {
        jsonResponse(['success' => false, 'message' => 'Nenhum dado para atualizar'], 400);
    }
    
    $updateData['updated_at'] = date('Y-m-d H:i:s');
    
    $success = $db->update('conversations', $updateData, 'id = ?', [$conversationId]);
    
    if ($success) {
        jsonResponse(['success' => true, 'message' => 'Conversa atualizada com sucesso']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Erro ao atualizar conversa'], 500);
    }
}

function generateWelcomeMessage($character) {
    $welcomeMessages = [
        'goku' => "Oi! Eu sou o Goku! Que legal te conhecer! Quer treinar comigo ou conversar sobre aventuras?",
        'vegeta' => "Hmph! Então você quer conversar com o Príncipe dos Saiyajins? Muito bem, mas não me faça perder tempo!",
        'naruto' => "Oi! Eu sou o Naruto Uzumaki, dattebayo! Vou ser o próximo Hokage! Vamos ser amigos?",
        'sasuke' => "... O que você quer? Não tenho tempo para conversas inúteis.",
        'superman' => "Olá! Sou o Superman. Como posso ajudá-lo hoje? Estou aqui para proteger e servir.",
        'batman' => "Eu sou o Batman. Se você precisa de ajuda para combater o crime ou resolver um mistério, estou aqui.",
        'default' => "Olá! É um prazer conhecê-lo! Como posso ajudá-lo hoje?"
    ];
    
    $slug = $character['slug'] ?? 'default';
    return $welcomeMessages[$slug] ?? $welcomeMessages['default'];
}
?>

