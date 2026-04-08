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
    payment_type VARCHAR(20) NOT NULL DEFAULT 'ຈ່າຍສົດ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS expense (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(15,2) NOT NULL,
    payment_type VARCHAR(20) NOT NULL DEFAULT 'ຈ່າຍສົດ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Migration: add payment_type if not exists
try { $pdo->exec("ALTER TABLE income ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'ຈ່າຍສົດ'"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE expense ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'ຈ່າຍສົດ'"); } catch(Exception $e) {}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

function respond($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function buildWhere($params) {
    $where = []; $vals = [];
    if (!empty($params['date_from']) && !empty($params['date_to'])) {
        $where[] = "date BETWEEN ? AND ?";
        $vals[] = $params['date_from'];
        $vals[] = $params['date_to'];
    } elseif (!empty($params['month'])) {
        $where[] = "DATE_FORMAT(date,'%Y-%m') = ?";
        $vals[] = $params['month'];
    }
    if (!empty($params['payment_type'])) {
        $where[] = "payment_type = ?";
        $vals[] = $params['payment_type'];
    }
    return [$where, $vals];
}

switch ($action) {

    case 'get_income':
        [$where, $vals] = buildWhere($_GET);
        $w = $where ? " WHERE ".implode(" AND ",$where) : "";
        $stmt = $pdo->prepare("SELECT * FROM income$w ORDER BY date DESC");
        $stmt->execute($vals);
        respond(["data" => $stmt->fetchAll()]);

    case 'add_income':
        $stmt = $pdo->prepare("INSERT INTO income (date,category,description,amount,payment_type) VALUES (?,?,?,?,?)");
        $stmt->execute([$input['date'],$input['category'],$input['description'],$input['amount'],$input['payment_type']??'ຈ່າຍສົດ']);
        respond(["success" => true, "id" => $pdo->lastInsertId()]);

    case 'update_income':
        $stmt = $pdo->prepare("UPDATE income SET date=?,category=?,description=?,amount=?,payment_type=? WHERE id=?");
        $stmt->execute([$input['date'],$input['category'],$input['description'],$input['amount'],$input['payment_type']??'ຈ່າຍສົດ',$input['id']]);
        respond(["success" => true]);

    case 'delete_income':
        $stmt = $pdo->prepare("DELETE FROM income WHERE id=?");
        $stmt->execute([$input['id']]);
        respond(["success" => true]);

    case 'get_expense':
        [$where, $vals] = buildWhere($_GET);
        $w = $where ? " WHERE ".implode(" AND ",$where) : "";
        $stmt = $pdo->prepare("SELECT * FROM expense$w ORDER BY date DESC");
        $stmt->execute($vals);
        respond(["data" => $stmt->fetchAll()]);

    case 'add_expense':
        $stmt = $pdo->prepare("INSERT INTO expense (date,category,description,amount,payment_type) VALUES (?,?,?,?,?)");
        $stmt->execute([$input['date'],$input['category'],$input['description'],$input['amount'],$input['payment_type']??'ຈ່າຍສົດ']);
        respond(["success" => true, "id" => $pdo->lastInsertId()]);

    case 'update_expense':
        $stmt = $pdo->prepare("UPDATE expense SET date=?,category=?,description=?,amount=?,payment_type=? WHERE id=?");
        $stmt->execute([$input['date'],$input['category'],$input['description'],$input['amount'],$input['payment_type']??'ຈ່າຍສົດ',$input['id']]);
        respond(["success" => true]);

    case 'delete_expense':
        $stmt = $pdo->prepare("DELETE FROM expense WHERE id=?");
        $stmt->execute([$input['id']]);
        respond(["success" => true]);

    case 'get_summary':
        [$where, $vals] = buildWhere($_GET);
        $w = $where ? " WHERE ".implode(" AND ",$where) : "";

        $si = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM income$w"); $si->execute($vals);
        $totalIncome = $si->fetchColumn();

        $se = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expense$w"); $se->execute($vals);
        $totalExpense = $se->fetchColumn();

        $ci = $pdo->prepare("SELECT category, SUM(amount) as total FROM income$w GROUP BY category ORDER BY total DESC"); $ci->execute($vals);
        $ce = $pdo->prepare("SELECT category, SUM(amount) as total FROM expense$w GROUP BY category ORDER BY total DESC"); $ce->execute($vals);
        $pi = $pdo->prepare("SELECT payment_type, SUM(amount) as total FROM income$w GROUP BY payment_type"); $pi->execute($vals);
        $pe = $pdo->prepare("SELECT payment_type, SUM(amount) as total FROM expense$w GROUP BY payment_type"); $pe->execute($vals);

        respond([
            "income"         => (float)$totalIncome,
            "expense"        => (float)$totalExpense,
            "balance"        => (float)$totalIncome - (float)$totalExpense,
            "income_by_cat"  => $ci->fetchAll(),
            "expense_by_cat" => $ce->fetchAll(),
            "income_by_pay"  => $pi->fetchAll(),
            "expense_by_pay" => $pe->fetchAll(),
        ]);

    default:
        respond(["error" => "action ບໍ່ຖືກຕ້ອງ"]);
}