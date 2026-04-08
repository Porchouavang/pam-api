<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "bljrbfofoy3il4qe2xzz-mysql.services.clever-cloud.com";
$db   = "bljrbfofoy3il4qe2xzz";
$user = "ugqqj4x3q7fyu3xb";
$pass = "R6otGRP51OLWclgnOvYg";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    echo json_encode(["error" => "ເຊື່ອມຕໍ່ database ບໍ່ສຳເລັດ: " . $e->getMessage()]);
    exit();
}

// Auto-create tables
$pdo->exec("CREATE TABLE IF NOT EXISTS income (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS expense (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function respond($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

switch ($action) {
    case 'get_income':
        $month = $_GET['month'] ?? date('Y-m');
        $stmt = $pdo->prepare("SELECT * FROM income WHERE DATE_FORMAT(date,'%Y-%m') = ? ORDER BY date DESC");
        $stmt->execute([$month]);
        respond(["data" => $stmt->fetchAll()]);

    case 'add_income':
        $stmt = $pdo->prepare("INSERT INTO income (date, category, description, amount) VALUES (?,?,?,?)");
        $stmt->execute([$input['date'], $input['category'], $input['description'], $input['amount']]);
        respond(["success" => true, "id" => $pdo->lastInsertId()]);

    case 'update_income':
        $stmt = $pdo->prepare("UPDATE income SET date=?, category=?, description=?, amount=? WHERE id=?");
        $stmt->execute([$input['date'], $input['category'], $input['description'], $input['amount'], $input['id']]);
        respond(["success" => true]);

    case 'delete_income':
        $stmt = $pdo->prepare("DELETE FROM income WHERE id=?");
        $stmt->execute([$input['id']]);
        respond(["success" => true]);

    case 'get_expense':
        $month = $_GET['month'] ?? date('Y-m');
        $stmt = $pdo->prepare("SELECT * FROM expense WHERE DATE_FORMAT(date,'%Y-%m') = ? ORDER BY date DESC");
        $stmt->execute([$month]);
        respond(["data" => $stmt->fetchAll()]);

    case 'add_expense':
        $stmt = $pdo->prepare("INSERT INTO expense (date, category, description, amount) VALUES (?,?,?,?)");
        $stmt->execute([$input['date'], $input['category'], $input['description'], $input['amount']]);
        respond(["success" => true, "id" => $pdo->lastInsertId()]);

    case 'update_expense':
        $stmt = $pdo->prepare("UPDATE expense SET date=?, category=?, description=?, amount=? WHERE id=?");
        $stmt->execute([$input['date'], $input['category'], $input['description'], $input['amount'], $input['id']]);
        respond(["success" => true]);

    case 'delete_expense':
        $stmt = $pdo->prepare("DELETE FROM expense WHERE id=?");
        $stmt->execute([$input['id']]);
        respond(["success" => true]);

    case 'get_summary':
        $month = $_GET['month'] ?? date('Y-m');
        $si = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total FROM income WHERE DATE_FORMAT(date,'%Y-%m') = ?");
        $si->execute([$month]);
        $totalIncome = $si->fetchColumn();
        $se = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total FROM expense WHERE DATE_FORMAT(date,'%Y-%m') = ?");
        $se->execute([$month]);
        $totalExpense = $se->fetchColumn();
        $ci = $pdo->prepare("SELECT category, SUM(amount) as total FROM income WHERE DATE_FORMAT(date,'%Y-%m') = ? GROUP BY category ORDER BY total DESC");
        $ci->execute([$month]);
        $ce = $pdo->prepare("SELECT category, SUM(amount) as total FROM expense WHERE DATE_FORMAT(date,'%Y-%m') = ? GROUP BY category ORDER BY total DESC");
        $ce->execute([$month]);
        respond([
            "income" => (float)$totalIncome,
            "expense" => (float)$totalExpense,
            "balance" => (float)$totalIncome - (float)$totalExpense,
            "income_by_cat" => $ci->fetchAll(),
            "expense_by_cat" => $ce->fetchAll(),
        ]);

    default:
        respond(["error" => "action ບໍ່ຖືກຕ້ອງ"]);
}
// d