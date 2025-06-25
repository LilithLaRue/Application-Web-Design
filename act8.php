<?php

$host = '127.0.0.1'; 
$user = 'root';       
$pass = '';           // nel sin contra para que no me doxxee
$dbname = 'superheroes_db';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . PHP_EOL);
}


$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);


$table = "CREATE TABLE IF NOT EXISTS superheroes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    real_name VARCHAR(255) NOT NULL,
    superhero_name VARCHAR(255) NOT NULL,
    photo_url VARCHAR(255) NOT NULL,
    additional_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table);


function prompt($prompt) {
    echo $prompt;
    return trim(fgets(STDIN));
}

function listSuperheroes($conn) {
    $result = $conn->query("SELECT * FROM superheroes");
    if ($result->num_rows === 0) {
        echo "No superheroes found." . PHP_EOL;
        return;
    }
    echo "Superheroes:" . PHP_EOL;
    while ($row = $result->fetch_assoc()) {
        echo "[{$row['id']}] {$row['superhero_name']} (Real name: {$row['real_name']})" . PHP_EOL;
    }
}

while (true) {
    echo PHP_EOL . "Superheroes CLI CRUD" . PHP_EOL;
    echo "1. List superheroes" . PHP_EOL;
    echo "2. Add superhero" . PHP_EOL;
    echo "3. View superhero details" . PHP_EOL;
    echo "4. Edit superhero" . PHP_EOL;
    echo "5. Delete superhero" . PHP_EOL;
    echo "6. Exit" . PHP_EOL;

    $choice = prompt("Choose an option: ");

    switch ($choice) {
        case 1:
            listSuperheroes($conn);
            break;

        case 2:
            $real_name = prompt("Enter real name: ");
            $superhero_name = prompt("Enter superhero name: ");
            $photo_url = prompt("Enter photo URL: ");
            $additional_info = prompt("Enter additional info (optional): ");

            $stmt = $conn->prepare("INSERT INTO superheroes (real_name, superhero_name, photo_url, additional_info) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $real_name, $superhero_name, $photo_url, $additional_info);
            if ($stmt->execute()) {
                echo "Superhero added successfully." . PHP_EOL;
            } else {
                echo "Error adding superhero: " . $stmt->error . PHP_EOL;
            }
            $stmt->close();
            break;

        case 3:
            $id = prompt("Enter superhero ID: ");
            $stmt = $conn->prepare("SELECT * FROM superheroes WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                echo "Superhero not found." . PHP_EOL;
            } else {
                $hero = $res->fetch_assoc();
                echo "ID: {$hero['id']}" . PHP_EOL;
                echo "Real Name: {$hero['real_name']}" . PHP_EOL;
                echo "Superhero Name: {$hero['superhero_name']}" . PHP_EOL;
                echo "Photo URL: {$hero['photo_url']}" . PHP_EOL;
                echo "Additional Info: {$hero['additional_info']}" . PHP_EOL;
                echo "Created At: {$hero['created_at']}" . PHP_EOL;
            }
            $stmt->close();
            break;

        case 4:
            $id = prompt("Enter superhero ID to edit: ");
            $stmt = $conn->prepare("SELECT * FROM superheroes WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                echo "Superhero not found." . PHP_EOL;
                $stmt->close();
                break;
            }
            $hero = $res->fetch_assoc();
            $stmt->close();

            $real_name = prompt("Enter real name [{$hero['real_name']}]: ");
            $superhero_name = prompt("Enter superhero name [{$hero['superhero_name']}]: ");
            $photo_url = prompt("Enter photo URL [{$hero['photo_url']}]: ");
            $additional_info = prompt("Enter additional info [{$hero['additional_info']}]: ");

            // Use old value if input empty
            $real_name = $real_name ?: $hero['real_name'];
            $superhero_name = $superhero_name ?: $hero['superhero_name'];
            $photo_url = $photo_url ?: $hero['photo_url'];
            $additional_info = $additional_info ?: $hero['additional_info'];

            $stmt = $conn->prepare("UPDATE superheroes SET real_name = ?, superhero_name = ?, photo_url = ?, additional_info = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $real_name, $superhero_name, $photo_url, $additional_info, $id);
            if ($stmt->execute()) {
                echo "Superhero updated successfully." . PHP_EOL;
            } else {
                echo "Error updating superhero: " . $stmt->error . PHP_EOL;
            }
            $stmt->close();
            break;

        case 5:
            $id = prompt("Enter superhero ID to delete: ");
            $stmt = $conn->prepare("DELETE FROM superheroes WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo "Superhero deleted successfully." . PHP_EOL;
            } else {
                echo "Error deleting superhero: " . $stmt->error . PHP_EOL;
            }
            $stmt->close();
            break;

        case 6:
            echo "Goodbye!" . PHP_EOL;
            $conn->close();
            exit;

        default:
            echo "Invalid option. Try again." . PHP_EOL;
    }
}
