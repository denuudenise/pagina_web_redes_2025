<?php
session_start();

// Configuración de la base de datos
class Database {
    private $servidor = "localhost";
    private $usuario = "root";
    private $password = "";
    private $base_datos = "crumbel_cookies";
    private $conexion;

    public function __construct() {
        $this->conectar();
    }

    private function conectar() {
        try {
            $this->conexion = new mysqli($this->servidor, $this->usuario, $this->password, $this->base_datos);
            
            if ($this->conexion->connect_error) {
                throw new Exception("Error de conexión: " . $this->conexion->connect_error);
            }
            
            $this->conexion->set_charset("utf8");
        } catch (Exception $e) {
            die("Error al conectar con la base de datos: " . $e->getMessage());
        }
    }

    public function getConexion() {
        return $this->conexion;
    }

    public function cerrarConexion() {
        if ($this->conexion) {
            $this->conexion->close();
        }
    }
}

// Crear instancia de la base de datos
$db = new Database();
$conexion = $db->getConexion();

// Inicializar carrito en sesión si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Manejar acciones del carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'agregar':
            agregarProducto($conexion);
            break;
        case 'actualizar':
            actualizarCantidad();
            break;
        case 'eliminar':
            eliminarProducto();
            break;
        case 'confirmar':
            confirmarPedido($conexion);
            break;
    }
}

