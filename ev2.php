<?php

session_start();

// Fake DB using SQLite for simplicity cause im not a masoquist
$db = new PDO('sqlite:orders.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    password TEXT,
    role TEXT,
    active INTEGER DEFAULT 1
)");

$db->exec("CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number TEXT,
    customer_name TEXT,
    customer_number TEXT,
    fiscal_data TEXT,
    datetime TEXT,
    delivery_address TEXT,
    notes TEXT,
    status TEXT DEFAULT 'Ordered',
    photo_in_route TEXT,
    photo_delivered TEXT,
    deleted INTEGER DEFAULT 0
)");


function seed($db) {
    $check = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($check == 0) {
        //AQUI LAS CREDENCIALES CONSTE NO SIRVE SI NO SON ESTAS
        $db->exec("INSERT INTO users (name, email, password, role) VALUES
            ('Admin', 'admin@example.com', 'admin', 'Admin'),
            ('Seller', 'sales@example.com', '1234', 'Sales'),
            ('Purchaser', 'purchase@example.com', '1234', 'Purchasing'),
            ('Warehouse', 'warehouse@example.com', '1234', 'Warehouse'),
            ('RouteGuy', 'route@example.com', '1234', 'Route')
        ");
        $db->exec("INSERT INTO orders (invoice_number, customer_name, customer_number, fiscal_data, datetime, delivery_address, notes)
            VALUES ('INV001', 'Acme Corp', 'CUST001', 'RFC123', datetime('now'), '123 Road St.', 'Leave at front')
        ");
    }
}
seed($db);


$action = $_GET['action'] ?? 'home';
$method = $_SERVER['REQUEST_METHOD'];


function isLoggedIn() {
    return isset($_SESSION['user']);
}
function requireLogin() {
    if (!isLoggedIn()) {
        echo "<p><a href='?action=login'>Please log in</a></p>";
        exit;
    }
}


if ($action === 'home') {
    echo "<h1>Track Your Order</h1>
        <form method='GET'>
            <input type='hidden' name='action' value='track'>
            Customer #: <input name='customer_number'> 
            Invoice #: <input name='invoice_number'>
            <button>Search</button>
        </form>
        <p><a href='?action=login'>Login</a></p>";
}
elseif ($action === 'track') {
    $cust = $_GET['customer_number'] ?? '';
    $inv = $_GET['invoice_number'] ?? '';
    $stmt = $db->prepare("SELECT * FROM orders WHERE customer_number=? AND invoice_number=? AND deleted=0");
    $stmt->execute([$cust, $inv]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        echo "Order not found.";
    } else {
        echo "<h2>Status: {$order['status']}</h2>";
        if ($order['status'] === 'Delivered') {
            echo "<img src='{$order['photo_delivered']}' height='200'><br>";
        }
        if ($order['status'] === 'In process') {
            echo "<p>Processing since: {$order['datetime']}</p>";
        }
    }
    echo "<p><a href='?action=home'>Back</a></p>";
}
elseif ($action === 'login') {
    if ($method === 'POST') {
        $email = $_POST['email'];
        $pass = $_POST['password'];
        $stmt = $db->prepare("SELECT * FROM users WHERE email=? AND password=? AND active=1");
        $stmt->execute([$email, $pass]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user'] = $user;
            header("Location: ?action=dashboard");
        } else echo "Invalid credentials.";
    } else {
        echo "<form method='POST'>
            Email: <input name='email'><br>
            Password: <input type='password' name='password'><br>
            <button>Login</button>
        </form>";
    }
}
elseif ($action === 'dashboard') {
    requireLogin();
    echo "<h1>Dashboard</h1>";
    echo "<ul>
        <li><a href='?action=users'>Users</a></li>
        <li><a href='?action=orders'>Orders</a></li>
        <li><a href='?action=deleted_orders'>Archived Orders</a></li>
        <li><a href='?action=logout'>Logout</a></li>
    </ul>";
}
elseif ($action === 'logout') {
    session_destroy();
    header("Location: ?action=home");
}
elseif ($action === 'users') {
    requireLogin();
    $users = $db->query("SELECT * FROM users")->fetchAll();
    echo "<h2>User List</h2><a href='?action=create_user'>Create New</a><ul>";
    foreach ($users as $u) {
        $status = $u['active'] ? 'Active' : 'Inactive';
        echo "<li>{$u['name']} ({$u['role']}) - {$status} - <a href='?action=edit_user&id={$u['id']}'>Edit</a></li>";
    }
    echo "</ul><a href='?action=dashboard'>Back</a>";
}
elseif ($action === 'create_user') {
    requireLogin();
    if ($method === 'POST') {
        $db->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
           ->execute([$_POST['name'], $_POST['email'], $_POST['password'], $_POST['role']]);
        header("Location: ?action=users");
    } else {
        echo "<form method='POST'>
            Name: <input name='name'><br>
            Email: <input name='email'><br>
            Password: <input name='password'><br>
            Role: <select name='role'><option>Sales</option><option>Purchasing</option><option>Warehouse</option><option>Route</option></select><br>
            <button>Create</button>
        </form>";
    }
}
elseif ($action === 'edit_user') {
    requireLogin();
    $id = $_GET['id'];
    $user = $db->query("SELECT * FROM users WHERE id=$id")->fetch();
    if ($method === 'POST') {
        $db->prepare("UPDATE users SET name=?, email=?, role=?, active=? WHERE id=?")
           ->execute([$_POST['name'], $_POST['email'], $_POST['role'], $_POST['active'], $id]);
        header("Location: ?action=users");
    } else {
        echo "<form method='POST'>
            Name: <input name='name' value='{$user['name']}'><br>
            Email: <input name='email' value='{$user['email']}'><br>
            Role: <input name='role' value='{$user['role']}'><br>
            Active: <select name='active'><option value='1'>Yes</option><option value='0'>No</option></select><br>
            <button>Update</button>
        </form>";
    }
}
elseif ($action === 'orders') {
    requireLogin();
    $orders = $db->query("SELECT * FROM orders WHERE deleted=0 ORDER BY id DESC")->fetchAll();
    echo "<h2>Orders</h2><a href='?action=create_order'>Create New</a><ul>";
    foreach ($orders as $o) {
        echo "<li>{$o['invoice_number']} - {$o['customer_name']} - <a href='?action=view_order&id={$o['id']}'>View</a></li>";
    }
    echo "</ul><a href='?action=dashboard'>Back</a>";
}
elseif ($action === 'create_order') {
    requireLogin();
    if ($method === 'POST') {
        $db->prepare("INSERT INTO orders (invoice_number, customer_name, customer_number, fiscal_data, datetime, delivery_address, notes)
            VALUES (?,?,?,?,datetime('now'),?,?)")
           ->execute([
               $_POST['invoice_number'], $_POST['customer_name'], $_POST['customer_number'],
               $_POST['fiscal_data'], $_POST['delivery_address'], $_POST['notes']
           ]);
        header("Location: ?action=orders");
    } else {
        echo "<form method='POST'>
            Invoice #: <input name='invoice_number'><br>
            Customer Name: <input name='customer_name'><br>
            Customer #: <input name='customer_number'><br>
            Fiscal Data: <input name='fiscal_data'><br>
            Delivery Address: <input name='delivery_address'><br>
            Notes: <input name='notes'><br>
            <button>Create</button>
        </form>";
    }
}
elseif ($action === 'view_order') {
    requireLogin();
    $id = $_GET['id'];
    $order = $db->query("SELECT * FROM orders WHERE id=$id")->fetch();
    if ($method === 'POST') {
        if (isset($_POST['status'])) {
            $status = $_POST['status'];
            if ($status === 'In route' && $_FILES['photo']) {
                $photo = 'uploads/inroute_' . basename($_FILES['photo']['name']);
                move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
                $db->exec("UPDATE orders SET status='$status', photo_in_route='$photo' WHERE id=$id");
            } elseif ($status === 'Delivered' && $_FILES['photo']) {
                $photo = 'uploads/delivered_' . basename($_FILES['photo']['name']);
                move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
                $db->exec("UPDATE orders SET status='$status', photo_delivered='$photo' WHERE id=$id");
            } else {
                $db->exec("UPDATE orders SET status='$status' WHERE id=$id");
            }
        }
        if (isset($_POST['delete'])) {
            $db->exec("UPDATE orders SET deleted=1 WHERE id=$id");
        }
        header("Location: ?action=orders");
    } else {
        echo "<h2>Order: {$order['invoice_number']}</h2>
            <p>Status: {$order['status']}</p>
            <form method='POST' enctype='multipart/form-data'>
                Change status: 
                <select name='status'>
                    <option>Ordered</option>
                    <option>In process</option>
                    <option>In route</option>
                    <option>Delivered</option>
                </select><br>
                Upload photo (optional): <input type='file' name='photo'><br>
                <button>Update</button>
            </form>
            <form method='POST'><button name='delete' value='1'>Archive</button></form>
            <a href='?action=orders'>Back</a>";
    }
}
elseif ($action === 'deleted_orders') {
    requireLogin();
    $orders = $db->query("SELECT * FROM orders WHERE deleted=1")->fetchAll();
    echo "<h2>Archived Orders</h2><ul>";
    foreach ($orders as $o) {
        echo "<li>{$o['invoice_number']} - <a href='?action=restore_order&id={$o['id']}'>Restore</a></li>";
    }
    echo "</ul><a href='?action=dashboard'>Back</a>";
}
elseif ($action === 'restore_order') {
    requireLogin();
    $id = $_GET['id'];
    $db->exec("UPDATE orders SET deleted=0 WHERE id=$id");
    header("Location: ?action=deleted_orders");
}
?>
