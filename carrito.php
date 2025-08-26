<?php
session_start();

// Conexión a la BD
$conexion = new mysqli("localhost", "root", "", "crumbel_cookies");
if ($conexion->connect_error) {
    die("Error en la conexión: " . $conexion->connect_error);
}

// Inicializar carrito en sesión si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Eliminar producto del carrito
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    unset($_SESSION['carrito'][$id]);
}

// Confirmar compra
if (isset($_POST['confirmar'])) {
    $id_cliente = 1; // 👉 en el futuro se reemplaza con el login real
    $total = 0;

    foreach ($_SESSION['carrito'] as $item) {
        $total += $item['precio'] * $item['cantidad'];
    }

    // Insertar pedido
    $conexion->query("INSERT INTO pedido (id_cliente, metodo_pago, total) VALUES ($id_cliente, 'Efectivo', $total)");
    $id_pedido = $conexion->insert_id;

    // Insertar detalle del pedido
    foreach ($_SESSION['carrito'] as $id_producto => $item) {
        $cantidad = $item['cantidad'];
        $subtotal = $item['precio'] * $cantidad;
        $conexion->query("INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, subtotal) 
                          VALUES ($id_pedido, $id_producto, $cantidad, $subtotal)");
    }

    // Vaciar carrito
    $_SESSION['carrito'] = [];
    echo "<script>alert('¡Pedido confirmado con éxito!'); window.location='carrito.php';</script>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito - Crumbel Cookies</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #ffeaf3;
            padding: 20px;
        }
        h1 {
            color: #ff69b4;
            text-align: center;
        }
        table {
            width: 80%;
            margin: 20px auto;
            border-collapse: collapse;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        th, td {
            padding: 15px;
            text-align: center;
        }
        th {
            background: #ff69b4;
            color: white;
        }
        tr:nth-child(even) {
            background: #fdf2f8;
        }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        .btn-eliminar {
            background: #ff4d6d;
            color: white;
        }
        .btn-confirmar {
            display: block;
            margin: 20px auto;
            background: #ff69b4;
            color: white;
            font-size: 1.2rem;
            padding: 12px 20px;
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <h1>🛒 Tu Carrito</h1>
    <table>
        <tr>
            <th>Producto</th>
            <th>Precio</th>
            <th>Cantidad</th>
            <th>Subtotal</th>
            <th>Acción</th>
        </tr>
        <?php
        $total = 0;
        foreach ($_SESSION['carrito'] as $id => $item):
            $subtotal = $item['precio'] * $item['cantidad'];
            $total += $subtotal;
        ?>
        <tr>
            <td><?= $item['nombre'] ?></td>
            <td>$<?= number_format($item['precio'], 2) ?></td>
            <td><?= $item['cantidad'] ?></td>
            <td>$<?= number_format($subtotal, 2) ?></td>
            <td><a href="carrito.php?eliminar=<?= $id ?>" class="btn btn-eliminar">❌ Eliminar</a></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <th colspan="3">TOTAL</th>
            <th colspan="2">$<?= number_format($total, 2) ?></th>
        </tr>
    </table>

    <?php if ($total > 0): ?>
        <form method="post">
            <button type="submit" name="confirmar" class="btn btn-confirmar">✅ Confirmar Pedido</button>
        </form>
    <?php else: ?>
        <p style="text-align:center; color:#555;">Tu carrito está vacío 🛍️</p>
    <?php endif; ?>
</body>
</html>