// Funciones del carrito
function agregarProducto($conexion) {
    if (isset($_POST['id_producto'])) {
        $id_producto = (int)$_POST['id_producto'];
        
        // Obtener información del producto
        $sql = "SELECT * FROM producto WHERE id_producto = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($producto = $resultado->fetch_assoc()) {
            // Verificar stock disponible
            if ($producto['stock'] > 0) {
                // Si ya existe en el carrito, aumentar cantidad
                if (isset($_SESSION['carrito'][$id_producto])) {
                    $_SESSION['carrito'][$id_producto]['cantidad']++;
                } else {
                    // Agregar nuevo producto al carrito
                    $_SESSION['carrito'][$id_producto] = [
                        'id_producto' => $producto['id_producto'],
                        'nombre' => $producto['nombre'],
                        'precio' => $producto['precio'],
                        'imagen_url' => $producto['imagen_url'],
                        'cantidad' => 1
                    ];
                }
                echo json_encode(['success' => true, 'message' => 'Producto agregado al carrito']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Producto sin stock']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        }
        $stmt->close();
    }
}

function actualizarCantidad() {
    if (isset($_POST['id_producto']) && isset($_POST['cantidad'])) {
        $id_producto = (int)$_POST['id_producto'];
        $cantidad = (int)$_POST['cantidad'];
        
        if ($cantidad > 0) {
            if (isset($_SESSION['carrito'][$id_producto])) {
                $_SESSION['carrito'][$id_producto]['cantidad'] = $cantidad;
                echo json_encode(['success' => true, 'message' => 'Cantidad actualizada']);
            }
        } else {
            eliminarProducto();
        }
    }
}

function eliminarProducto() {
    if (isset($_POST['id_producto'])) {
        $id_producto = (int)$_POST['id_producto'];
        unset($_SESSION['carrito'][$id_producto]);
        echo json_encode(['success' => true, 'message' => 'Producto eliminado del carrito']);
    }
}

function confirmarPedido($conexion) {
    // Verificar que hay un cliente logueado
    $id_cliente = $_SESSION['id_cliente'] ?? 1; // Por defecto cliente 1 si no hay login
    
    if (empty($_SESSION['carrito'])) {
        echo json_encode(['success' => false, 'message' => 'El carrito está vacío']);
        return;
    }
    
    try {
        $conexion->begin_transaction();
        
        // Calcular total
        $total = 0;
        foreach ($_SESSION['carrito'] as $item) {
            $total += $item['precio'] * $item['cantidad'];
        }
        
        // Insertar pedido
        $sql = "INSERT INTO pedido (id_cliente, fecha_pedido, metodo_pago, total) VALUES (?, NOW(), ?, ?)";
        $stmt = $conexion->prepare($sql);
        $metodo_pago = $_POST['metodo_pago'] ?? 'Efectivo';
        $stmt->bind_param("isd", $id_cliente, $metodo_pago, $total);
        $stmt->execute();
        
        $id_pedido = $conexion->insert_id;
        
        // Insertar detalles del pedido
        $sql_detalle = "INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, subtotal) VALUES (?, ?, ?, ?)";
        $stmt_detalle = $conexion->prepare($sql_detalle);
        
        foreach ($_SESSION['carrito'] as $item) {
            $subtotal = $item['precio'] * $item['cantidad'];
            $stmt_detalle->bind_param("iiid", $id_pedido, $item['id_producto'], $item['cantidad'], $subtotal);
            $stmt_detalle->execute();
            
            // Actualizar stock del producto
            $sql_stock = "UPDATE producto SET stock = stock - ? WHERE id_producto = ?";
            $stmt_stock = $conexion->prepare($sql_stock);
            $stmt_stock->bind_param("ii", $item['cantidad'], $item['id_producto']);
            $stmt_stock->execute();
            $stmt_stock->close();
        }
        
        $conexion->commit();
        
        // Limpiar carrito
        $_SESSION['carrito'] = [];
        
        echo json_encode(['success' => true, 'message' => 'Pedido confirmado exitosamente', 'id_pedido' => $id_pedido]);
        
        $stmt->close();
        $stmt_detalle->close();
        
    } catch (Exception $e) {
        $conexion->rollback();
        echo json_encode(['success' => false, 'message' => 'Error al procesar el pedido: ' . $e->getMessage()]);
    }
}

// Calcular totales del carrito
function calcularTotales() {
    $subtotal = 0;
    $cantidad_items = 0;
    
    foreach ($_SESSION['carrito'] as $item) {
        $subtotal += $item['precio'] * $item['cantidad'];
        $cantidad_items += $item['cantidad'];
    }
    
    $envio = $subtotal > 0 ? 5.00 : 0.00;
    $total = $subtotal + $envio;
    
    return [
        'subtotal' => $subtotal,
        'envio' => $envio,
        'total' => $total,
        'cantidad_items' => $cantidad_items
    ];
}

// Si es una petición AJAX, no mostrar HTML
if (isset($_POST['action'])) {
    exit;
}

$totales = calcularTotales();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛒 Carrito - Crumbel Cookies</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #ffc0cb 0%, #ffffff 100%);
            min-height: 100vh;
            color: #333;
        }

        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(255, 192, 203, 0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        h1 {
            color: #ff69b4;
            font-size: 2.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .back-btn {
            background: linear-gradient(45deg, #ff69b4, #ff1493);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 105, 180, 0.4);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(255, 105, 180, 0.6);
        }

        main {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .cart-title {
            text-align: center;
            color: #ff69b4;
            font-size: 3rem;
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from { text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1); }
            to { text-shadow: 2px 2px 4px rgba(255, 105, 180, 0.3); }
        }

        .cart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(255, 192, 203, 0.3);
            margin-bottom: 2rem;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto auto auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            background: rgba(255, 192, 203, 0.1);
            border-radius: 10px;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .item-info h3 {
            color: #ff69b4;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .item-price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f8f9fa;
            border-radius: 20px;
            padding: 0.25rem;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 50%;
            background: #ff69b4;
            color: white;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: #ff1493;
            transform: scale(1.1);
        }

        .quantity-display {
            min-width: 30px;
            text-align: center;
            font-weight: bold;
            font-size: 1rem;
        }

        .remove-btn {
            background: linear-gradient(45deg, #ff4d6d, #dc3545);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .remove-btn:hover {
            background: linear-gradient(45deg, #dc3545, #c82333);
            transform: translateY(-2px);
        }

        .cart-summary {
            background: linear-gradient(45deg, #ff69b4, #ff1493);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-top: 2rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .total-row {
            border-top: 2px solid rgba(255, 255, 255, 0.3);
            padding-top: 1rem;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .checkout-btn {
            width: 100%;
            background: white;
            color: #ff69b4;
            border: none;
            padding: 1rem 2rem;
            border-radius: 25px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .checkout-btn:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-cart h2 {
            color: #ff69b4;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .empty-cart p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .shop-btn {
            background: linear-gradient(45deg, #ff69b4, #ff1493);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 25px;
            text-decoration: none;
            font-size: 1.1rem;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .shop-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 105, 180, 0.4);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(45deg, #ff69b4, #ff1493);
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 20px rgba(255, 105, 180, 0.4);
        }

        .notification.show {
            transform: translateX(0);
        }

        @media (max-width: 768px) {
            .cart-item {
                grid-template-columns: 80px 1fr;
                gap: 0.5rem;
                text-align: center;
            }

            .item-price,
            .quantity-controls,
            .remove-btn {
                grid-column: 1 / -1;
                justify-self: center;
                margin-top: 0.5rem;
            }

            .cart-title { font-size: 2rem; }
            h1 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
    <header>
        <h1>🍪 Crumbel Cookies</h1>
        <a href="inicio.php" class="back-btn">← Volver a la Tienda</a>
    </header>

    <main>
        <h2 class="cart-title">🛒 Tu Carrito</h2>

        <?php if (!empty($_SESSION['carrito'])): ?>
        <!-- Carrito con productos -->
        <div id="cart-with-items">
            <div class="cart-container">
                <?php foreach ($_SESSION['carrito'] as $id_producto => $item): ?>
                <div class="cart-item" data-id="<?= $id_producto ?>">
                    <img src="<?= htmlspecialchars($item['imagen_url']) ?>" 
                         alt="<?= htmlspecialchars($item['nombre']) ?>" 
                         class="item-image">
                    <div class="item-info">
                        <h3><?= htmlspecialchars($item['nombre']) ?></h3>
                        <p>Precio unitario: $<?= number_format($item['precio'], 2) ?></p>
                    </div>
                    <div class="item-price">$<?= number_format($item['precio'] * $item['cantidad'], 2) ?></div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="updateQuantity(<?= $id_producto ?>, -1)">-</button>
                        <span class="quantity-display"><?= $item['cantidad'] ?></span>
                        <button class="quantity-btn" onclick="updateQuantity(<?= $id_producto ?>, 1)">+</button>
                    </div>
                    <button class="remove-btn" onclick="removeItem(<?= $id_producto ?>)">🗑️ Eliminar</button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <div class="summary-row">
                    <span>Subtotal (<?= $totales['cantidad_items'] ?> items):</span>
                    <span>$<?= number_format($totales['subtotal'], 2) ?></span>
                </div>
                <div class="summary-row">
                    <span>Envío:</span>
                    <span>$<?= number_format($totales['envio'], 2) ?></span>
                </div>
                <div class="summary-row total-row">
                    <span>Total:</span>
                    <span>$<?= number_format($totales['total'], 2) ?></span>
                </div>
                <button class="checkout-btn" onclick="proceedToCheckout()">
                    ✨ Confirmar Pedido
                </button>
            </div>
        </div>
        <?php else: ?>
        <!-- Carrito vacío -->
        <div class="cart-container empty-cart">
            <h2>Tu carrito está vacío 🛒</h2>
            <p>¡Agrega algunas deliciosas cookies para comenzar!</p>
            <a href="inicio.php" class="shop-btn">🍪 Explorar Productos</a>
        </div>
        <?php endif; ?>
    </main>

    <div class="notification" id="notification">
        <span id="notification-text"></span>
    </div>

    <script>
        function updateQuantity(idProducto, change) {
            const currentQty = parseInt(document.querySelector(`[data-id="${idProducto}"] .quantity-display`).textContent);
            const newQty = currentQty + change;
            
            if (newQty <= 0) {
                removeItem(idProducto);
                return;
            }
            
            fetch('carrito.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=actualizar&id_producto=${idProducto}&cantidad=${newQty}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Recargar para actualizar totales
                } else {
                    showNotification(data.message || 'Error al actualizar');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al actualizar cantidad');
            });
        }

        function removeItem(idProducto) {
            if (!confirm('¿Estás seguro de que quieres eliminar este producto?')) {
                return;
            }
            
            fetch('carrito.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=eliminar&id_producto=${idProducto}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showNotification(data.message || 'Error al eliminar');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al eliminar producto');
            });
        }

        function proceedToCheckout() {
            const metodoPago = prompt('Método de pago (Efectivo/Tarjeta):') || 'Efectivo';
            
            fetch('carrito.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=confirmar&metodo_pago=${metodoPago}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`¡Pedido confirmado exitosamente! ID del pedido: ${data.id_pedido}`);
                    location.reload();
                } else {
                    showNotification(data.message || 'Error al confirmar pedido');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al procesar el pedido');
            });
        }

        function showNotification(message) {
            const notification = document.getElementById('notification');
            const notificationText = document.getElementById('notification-text');
            
            notificationText.textContent = message;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>
<?php
$db->cerrarConexion();
?>