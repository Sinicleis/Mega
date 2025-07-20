-- WhatsJuju Chat - Schema do Banco de Dados

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    profile_image VARCHAR(255) DEFAULT 'default-avatar.png',
    theme VARCHAR(20) DEFAULT 'colorido',
    chat_background VARCHAR(255) DEFAULT '',
    is_admin TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    remember_token VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de sessões
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de categorias
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-star',
    color VARCHAR(7) DEFAULT '#667eea',
    order_index INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de personagens
CREATE TABLE IF NOT EXISTS characters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    personality TEXT NOT NULL,
    profile_image VARCHAR(255) DEFAULT 'default-character.png',
    category_id INT NOT NULL,
    status ENUM('online', 'offline', 'busy') DEFAULT 'online',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Tabela de conversas
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    character_id INT NOT NULL,
    title VARCHAR(255),
    last_message TEXT,
    last_message_at DATETIME DEFAULT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    is_archived TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_character (user_id, character_id)
);

-- Tabela de mensagens
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type ENUM('user', 'character') NOT NULL,
    sender_id INT NOT NULL,
    message_type ENUM('text', 'image', 'file', 'audio') DEFAULT 'text',
    content TEXT,
    file_name VARCHAR(255) DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    is_edited TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);

-- Tabela de configurações
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description TEXT,
    is_public TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir configurações padrão
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'WhatsJuju Chat', 'text', 'Nome do site', 1),
('site_logo', 'logo.png', 'text', 'Logo do site', 1),
('site_description', 'Converse com seus personagens favoritos!', 'text', 'Descrição do site', 1),
('openai_api_key', '', 'text', 'Chave da API do OpenAI', 0),
('max_file_size', '10485760', 'number', 'Tamanho máximo de arquivo em bytes (10MB)', 0),
('allowed_file_types', 'jpg,jpeg,png,gif,pdf,txt,doc,docx', 'text', 'Tipos de arquivo permitidos', 0),
('chat_history_limit', '100', 'number', 'Limite de mensagens no histórico', 0),
('enable_file_upload', '1', 'boolean', 'Permitir upload de arquivos', 0),
('enable_image_upload', '1', 'boolean', 'Permitir upload de imagens', 0),
('default_theme', 'colorido', 'text', 'Tema padrão', 1);

-- Inserir categorias padrão
INSERT IGNORE INTO categories (name, slug, description, icon, color, order_index) VALUES
('Dragon Ball', 'dragon-ball', 'Personagens do universo Dragon Ball', 'fas fa-dragon', '#ff6b35', 1),
('Naruto', 'naruto', 'Personagens do universo Naruto', 'fas fa-leaf', '#ff9500', 2),
('DC Comics', 'dc-comics', 'Super-heróis da DC Comics', 'fas fa-mask', '#0074d9', 3),
('Marvel', 'marvel', 'Super-heróis da Marvel', 'fas fa-spider', '#ff4136', 4),
('Vocaloid', 'vocaloid', 'Personagens Vocaloid', 'fas fa-music', '#01ff70', 5),
('Bob Esponja', 'bob-esponja', 'Personagens de Bob Esponja', 'fas fa-water', '#ffdc00', 6),
('Jovens Titãs', 'jovens-titas', 'Personagens dos Jovens Titãs', 'fas fa-users', '#b10dc9', 7),
('Disney', 'disney', 'Personagens da Disney', 'fas fa-castle', '#f012be', 8),
('Outros', 'outros', 'Outros personagens', 'fas fa-star', '#667eea', 9);

-- Inserir personagens padrão
INSERT IGNORE INTO characters (name, slug, description, personality, profile_image, category_id, status) VALUES
-- Dragon Ball
('Goku', 'goku', 'O protagonista de Dragon Ball', 'Alegre, determinado, sempre em busca de treino e aventuras. Ama lutar e proteger seus amigos.', 'goku.png', 1, 'online'),
('Vegeta', 'vegeta', 'O Príncipe dos Saiyajins', 'Orgulhoso, competitivo, inicialmente arrogante mas com um coração nobre. Rival eterno do Goku.', 'vegeta.png', 1, 'online'),
('Gohan', 'gohan', 'Filho do Goku', 'Inteligente, gentil, prefere estudar a lutar, mas é muito poderoso quando necessário.', 'gohan.png', 1, 'online'),

