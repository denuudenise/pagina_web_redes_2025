<?php
session_start();

// Conexión a la base de datos
$conexion = new mysqli("localhost", "phpmyadmin", "RedesInformaticas", "crumbel_cookies");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$error = "";
$success = "";

// Procesar login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'login') {
        // Proceso de login
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono']);

        if (!empty($email) && !empty($telefono)) {
            // Buscar cliente en la BD usando email y teléfono
            $sql = "SELECT * FROM cliente WHERE email = ? AND telefono = ? LIMIT 1";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ss", $email, $telefono);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado->num_rows > 0) {
                $cliente = $resultado->fetch_assoc();

                // Guardar datos en la sesión
                $_SESSION['id_cliente'] = $cliente['id_cliente'];
                $_SESSION['nombre_cliente'] = $cliente['nombre'] . " " . $cliente['apellido'];
                $_SESSION['email_cliente'] = $cliente['email'];

                // Redirigir al inicio
                header("Location: inicio.php");
                exit();
            } else {
                $error = "⚠️ Email o Teléfono incorrectos.";
            }
            $stmt->close();
        } else {
            $error = "Por favor completa todos los campos.";
        }
    } 
    elseif (isset($_POST['action']) && $_POST['action'] == 'register') {
        // Proceso de registro
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']);
        $direccion = trim($_POST['direccion']);

        if (!empty($nombre) && !empty($apellido) && !empty($telefono) && !empty($email) && !empty($direccion)) {
            // Verificar si el email ya existe
            $sql_check = "SELECT id_cliente FROM cliente WHERE email = ? LIMIT 1";
            $stmt_check = $conexion->prepare($sql_check);
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $error = "Este email ya está registrado.";
            } else {
                // Insertar nuevo cliente
                $sql_insert = "INSERT INTO cliente (nombre, apellido, telefono, email, direccion) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conexion->prepare($sql_insert);
                $stmt_insert->bind_param("sssss", $nombre, $apellido, $telefono, $email, $direccion);
                
                if ($stmt_insert->execute()) {
                    // Auto-login después del registro
                    $_SESSION['id_cliente'] = $conexion->insert_id;
                    $_SESSION['nombre_cliente'] = $nombre . " " . $apellido;
                    $_SESSION['email_cliente'] = $email;
                    
                    header("Location: inicio.php");
                    exit();
                } else {
                    $error = "Error al registrar usuario.";
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        } else {
            $error = "Por favor completa todos los campos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Crumbel Cookies</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            height: 100vh;
            background: linear-gradient(rgba(255, 192, 203, 0.3), rgba(255, 192, 203, 0.3)),
                        url('https://graziamagazine.com/mx/wp-content/uploads/sites/13/2024/08/LARGE-Crumbl-cookies-28-e1723749738300.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        /* Overlay rosado */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 167, 221, 0.15), rgba(255, 105, 180, 0.9));
            z-index: 1;
        }

        .login-container {
            background: #c67eaa;
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 2;
            backdrop-filter: blur(10px);
        }

        h1 {
            color: white;
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 30px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .form-tabs {
            display: flex;
            margin-bottom: 20px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.1);
        }

        .tab-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: transparent;
            color: white;
            cursor: pointer;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: bold;
        }

        .tab-btn.active {
            background: white;
            color: #c67eaa;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: white;
            font-size: 1.1rem;
            margin-bottom: 8px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 12px 15px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            background: white;
            color: #333;
            outline: none;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: #2c1810;
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .login-btn:hover {
            background: #1a0f0a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.9);
            color: #c67eaa;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            z-index: 10;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: white;
            transform: translateY(-2px);
        }

        .error, .success {
            text-align: center;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .error {
            background: rgba(220, 53, 69, 0.9);
            color: white;
        }

        .success {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <a href="inicio.php" class="back-btn">← Volver a la tienda</a>
    
    <div class="login-container">
        <h1>Bienvenido</h1>
        
        <div class="form-tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Iniciar Sesión</button>
            <button class="tab-btn" onclick="switchTab('register')">Registrarse</button>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Formulario de Login -->
        <div id="login-form" class="form-section active">
            <form method="post">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="email_login">Email</label>
                    <input type="email" id="email_login" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="telefono_login">Teléfono</label>
                    <input type="tel" id="telefono_login" name="telefono" required>
                </div>
                
                <button type="submit" class="login-btn">Iniciar Sesión</button>
            </form>
        </div>

        <!-- Formulario de Registro -->
        <div id="register-form" class="form-section">
            <form method="post">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="apellido">Apellido</label>
                    <input type="text" id="apellido" name="apellido" required>
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="direccion">Dirección</label>
                    <input type="text" id="direccion" name="direccion" required>
                </div>
                
                <button type="submit" class="login-btn">Registrarse</button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Ocultar todos los formularios
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remover clase active de todos los botones
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostrar formulario seleccionado
            document.getElementById(tab + '-form').classList.add('active');
            
            // Activar botón seleccionado
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
<?php
$conexion->close();
?>