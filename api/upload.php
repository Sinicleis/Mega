<?php
require_once '../includes/auth.php';

// Verificar se está logado
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getCurrentUserId();

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

try {
    handleFileUpload();
} catch (Exception $e) {
    error_log("Erro no upload: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
}

function handleFileUpload() {
    global $db, $userId;
    
    // Verificar se há arquivo
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'Nenhum arquivo enviado ou erro no upload'], 400);
    }
    
    // Verificar dados obrigatórios
    if (!isset($_POST['conversation_id']) || !isset($_POST['message_type'])) {
        jsonResponse(['success' => false, 'message' => 'Dados obrigatórios não fornecidos'], 400);
    }
    
    $conversationId = (int)$_POST['conversation_id'];
    $messageType = $_POST['message_type'];
    $caption = $_POST['caption'] ?? '';
    
    // Verificar se a conversa pertence ao usuário
    $conversation = $db->fetchOne("
        SELECT c.*, ch.name as character_name, ch.personality as character_personality
        FROM conversations c
        JOIN characters ch ON c.character_id = ch.id
        WHERE c.id = ? AND c.user_id = ?
    ", [$conversationId, $userId]);
    
    if (!$conversation) {
        jsonResponse(['success' => false, 'message' => 'Conversa não encontrada'], 404);
    }
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmpName = $file['tmp_name'];
    $fileType = $file['type'];
    
    // Validar tipo de arquivo
    $allowedTypes = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'file' => [
            'application/pdf', 'text/plain', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', 'application/x-rar-compressed'
        ]
    ];
    
    $isValidType = false;
    if ($messageType === 'image' && in_array($fileType, $allowedTypes['image'])) {
        $isValidType = true;
    } elseif ($messageType === 'file' && (in_array($fileType, $allowedTypes['file']) || in_array($fileType, $allowedTypes['image']))) {
        $isValidType = true;
    }
    
    if (!$isValidType) {
        jsonResponse(['success' => false, 'message' => 'Tipo de arquivo não permitido'], 400);
    }
    
    // Validar tamanho (10MB máximo)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($fileSize > $maxSize) {
        jsonResponse(['success' => false, 'message' => 'Arquivo muito grande. Máximo 10MB'], 400);
    }
    
    // Criar diretório de upload se não existir
    $uploadDir = '../uploads/' . ($messageType === 'image' ? 'images' : 'files') . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Gerar nome único para o arquivo
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $uniqueFileName;
    
    // Mover arquivo
    if (!move_uploaded_file($fileTmpName, $filePath)) {
        jsonResponse(['success' => false, 'message' => 'Erro ao salvar arquivo'], 500);
    }
    
    // Salvar mensagem no banco
    $messageData = [
        'conversation_id' => $conversationId,
        'sender_type' => 'user',
        'sender_id' => $userId,
        'message_type' => $messageType,
        'content' => $caption,
        'file_name' => $fileName,
        'file_path' => 'uploads/' . ($messageType === 'image' ? 'images' : 'files') . '/' . $uniqueFileName,
        'file_size' => $fileSize,
        'file_type' => $fileType,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $messageId = $db->insert('messages', $messageData);
    
    if (!$messageId) {
        // Remover arquivo se falhou ao salvar no banco
        unlink($filePath);
        jsonResponse(['success' => false, 'message' => 'Erro ao salvar mensagem'], 500);
    }
    
    // Atualizar conversa
    $lastMessage = $messageType === 'image' ? '📷 Imagem' : '📎 ' . $fileName;
    if (!empty($caption)) {
        $lastMessage .= ': ' . $caption;
    }
    
    $db->update('conversations', [
        'last_message' => $lastMessage,
        'last_message_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$conversationId]);
    
    // Preparar resposta
    $message = [
        'id' => $messageId,
        'sender_type' => 'user',
        'message_type' => $messageType,
        'content' => $caption,
        'file_name' => $fileName,
        'file_path' => $messageData['file_path'],
        'file_size' => $fileSize,
        'file_type' => $fileType,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Gerar resposta do personagem para o arquivo
    $characterResponse = generateFileResponse($messageType, $fileName, $caption, $conversation, $conversationId);
    
    $response = [
        'success' => true,
        'message' => $message,
        'upload_message' => 'Arquivo enviado com sucesso'
    ];
    
    if ($characterResponse) {
        $response['character_response'] = $characterResponse;
    }
    
    jsonResponse($response);
}

function generateFileResponse($messageType, $fileName, $caption, $conversation, $conversationId) {
    global $db;
    
    $characterName = $conversation['character_name'];
    $characterSlug = $conversation['slug'] ?? '';
    
    // Respostas específicas por tipo de arquivo
    $responses = [
        'image' => [
            'goku' => [
                "Uau! Que imagem legal! Me lembra de algumas aventuras que tive!",
                "Essa foto é incrível! Você tirou isso?",
                "Que legal! Posso ver mais fotos como essa?",
                "Interessante! Isso me dá vontade de treinar mais!"
            ],
            'vegeta' => [
                "Hmph! Não está mal... para um terráqueo.",
                "Interessante. Você tem bom gosto.",
                "Essa imagem... desperta minha curiosidade.",
                "Admito que é impressionante."
            ],
            'naruto' => [
                "Que foto incrível, dattebayo!",
                "Isso é muito legal! Me lembra da Vila da Folha!",
                "Uau! Você é um ótimo fotógrafo, dattebayo!",
                "Essa imagem me dá energia!"
            ],
            'default' => [
                "Que imagem interessante!",
                "Obrigado por compartilhar isso comigo!",
                "Essa foto é muito legal!",
                "Gostei muito dessa imagem!"
            ]
        ],
        'file' => [
            'batman' => [
                "Interessante. Vou analisar este arquivo.",
                "Adicionando aos meus arquivos para investigação.",
                "Este documento pode ser útil.",
                "Obrigado por compartilhar esta informação."
            ],
            'superman' => [
                "Obrigado por compartilhar este arquivo comigo.",
                "Vou dar uma olhada nisso. Pode ser importante.",
                "Interessante. Como posso ajudar com isso?",
                "Este documento parece relevante."
            ],
            'default' => [
                "Obrigado por enviar este arquivo!",
                "Vou dar uma olhada nisso.",
                "Interessante! Obrigado por compartilhar.",
                "Que legal! Obrigado pelo arquivo."
            ]
        ]
    ];
    
    // Selecionar resposta baseada no personagem e tipo
    $characterResponses = $responses[$messageType][$characterSlug] ?? $responses[$messageType]['default'];
    $responseText = $characterResponses[array_rand($characterResponses)];
    
    // Adicionar comentário sobre a legenda se houver
    if (!empty($caption)) {
        $captionResponses = [
            'goku' => "E sobre o que você escreveu: interessante!",
            'vegeta' => "Quanto ao seu comentário... hmph, não está errado.",
            'naruto' => "E o que você escreveu é muito legal, dattebayo!",
            'default' => "E obrigado pelo comentário também!"
        ];
        
        $captionResponse = $captionResponses[$characterSlug] ?? $captionResponses['default'];
        $responseText .= " " . $captionResponse;
    }
    
    // Salvar resposta do personagem
    $responseData = [
        'conversation_id' => $conversationId,
        'sender_type' => 'character',
        'sender_id' => $conversation['character_id'],
        'message_type' => 'text',
        'content' => $responseText,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $responseId = $db->insert('messages', $responseData);
    
    if ($responseId) {
        // Atualizar conversa com a resposta
        $db->update('conversations', [
            'last_message' => $responseText,
            'last_message_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$conversationId]);
        
        return [
            'id' => $responseId,
            'sender_type' => 'character',
            'message_type' => 'text',
            'content' => $responseText,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    return null;
}
?>

