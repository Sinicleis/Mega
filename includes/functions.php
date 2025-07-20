<?php
// Funções auxiliares para o WhatsJuju Chat

// Sanitizar entrada
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Hash da senha
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verificar senha
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Gerar token aleatório
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Verificar se é requisição AJAX
function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Resposta JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Redirecionar
function redirect($url) {
    header("Location: $url");
    exit;
}

// Formatar tamanho de arquivo
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    
    return $bytes;
}

// Gerar slug
function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

// Truncar texto
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

// Escapar HTML
function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Verificar se arquivo é imagem
function isImage($filePath) {
    $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    return in_array($mimeType, $imageTypes);
}

// Redimensionar imagem
function resizeImage($sourcePath, $destPath, $maxWidth, $maxHeight) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $sourceType = $imageInfo[2];
    
    // Calcular novas dimensões
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $newWidth = intval($sourceWidth * $ratio);
    $newHeight = intval($sourceHeight * $ratio);
    
    // Criar imagem de origem
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    // Criar imagem de destino
    $destImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preservar transparência para PNG e GIF
    if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
        imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Redimensionar
    imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    // Salvar imagem
    $result = false;
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($destImage, $destPath, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($destImage, $destPath);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($destImage, $destPath);
            break;
    }
    
    // Limpar memória
    imagedestroy($sourceImage);
    imagedestroy($destImage);
    
    return $result;
}

// Criar thumbnail
function createThumbnail($sourcePath, $destPath, $size = 150) {
    return resizeImage($sourcePath, $destPath, $size, $size);
}

// Validar upload
function validateUpload($file, $allowedTypes = [], $maxSize = 10485760) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Erro no upload do arquivo';
        return $errors;
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = 'Arquivo muito grande. Máximo: ' . formatFileSize($maxSize);
    }
    
    if (!empty($allowedTypes)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Tipo de arquivo não permitido';
        }
    }
    
    return $errors;
}

// Gerar nome único para arquivo
function generateUniqueFileName($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

// Criar diretório se não existir
function ensureDirectoryExists($path) {
    if (!is_dir($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

// Log de erro personalizado
function logError($message, $context = []) {
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $logMessage .= ' - Context: ' . json_encode($context);
    }
    error_log($logMessage);
}

// Verificar se string contém apenas caracteres alfanuméricos
function isAlphanumeric($string) {
    return ctype_alnum($string);
}

// Limpar string para uso em URL
function cleanForUrl($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\-_]/', '', $string);
    return $string;
}

// Converter array para CSV
function arrayToCsv($array, $filename) {
    $output = fopen($filename, 'w');
    
    if (!empty($array)) {
        // Cabeçalhos
        fputcsv($output, array_keys($array[0]));
        
        // Dados
        foreach ($array as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    return file_exists($filename);
}

// Verificar se usuário é mobile
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

// Obter IP do usuário
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Verificar se é HTTPS
function isHttps() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
}

// Obter URL base
function getBaseUrl() {
    $protocol = isHttps() ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    return rtrim($protocol . $host . $path, '/');
}

// Formatar data para exibição
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date)) return '';
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

// Calcular tempo relativo
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'agora';
    if ($time < 3600) return floor($time/60) . 'm';
    if ($time < 86400) return floor($time/3600) . 'h';
    if ($time < 2592000) return floor($time/86400) . 'd';
    if ($time < 31536000) return floor($time/2592000) . 'mês';
    
    return floor($time/31536000) . 'ano';
}
?>

