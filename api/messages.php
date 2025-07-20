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
    error_log("Erro na API de mensagens: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
}

function handleGet() {
    global $db, $userId;
    
    if (!isset($_GET['conversation_id'])) {
        jsonResponse(['success' => false, 'message' => 'ID da conversa é obrigatório'], 400);
    }
    
    $conversationId = (int)$_GET['conversation_id'];
    
    // Verificar se a conversa pertence ao usuário
    $conversation = $db->fetchOne("
        SELECT id FROM conversations WHERE id = ? AND user_id = ?
    ", [$conversationId, $userId]);
    
    if (!$conversation) {
        jsonResponse(['success' => false, 'message' => 'Conversa não encontrada'], 404);
    }
    
    // Buscar mensagens
    $messages = $db->fetchAll("
        SELECT * FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at ASC
    ", [$conversationId]);
    
    jsonResponse(['success' => true, 'messages' => $messages]);
}

function handlePost() {
    global $db, $userId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
    }
    
    switch ($input['action']) {
        case 'send':
            sendMessage($input);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Ação inválida'], 400);
    }
}

function handleDelete() {
    global $db, $userId;
    
    if (isset($_GET['id'])) {
        $messageId = (int)$_GET['id'];
        
        // Verificar se a mensagem pertence ao usuário
        $message = $db->fetchOne("
            SELECT m.id FROM messages m
            JOIN conversations c ON m.conversation_id = c.id
            WHERE m.id = ? AND c.user_id = ?
        ", [$messageId, $userId]);
        
        if (!$message) {
            jsonResponse(['success' => false, 'message' => 'Mensagem não encontrada'], 404);
        }
        
        $success = $db->delete('messages', 'id = ?', [$messageId]);
        
        if ($success) {
            jsonResponse(['success' => true, 'message' => 'Mensagem excluída com sucesso']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Erro ao excluir mensagem'], 500);
        }
    } else {
        jsonResponse(['success' => false, 'message' => 'ID da mensagem é obrigatório'], 400);
    }
}

function sendMessage($input) {
    global $db, $userId;
    
    // Validar dados obrigatórios
    if (!isset($input['conversation_id']) || !isset($input['content'])) {
        jsonResponse(['success' => false, 'message' => 'Dados obrigatórios não fornecidos'], 400);
    }
    
    $conversationId = (int)$input['conversation_id'];
    $content = trim($input['content']);
    $messageType = $input['message_type'] ?? 'text';
    
    if (empty($content)) {
        jsonResponse(['success' => false, 'message' => 'Conteúdo da mensagem não pode estar vazio'], 400);
    }
    
    // Verificar se a conversa pertence ao usuário
    $conversation = $db->fetchOne("
        SELECT c.*, ch.id as character_id, ch.name as character_name, 
               ch.personality as character_personality
        FROM conversations c
        JOIN characters ch ON c.character_id = ch.id
        WHERE c.id = ? AND c.user_id = ?
    ", [$conversationId, $userId]);
    
    if (!$conversation) {
        jsonResponse(['success' => false, 'message' => 'Conversa não encontrada'], 404);
    }
    
    // Salvar mensagem do usuário
    $messageData = [
        'conversation_id' => $conversationId,
        'sender_type' => 'user',
        'sender_id' => $userId,
        'message_type' => $messageType,
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $messageId = $db->insert('messages', $messageData);
    
    if (!$messageId) {
        jsonResponse(['success' => false, 'message' => 'Erro ao salvar mensagem'], 500);
    }
    
    // Atualizar conversa
    $db->update('conversations', [
        'last_message' => $content,
        'last_message_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$conversationId]);
    
    // Gerar resposta do personagem
    $characterResponse = generateCharacterResponse($content, $conversation, $conversationId);
    
    $response = [
        'success' => true,
        'message_id' => $messageId,
        'message' => 'Mensagem enviada com sucesso'
    ];
    
    if ($characterResponse) {
        $response['character_response'] = $characterResponse;
    }
    
    jsonResponse($response);
}

function generateCharacterResponse($userMessage, $conversation, $conversationId) {
    global $db;
    
    // Tentar usar IA primeiro
    $aiResponse = generateAIResponse($userMessage, $conversation);
    
    // Se não conseguir usar IA, usar resposta baseada em regras
    if (!$aiResponse) {
        $aiResponse = generateRuleBasedResponse($userMessage, $conversation);
    }
    
    if ($aiResponse) {
        // Salvar resposta do personagem
        $responseData = [
            'conversation_id' => $conversationId,
            'sender_type' => 'character',
            'sender_id' => $conversation['character_id'],
            'message_type' => 'text',
            'content' => $aiResponse,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $responseId = $db->insert('messages', $responseData);
        
        if ($responseId) {
            // Atualizar conversa com a resposta
            $db->update('conversations', [
                'last_message' => $aiResponse,
                'last_message_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$conversationId]);
            
            return [
                'id' => $responseId,
                'sender_type' => 'character',
                'message_type' => 'text',
                'content' => $aiResponse,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    return null;
}

function generateAIResponse($userMessage, $conversation) {
    // Obter chave da API do OpenAI
    $db = Database::getInstance();
    $apiKey = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'openai_api_key'");
    
    if (!$apiKey || empty($apiKey['setting_value'])) {
        return null; // Sem chave da API
    }
    
    $apiKey = $apiKey['setting_value'];
    
    // Preparar contexto da conversa
    $systemPrompt = "Você é {$conversation['character_name']}. " . 
                   "Sua personalidade: {$conversation['character_personality']} " .
                   "Responda sempre como este personagem, mantendo sua personalidade e forma de falar. " .
                   "Seja natural, envolvente e fiel ao personagem. Mantenha as respostas concisas mas interessantes.";
    
    // Buscar histórico recente da conversa
    $recentMessages = $db->fetchAll("
        SELECT sender_type, content FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ", [$conversation['id']]);
    
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];
    
    // Adicionar histórico (em ordem cronológica)
    $recentMessages = array_reverse($recentMessages);
    foreach ($recentMessages as $msg) {
        $role = $msg['sender_type'] === 'user' ? 'user' : 'assistant';
        $messages[] = ['role' => $role, 'content' => $msg['content']];
    }
    
    // Adicionar mensagem atual
    $messages[] = ['role' => 'user', 'content' => $userMessage];
    
    // Fazer requisição para OpenAI
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => $messages,
        'max_tokens' => 300,
        'temperature' => 0.8
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return trim($result['choices'][0]['message']['content']);
        }
    }
    
    return null;
}

function generateRuleBasedResponse($userMessage, $conversation) {
    $characterName = $conversation['character_name'];
    $characterSlug = $conversation['slug'] ?? '';
    $message = strtolower($userMessage);
    
    // Respostas baseadas em palavras-chave
    $responses = [
        // Saudações
        'oi' => [
            'goku' => "Oi! Como você está? Quer treinar comigo?",
            'vegeta' => "Hmph! Olá. O que você quer, terráqueo?",
            'naruto' => "Oi! Que bom te ver, dattebayo!",
            'superman' => "Olá! Como posso ajudá-lo hoje?",
            'default' => "Olá! É bom falar com você!"
        ],
        
        // Perguntas sobre treino/luta
        'treino' => [
            'goku' => "Treino é a melhor coisa do mundo! Vamos treinar juntos? Posso te ensinar algumas técnicas!",
            'vegeta' => "Treino é essencial para um guerreiro. Mas você tem força suficiente para treinar comigo?",
            'naruto' => "Eu treino todos os dias para ficar mais forte, dattebayo! Quer treinar comigo?",
            'default' => "Treinar é importante para melhorar sempre!"
        ],
        
        // Perguntas sobre poder/força
        'poder' => [
            'goku' => "O verdadeiro poder vem de proteger quem você ama! Não é só sobre ser forte.",
            'vegeta' => "Poder? Eu sou o Príncipe dos Saiyajins! Meu poder é incomparável!",
            'superman' => "Com grandes poderes vêm grandes responsabilidades. Uso minha força para proteger.",
            'default' => "O poder deve ser usado com sabedoria."
        ],
        
        // Perguntas sobre amigos
        'amigo' => [
            'goku' => "Amigos são o mais importante! Eles me dão força para continuar lutando!",
            'naruto' => "Amigos são tudo para mim, dattebayo! Nunca abandono um amigo!",
            'default' => "Amizade é uma das coisas mais valiosas da vida!"
        ],
        
        // Despedidas
        'tchau' => [
            'goku' => "Tchau! Foi legal conversar! Vamos treinar de novo em breve!",
            'naruto' => "Tchau! Até a próxima, dattebayo!",
            'default' => "Tchau! Foi ótimo conversar com você!"
        ]
    ];
    
    // Procurar por palavras-chave na mensagem
    foreach ($responses as $keyword => $characterResponses) {
        if (strpos($message, $keyword) !== false) {
            if (isset($characterResponses[$characterSlug])) {
                return $characterResponses[$characterSlug];
            } elseif (isset($characterResponses['default'])) {
                return $characterResponses['default'];
            }
        }
    }
    
    // Respostas genéricas por personagem
    $genericResponses = [
        'goku' => [
            "Interessante! Me conta mais sobre isso!",
            "Que legal! Isso me lembra de uma aventura que tive!",
            "Hmm, nunca pensei nisso dessa forma!",
            "Você é muito inteligente! Eu não sou muito bom com essas coisas complicadas, hehe!",
            "Isso parece divertido! Posso participar?"
        ],
        'vegeta' => [
            "Hmph! Isso é óbvio para alguém da minha estirpe.",
            "Interessante... para um terráqueo.",
            "Você tem um ponto válido, admito.",
            "Não esperava essa resposta de você.",
            "Continue... você despertou minha curiosidade."
        ],
        'naruto' => [
            "Isso é incrível, dattebayo!",
            "Nunca desista dos seus sonhos!",
            "Você me lembra do meu jeito de ninja!",
            "Vamos fazer isso juntos, dattebayo!",
            "Acredite em si mesmo!"
        ],
        'sasuke' => [
            "...",
            "Entendo.",
            "Isso não me interessa.",
            "Hmm.",
            "Continue."
        ],
        'superman' => [
            "Isso é muito importante. Como posso ajudar?",
            "Você está certo. Devemos sempre fazer o que é correto.",
            "Juntos podemos fazer a diferença.",
            "Sua perspectiva é valiosa.",
            "Vamos trabalhar juntos para resolver isso."
        ],
        'batman' => [
            "Interessante. Preciso investigar mais sobre isso.",
            "Já considerei essa possibilidade.",
            "Você tem razão. Precisamos de um plano.",
            "Isso requer mais análise.",
            "Vou adicionar isso aos meus arquivos."
        ]
    ];
    
    // Selecionar resposta genérica
    if (isset($genericResponses[$characterSlug])) {
        $responses = $genericResponses[$characterSlug];
        return $responses[array_rand($responses)];
    }
    
    // Resposta padrão
    $defaultResponses = [
        "Que interessante! Me conte mais sobre isso.",
        "Entendo seu ponto de vista.",
        "Isso é uma perspectiva interessante!",
        "Obrigado por compartilhar isso comigo.",
        "Vamos continuar nossa conversa!"
    ];
    
    return $defaultResponses[array_rand($defaultResponses)];
}
?>

