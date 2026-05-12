<?php
/**
 * WEBHOOK - Notificações do Mercado Pago
 * URL: https://app.cybercoari.com.br/notifica.php
 */

require_once 'config.php';

// Log da requisição
$input = file_get_contents('php://input');
$timestamp = date('Y-m-d H:i:s');

// Salvar log
file_put_contents('webhook_log.txt', "\n=== $timestamp ===\n", FILE_APPEND);
file_put_contents('webhook_log.txt', "BODY: " . $input . "\n", FILE_APPEND);

$data = json_decode($input, true);

// Extrair payment_id de TODOS os formatos possíveis
$payment_id = null;

// Formato 1: { "data": { "id": "123" } }
if (isset($data['data']['id'])) {
    $payment_id = $data['data']['id'];
}
// Formato 2: { "id": "123" }
elseif (isset($data['id'])) {
    $payment_id = $data['id'];
}
// Formato 3: { "resource": "123" } - MUITO IMPORTANTE!
elseif (isset($data['resource'])) {
    $payment_id = $data['resource'];
}
// Formato 4: { "payment_id": "123" }
elseif (isset($data['payment_id'])) {
    $payment_id = $data['payment_id'];
}

file_put_contents('webhook_log.txt', "Payment ID extraído: $payment_id\n", FILE_APPEND);

if ($payment_id) {
    // Aguardar 2 segundos para garantir que o pagamento está processado
    sleep(2);
    
    // Consultar status no Mercado Pago
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.mercadopago.com/v1/payments/$payment_id",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MERCADOPAGO_ACCESS_TOKEN,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    file_put_contents('webhook_log.txt', "API Response Code: $http_code\n", FILE_APPEND);
    
    if ($http_code == 200) {
        $payment = json_decode($response);
        $status = $payment->status;
        $transaction_amount = $payment->transaction_amount;
        
        file_put_contents('webhook_log.txt', "Status do pagamento: $status\n", FILE_APPEND);
        file_put_contents('webhook_log.txt', "Valor: R$ $transaction_amount\n", FILE_APPEND);
        
        // Verificar se o pagamento já existe no banco
        $check_stmt = mysqli_prepare($conexao, "SELECT id FROM status WHERE codigo = ?");
        mysqli_stmt_bind_param($check_stmt, "s", $payment_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $exists = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);
        
        if ($exists) {
            // Atualizar status existente
            $stmt = mysqli_prepare($conexao, "UPDATE status SET status = ?, updated_at = NOW() WHERE codigo = ?");
            mysqli_stmt_bind_param($stmt, "ss", $status, $payment_id);
        } else {
            // Inserir novo pagamento
            $stmt = mysqli_prepare($conexao, "INSERT INTO status (status, codigo, transaction_amount, created_at) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "ssd", $status, $payment_id, $transaction_amount);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            file_put_contents('webhook_log.txt', "✅ Banco atualizado com sucesso! Status: $status\n", FILE_APPEND);
        } else {
            file_put_contents('webhook_log.txt', "❌ Erro no banco: " . mysqli_error($conexao) . "\n", FILE_APPEND);
        }
        mysqli_stmt_close($stmt);
    } else {
        file_put_contents('webhook_log.txt', "❌ Erro na API: $http_code\n", FILE_APPEND);
    }
} else {
    file_put_contents('webhook_log.txt', "❌ Não foi possível extrair o payment_id\n", FILE_APPEND);
}

// Sempre retornar 200 OK
http_response_code(200);
echo "OK";
?>