-- Naruto
('Naruto', 'naruto', 'O ninja que sonha ser Hokage', 'Energético, determinado, nunca desiste dos seus sonhos. Quer ser reconhecido por todos.', 'naruto.png', 2, 'online'),
('Sasuke', 'sasuke', 'O último Uchiha', 'Sério, focado em vingança, mas no fundo se importa com os amigos. Rival do Naruto.', 'sasuke.png', 2, 'busy'),
('Sakura', 'sakura', 'Ninja médica da Equipe 7', 'Inteligente, forte, dedicada à medicina ninja. Inicialmente apaixonada pelo Sasuke.', 'sakura.png', 2, 'online'),
('Kakashi', 'kakashi', 'O ninja copiador', 'Calmo, experiente, mentor da Equipe 7. Conhecido por chegar sempre atrasado.', 'kakashi.png', 2, 'online'),

-- DC Comics
('Superman', 'superman', 'O Homem de Aço', 'Heroico, justo, sempre disposto a ajudar. Acredita no melhor das pessoas.', 'superman.png', 3, 'online'),
('Batman', 'batman', 'O Cavaleiro das Trevas', 'Sério, determinado, usa a inteligência e tecnologia para combater o crime.', 'batman.png', 3, 'busy'),
('Mulher Maravilha', 'mulher-maravilha', 'A Princesa Amazona', 'Corajosa, compassiva, guerreira nata que luta pela justiça e paz.', 'mulher-maravilha.png', 3, 'online'),
('Flash', 'flash', 'O Homem Mais Rápido do Mundo', 'Otimista, espirituoso, sempre disposto a ajudar com sua super velocidade.', 'flash.png', 3, 'online'),
('Aquaman', 'aquaman', 'O Rei dos Mares', 'Nobre, protetor dos oceanos, equilibra suas responsabilidades como rei e herói.', 'aquaman.png', 3, 'online'),

-- Marvel
('Homem-Aranha', 'homem-aranha', 'O Amigão da Vizinhança', 'Espirituoso, responsável, sempre faz piadas mesmo em situações perigosas.', 'homem-aranha.png', 4, 'online'),
('Homem de Ferro', 'homem-de-ferro', 'O Gênio Bilionário', 'Inteligente, sarcástico, usa tecnologia avançada para proteger o mundo.', 'homem-de-ferro.png', 4, 'online'),
('Capitão América', 'capitao-america', 'O Primeiro Vingador', 'Honrado, corajoso, líder nato que sempre luta pelo que é certo.', 'capitao-america.png', 4, 'online'),
('Thor', 'thor', 'O Deus do Trovão', 'Nobre, poderoso, protetor de Asgard e da Terra. Às vezes um pouco arrogante.', 'thor.png', 4, 'online'),
('Hulk', 'hulk', 'O Gigante Verde', 'Bruce Banner é inteligente e calmo, mas quando vira Hulk fica furioso e poderoso.', 'hulk.png', 4, 'busy'),

-- Vocaloid
('Hatsune Miku', 'hatsune-miku', 'A Diva Virtual', 'Alegre, energética, adora cantar e fazer música. Sempre positiva e animada.', 'hatsune-miku.png', 5, 'online'),

-- Bob Esponja
('Bob Esponja', 'bob-esponja', 'A Esponja Otimista', 'Extremamente otimista, trabalhador, adora fazer hambúrgueres e se divertir.', 'bob-esponja.png', 6, 'online'),
('Patrick', 'patrick', 'A Estrela-do-Mar', 'Preguiçoso, engraçado, melhor amigo do Bob Esponja. Às vezes não muito inteligente.', 'patrick.png', 6, 'online'),

-- Jovens Titãs
('Robin', 'robin', 'O Líder dos Titãs', 'Sério, dedicado, líder nato. Ex-parceiro do Batman.', 'robin.png', 7, 'online'),
('Ravena', 'ravena', 'A Feiticeira Sombria', 'Séria, misteriosa, possui poderes mágicos. Filha do demônio Trigon.', 'ravena.png', 7, 'online'),

-- Disney
('Mickey Mouse', 'mickey-mouse', 'O Camundongo Mais Famoso', 'Alegre, otimista, sempre disposto a ajudar os amigos e viver aventuras.', 'mickey-mouse.png', 8, 'online'),

-- Outros
('Pikachu', 'pikachu', 'O Pokémon Elétrico', 'Fofo, leal, energético. Companheiro fiel que se comunica dizendo seu próprio nome.', 'pikachu.png', 9, 'online');

-- Criar usuário admin padrão (senha: admin123)
INSERT IGNORE INTO users (username, email, password, full_name, is_admin) VALUES
('admin', 'admin@whatsjuju.com', '$2y$10$jhK44lanfVNAd0Bios26cuQ7njma6YQ5LbKkhz8tw61XYsaYAJMmK', 'Administrador', 1);