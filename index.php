<?php
require_once 'config.php';

// Configurar sessão em diretório permitido ANTES de iniciar
$session_path = '/tmp';
if (is_writable($session_path)) {
    session_save_path($session_path);
}

// Tentar iniciar sessão sem erro
try {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
} catch (Exception $e) {
    error_log("Erro na sessão: " . $e->getMessage());
}

$qr_code_text = null;
$payment_id = null;
$erro = null;

// Verificar se já tem pagamento (usando cookie)
$cookie_name = 'payment_id';
if (isset($_COOKIE[$cookie_name]) && isset($_COOKIE['payment_expires']) && time() < $_COOKIE['payment_expires']) {
    $payment_id = $_COOKIE[$cookie_name];
    $qr_code_text = $_COOKIE['qr_code_text'];
} else {
    // Limpar cookies antigos
    setcookie($cookie_name, '', time() - 3600, '/');
    setcookie('qr_code_text', '', time() - 3600, '/');
    setcookie('payment_expires', '', time() - 3600, '/');
    
    // Gerar chave única para idempotência
    $idempotency_key = uniqid() . '-' . rand(1000, 9999) . '-' . time();
    
    // Dados para pagamento PIX
    $dados = [
        "transaction_amount" => 1.00,
        "description" => "Pagamento Cyber Coari",
        "payment_method_id" => "pix",
        "notification_url" => "https://app.cybercoari.com.br/notifica.php",
        "payer" => [
            "email" => "cliente_" . time() . "@cybercoari.com.br"
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mercadopago.com/v1/payments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . $idempotency_key,
            'Authorization: Bearer ' . MERCADOPAGO_ACCESS_TOKEN
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 201) {
        $result = json_decode($response);
        $payment_id = $result->id;
        $qr_code_text = $result->point_of_interaction->transaction_data->qr_code;
        
        // Salvar no banco
        $stmt = mysqli_prepare($conexao, "INSERT INTO status (status, codigo) VALUES ('pending', ?)");
        mysqli_stmt_bind_param($stmt, "s", $payment_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Salvar em cookies
        setcookie($cookie_name, $payment_id, time() + 1800, '/', '', false, true);
        setcookie('qr_code_text', $qr_code_text, time() + 1800, '/', '', false, true);
        setcookie('payment_expires', time() + 1800, time() + 1800, '/', '', false, true);
    } else {
        $error = json_decode($response);
        $erro = $error->message ?? "Erro HTTP $http_code";
        if (isset($error->cause)) {
            $erro .= "<br>Detalhe: " . htmlspecialchars($error->cause[0]->description ?? '');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>PIX - Cyber Coari</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 25px;
            padding: 30px 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            text-align: center;
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        h1 {
            color: #333;
            margin-bottom: 8px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .valor {
            background: linear-gradient(135deg, #00a650 0%, #008c43 100%);
            color: white;
            padding: 15px;
            border-radius: 15px;
            margin: 20px 0;
        }
        
        .valor .label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .valor .amount {
            font-size: 42px;
            font-weight: bold;
        }
        
        .qr-code {
            display: block;
            width: 250px;
            height: 250px;
            margin: 20px auto;
            border: 3px solid #e0e0e0;
            border-radius: 20px;
            padding: 10px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .codigo {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            font-family: monospace;
            word-break: break-all;
            margin: 20px 0;
            font-size: 11px;
            border: 1px solid #e0e0e0;
            text-align: left;
            max-height: 100px;
            overflow-y: auto;
        }
        
        .btn-copiar {
            background: #00a650;
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-copiar:hover {
            background: #008c43;
            transform: scale(1.02);
        }
        
        .btn-copiar:active {
            transform: scale(0.98);
        }
        
        .status {
            margin-top: 20px;
            padding: 12px;
            border-radius: 12px;
            font-weight: bold;
        }
        
        .pending {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .approved {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .refresh-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 15px;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: #5a6268;
        }
        
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #00a650;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .instrucao {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 10px;
            margin: 15px 0;
            font-size: 13px;
            color: #004085;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 20px 15px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .valor .amount {
                font-size: 32px;
            }
            
            .qr-code {
                width: 200px;
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 Pagamento via PIX</h1>
        <div class="subtitle">Escaneie o QR Code ou copie o código</div>
        
        <div class="valor">
            <div class="label">Valor a pagar</div>
            <div class="amount">R$ 1,00</div>
        </div>
        
        <?php if ($qr_code_text): ?>
            
            <!-- QR CODE IMAGEM - API gratuita funcionando -->
            <img class="qr-code" src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo urlencode($qr_code_text); ?>" alt="QR Code PIX" />
            
            <div class="instrucao">
                📱 Abra o app do seu banco, escaneie o QR Code ou cole o código abaixo
            </div>
            
            <!-- Código PIX para copiar -->
            <div class="codigo" id="codigoPix">
                <?php echo htmlspecialchars($qr_code_text); ?>
            </div>
            
            <button class="btn-copiar" onclick="copiarPix()">📋 Copiar código PIX</button>
            
            <div class="status pending" id="status">
                <span id="statusText">⏳ Aguardando pagamento...</span>
            </div>
            
            <button class="refresh-btn" onclick="verificarAgora()">🔄 Verificar pagamento</button>
            
            <div class="footer">
                Após o pagamento, a confirmação é automática<br>
                O código expira em 30 minutos
            </div>
            
            <script>
                function copiarPix() {
                    const codigo = document.getElementById('codigoPix');
                    const textarea = document.createElement('textarea');
                    textarea.value = codigo.innerText;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    alert('✅ Código PIX copiado com sucesso!');
                }
                
                let paymentId = '<?php echo $payment_id; ?>';
                let interval;
                let verificando = false;
                
                function verificarStatus() {
                    if (verificando || !paymentId) return;
                    verificando = true;
                    
                    const statusText = document.getElementById('statusText');
                    const originalText = statusText.innerHTML;
                    statusText.innerHTML = '<span class="loading"></span> Verificando...';
                    
                    fetch('check_status.php?id=' + paymentId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'approved') {
                                statusText.innerHTML = '✅ Pagamento CONFIRMADO! Redirecionando...';
                                document.getElementById('status').className = 'status approved';
                                if (interval) clearInterval(interval);
                                
                                // Salvar cookie e redirecionar para página de sucesso
                                document.cookie = "last_payment_id=" + paymentId + "; path=/";
                                setTimeout(() => {
                                    window.location.href = 'sucesso.php?id=' + paymentId;
                                }, 2000);
                            } else if (data.status === 'rejected') {
                                statusText.innerHTML = '❌ Pagamento recusado. Tente novamente.';
                                document.getElementById('status').className = 'status error';
                                if (interval) clearInterval(interval);
                            } else {
                                statusText.innerHTML = '⏳ Aguardando pagamento...';
                            }
                            verificando = false;
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            statusText.innerHTML = originalText;
                            verificando = false;
                        });
                }
                
                function verificarAgora() {
                    verificarStatus();
                }
                
                if (paymentId) {
                    interval = setInterval(verificarStatus, 5000);
                    setTimeout(verificarStatus, 1000);
                }
            </script>
            
        <?php elseif ($erro): ?>
            <div class="status error">
                ❌ <?php echo htmlspecialchars($erro); ?>
            </div>
            <button class="refresh-btn" onclick="location.reload()">🔄 Tentar novamente</button>
            
        <?php else: ?>
            <div class="status pending">
                ⏳ Gerando pagamento...
            </div>
            <div class="loading" style="margin: 20px auto;"></div>
            <script>
                setTimeout(() => location.reload(), 3000);
            </script>
        <?php endif; ?>
    </div>
</body>
</html>