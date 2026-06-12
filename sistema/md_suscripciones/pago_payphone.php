<?php
require('../../md_autenticacion/sesion.php');
require('../../md_config/conexion.php');

$suscripcion_id = $_GET['suscripcion_id'];
$monto = $_GET['monto'];

// Obtener información de la suscripción
$sql = "SELECT s.*, e.nombre_comercial 
        FROM suscripciones s 
        JOIN empresa e ON s.empresa_id = e.id 
        WHERE s.id = ? AND s.usuario_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$suscripcion_id, $_SESSION['id']]);
$suscripcion = $stmt->fetch();

if (!$suscripcion) {
    die("Suscripción no encontrada");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pago con PayPhone</title>
    <script src="https://pay.payphonetodoesposible.com/api/button/js?appId=TU_APP_ID"></script>
</head>
<body>
    <h2>Pago de Suscripción: <?= $suscripcion['nombre_comercial'] ?></h2>
    <p>Monto a pagar: $<?= number_format($monto, 2) ?></p>
    
    <div id="payphone-button"></div>

    <script>
    payphone.Button({
        token: "TU_TOKEN_PAYPHONE",
        btnHorizontal: true,
        btnCard: true,
        createOrder: function(actions) {
            return actions.prepare({
                amount: Math.round(<?= $monto ?> * 100),
                amountWithoutTax: Math.round(<?= $monto ?> * 100),
                currency: "USD",
                clientTransactionId: "suscripcion-<?= $suscripcion_id ?>",
                lang: "es"
            });
        },
        onComplete: function(model, actions) {
            if (model.transactionStatus === "Approved") {
                // Actualizar suscripción como pagada
                fetch('procesar_payphone.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        suscripcion_id: <?= $suscripcion_id ?>,
                        transaccion_id: model.transactionId,
                        monto: <?= $monto ?>
                    })
                }).then(response => {
                    window.location.href = '../md_suscripciones/suscripciones_lista.php?pago_exitoso=true';
                });
            }
        }
    }).render("#payphone-button");
    </script>
</body>
</html>