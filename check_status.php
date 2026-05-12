<?php
require_once 'config.php';

header('Content-Type: application/json');

$payment_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$payment_id) {
    echo json_encode(['status' => 'unknown', 'error' => 'ID não informado']);
    exit;
}

// Buscar status no banco de dados
$stmt = mysqli_prepare($conexao, "SELECT status FROM status WHERE codigo = ?");
mysqli_stmt_bind_param($stmt, "s", $payment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode(['status' => $row['status']]);
} else {
    // Se não encontrar no banco, consultar direto na API do Mercado Pago
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.mercadopago.com/v1/payments/$payment_id",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MERCADOPAGO_ACCESS_TOKEN
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $payment = json_decode($response);
        $status = $payment->status;
        
        // Salvar no banco
        $stmt2 = mysqli_prepare($conexao, "INSERT INTO status (status, codigo) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = ?");
        mysqli_stmt_bind_param($stmt2, "sss", $status, $payment_id, $status);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
        
        echo json_encode(['status' => $status]);
    } else {
        echo json_encode(['status' => 'pending', 'message' => 'Aguardando pagamento']);
    }
}

mysqli_stmt_close($stmt);
?>