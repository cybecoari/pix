<?php
require_once 'config.php';

// Pegar ID do pagamento
$payment_id = isset($_GET['id']) ? $_GET['id'] : null;

if ($payment_id) {
    // Buscar informações do pagamento
    $stmt = mysqli_prepare($conexao, "SELECT status, codigo, created_at FROM status WHERE codigo = ?");
    mysqli_stmt_bind_param($stmt, "s", $payment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $payment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Se não veio ID, tentar buscar último pagamento aprovado na sessão
if (!$payment_id && isset($_COOKIE['last_payment_id'])) {
    $payment_id = $_COOKIE['last_payment_id'];
    // Buscar novamente
    $stmt = mysqli_prepare($conexao, "SELECT status, codigo, created_at FROM status WHERE codigo = ?");
    mysqli_stmt_bind_param($stmt, "s", $payment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $payment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Confirmado - Cyber Coari</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #00a650 0%, #008c43 100%);
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
            padding: 40px 30px;
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
        
        .checkmark {
            width: 80px;
            height: 80px;
            background: #00a650;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: scaleIn 0.5s ease-in-out;
        }
        
        @keyframes scaleIn {
            0% {
                transform: scale(0);
            }
            70% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .checkmark svg {
            width: 50px;
            height: 50px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .detalhes {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .detalhes p {
            margin: 10px 0;
            color: #555;
        }
        
        .detalhes strong {
            color: #333;
        }
        
        .btn {
            background: #00a650;
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            background: #008c43;
            transform: scale(1.02);
        }
        
        .btn-secundario {
            background: #6c757d;
            margin-top: 10px;
        }
        
        .btn-secundario:hover {
            background: #5a6268;
        }
        
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
        
        .confete {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 999;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="confete" id="confete"></div>
    
    <div class="container">
        <div class="checkmark">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        
        <h1>🎉 Pagamento Confirmado!</h1>
        <div class="subtitle">Seu pagamento foi processado com sucesso</div>
        
        <div class="detalhes">
            <p><strong>💰 Valor:</strong> R$ 1,00</p>
            <p><strong>📅 Data:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            <p><strong>🆔 Transação:</strong> <?php echo htmlspecialchars(substr($payment_id ?? '', 0, 20)) . '...'; ?></p>
            <p><strong>✅ Status:</strong> <span style="color:#00a650;">Aprovado</span></p>
        </div>
        
        <a href="index.php" class="btn">✅ Nova transação</a>
        <a href="https://wa.me/5597981187297?text=Olá%2C%20acabei%20de%20fazer%20um%20pagamento%20no%20Cyber%20Coari" class="btn btn-secundario">📱 Falar com suporte</a>
        
        <div class="footer">
            Cyber Coari - Sistemas de Pagamento<br>
            Em caso de dúvidas, entre em contato
        </div>
    </div>
    
    <script>
        // Efeito de confete
        function criarConfete() {
            const confete = document.getElementById('confete');
            const cores = ['#00a650', '#ff0000', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ff6600'];
            
            for (let i = 0; i < 150; i++) {
                const particula = document.createElement('div');
                particula.style.position = 'absolute';
                particula.style.width = Math.random() * 10 + 5 + 'px';
                particula.style.height = Math.random() * 10 + 5 + 'px';
                particula.style.backgroundColor = cores[Math.floor(Math.random() * cores.length)];
                particula.style.left = Math.random() * 100 + '%';
                particula.style.top = '-20px';
                particula.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                particula.style.opacity = Math.random() * 0.8 + 0.2;
                particula.style.animation = `cair ${Math.random() * 3 + 2}s linear forwards`;
                particula.style.zIndex = '9999';
                
                confete.appendChild(particula);
                
                setTimeout(() => {
                    particula.remove();
                }, 5000);
            }
        }
        
        // Adicionar animação de cair
        const style = document.createElement('style');
        style.textContent = `
            @keyframes cair {
                0% { transform: translateY(-20px) rotate(0deg); opacity: 1; }
                100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Executar confete
        criarConfete();
        
        // Salvar ID do pagamento no cookie
        document.cookie = "last_payment_id=<?php echo $payment_id; ?>; path=/";
    </script>
</body>
</html>