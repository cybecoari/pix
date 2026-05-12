<?php
/**
 * CONFIGURAÇÕES PRINCIPAIS
 * Carrega tokens e conecta ao banco de dados
 */

// Carregar tokens
require_once __DIR__ . '/tokens.php';

// Conectar ao banco de dados
$conexao = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Verificar conexão
if (!$conexao) {
    error_log("Erro de conexão: " . mysqli_connect_error());
    if (ENVIRONMENT === 'development') {
        die("Falha na conexão: " . mysqli_connect_error());
    } else {
        die("Erro interno. Contate o administrador.");
    }
}

// Configurar charset para acentos e caracteres especiais
mysqli_set_charset($conexao, "utf8mb4");

// Criar tabela se não existir (opcional - roda automaticamente)
$sql_create = "CREATE TABLE IF NOT EXISTS status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(50),
    codigo VARCHAR(100),
    qr_code TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

mysqli_query($conexao, $sql_create);
?>