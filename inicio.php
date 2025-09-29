<?php
session_start();

// Conexión BD
$conexion = new mysqli("localhost", "phpmyadmin", "RedesInformaticas", "crumbel_cookies");
if ($conexion->connect_error) {
    die("Error en la conexión: " . $conexion->connect_error);
}

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Agregar producto al carrito
if (isset($_POST['id_producto'])) {
    $id_producto = intval($_POST['id_producto']);

    // Buscar producto en la BD
    $sql = "SELECT * FROM producto WHERE id_producto = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $producto = $resultado->fetch_assoc();

    if ($producto) {
        // Si ya existe en carrito, sumar cantidad
        if (isset($_SESSION['carrito'][$id_producto])) {
            $_SESSION['carrito'][$id_producto]['cantidad']++;
        } else {
            $_SESSION['carrito'][$id_producto] = [
                "id_producto" => $producto['id_producto'],
                "nombre" => $producto['nombre'],
                "precio" => $producto['precio'],
                "imagen_url" => $producto['imagen_url'],
                "cantidad" => 1
            ];
        }
        echo "<script>alert('{$producto['nombre']} agregado al carrito 🛒');</script>";
    }
    $stmt->close();
}

// Cerrar sesión si se solicita
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: inicio.php");
    exit();
}

// Contador carrito
$cartCount = 0;
foreach ($_SESSION['carrito'] as $item) {
    $cartCount += $item['cantidad'];
}

// Verificar si hay sesión activa
$usuarioLogueado = isset($_SESSION['id_cliente']) && isset($_SESSION['nombre_cliente']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🍪 Crumbel Cookies</title>
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
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            }
            to {
                text-shadow: 2px 2px 4px rgba(255, 105, 180, 0.3);
            }
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: linear-gradient(45deg, #ff69b4, #ff1493);
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(255, 105, 180, 0.4);
        }

        .user-name {
            color: white;
            font-weight: bold;
            font-size: 1rem;
        }

        .logout-btn {
            background: white;
            color: #ff69b4;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }

        .login-btn {
            background: linear-gradient(45deg, #ff69b4, #ff1493);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 105, 180, 0.4);
            text-decoration: none;
            display: inline-block;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(255, 105, 180, 0.6);
        }

        .cart-container {
            position: relative;
        }

        #carrito {
            width: 40px;
            height: 40px;
            cursor: pointer;
            transition: transform 0.3s ease;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        #carrito:hover {
            transform: scale(1.1) rotate(5deg);
        }

        .cart-counter {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff1493;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .products-section {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            color: #ff69b4;
            margin-bottom: 3rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .cookie-showcase {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .cookie-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(255, 192, 203, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .cookie-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(255, 192, 203, 0.4);
        }

        .cookie-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 15px;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .cookie-card:hover .cookie-image {
            transform: scale(1.05);
        }

        .cookie-name {
            font-size: 1.5rem;
            color: #ff69b4;
            margin-bottom: 0.5rem;
        }

        .cookie-price {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .add-to-cart {
            width: 100%;
            background: linear-gradient(45deg, #ff69b4, #ff1493);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 15px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-to-cart:hover {
            background: linear-gradient(45deg, #ff1493, #dc143c);
            transform: translateY(-2px);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
            padding: 0 2rem;
            max-width: 1200px;
            margin: 3rem auto;
        }

        .feature {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(255, 192, 203, 0.2);
            transition: transform 0.3s ease;
        }

        .feature:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-title {
            color: #ff69b4;
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .hero-section {
                height: 40vh;
            }
        
            h1 {
                font-size: 2rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .header-right {
                gap: 1rem;
            }

            .user-info {
                padding: 0.5rem 1rem;
            }

            .user-name {
                font-size: 0.9rem;
            }
            
            .products-section {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>🍪 Crumbel Cookies</h1>
        <div class="header-right">
            <?php if ($usuarioLogueado): ?>
                <!-- Usuario logueado -->
                <div class="user-info">
                    <span class="user-name">👤 <?= htmlspecialchars($_SESSION['nombre_cliente']) ?></span>
                    <a href="?logout=1" class="logout-btn">Cerrar Sesión</a>
                </div>
            <?php else: ?>
                <!-- No hay sesión -->
                <a href="login.php" class="login-btn">Login</a>
            <?php endif; ?>
            
            <div class="cart-container">
                <a href="carrito.php">
                    <img src="https://cdn-icons-png.flaticon.com/512/5412/5412512.png" id="carrito">
                </a>
                <span class="cart-counter"><?= $cartCount ?></span>
            </div>
        </div>
    </header>

    <main>
        <div class="products-section">
            <h2 class="section-title">Nuestros Productos</h2>
            <div class="cookie-showcase">
                <?php
                $productos = $conexion->query("SELECT * FROM producto WHERE stock > 0");
                if ($productos && $productos->num_rows > 0):
                    while ($row = $productos->fetch_assoc()):
                ?>
                <div class="cookie-card">
                    <img src="<?= htmlspecialchars($row['imagen_url']) ?>" alt="<?= htmlspecialchars($row['nombre']) ?>" class="cookie-image">
                    <h3 class="cookie-name"><?= htmlspecialchars($row['nombre']) ?></h3>
                    <p class="cookie-price">$<?= number_format($row['precio'], 2) ?></p>
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">Stock: <?= $row['stock'] ?> disponibles</p>
                    <form method="post" style="margin: 0;">
                        <input type="hidden" name="id_producto" value="<?= $row['id_producto'] ?>">
                        <button type="submit" class="add-to-cart">Agregar al Carrito</button>
                    </form>
                </div>
                <?php 
                    endwhile;
                else:
                ?>
                <div style="grid-column: 1 / -1; text-align: center; color: #666;">
                    <h3>No hay productos disponibles</h3>
                    <p>Por favor, vuelve más tarde.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer style="background: #ff69b4; color: white; text-align: center; padding: 2rem; margin-top: 3rem;">
        <p>&copy; 2025 Crumbel Cookies. Todos los derechos reservados.</p>
        <p>¡Síguenos en nuestras redes sociales para ofertas especiales!</p>
    </footer>
</body>
</html>
<?php
$conexion->close();
?>