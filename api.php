<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==============================
// Database Connection
// ==============================
$host = getenv('DB_HOST') ?: 'bcnwmnxodqnmfeynxlpi-mysql.services.clever-cloud.com';
$db   = getenv('DB_NAME') ?: 'bcnwmnxodqnmfeynxlpi';
$user = getenv('DB_USER') ?: 'u0ddxmv9ujlin1zm';
$pass = getenv('DB_PASSWORD') ?: 'z2vBYwhqTTEfh1lXpnMQ';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    respond(["error" => "Database connection failed: " . $e->getMessage()], 500);
}

// ==============================
// Helpers
// ==============================
function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function getInput() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function requireFields($data, $fields) {
    foreach ($fields as $f) {
        if (!isset($data[$f]) || trim((string)$data[$f]) === '') {
            respond(['error' => 'All fields are required'], 400);
        }
    }
}

// ==============================
// Router
// ==============================
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');

// Extract path segments: /api/resource or /api/resource/id
$segments = explode('/', ltrim($uri, '/'));
// segments[0] = "api", segments[1] = resource, segments[2] = id (optional)
$resource = $segments[1] ?? '';
$id       = $segments[2] ?? null;

// ==============================
// POST /login
// ==============================
if ($method === 'POST' && $resource === 'login') {
    $input = getInput();
    if (empty($input['phone']) || empty($input['password'])) {
        respond(['error' => true, 'message' => 'Please provide phone and password'], 400);
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
    $stmt->execute([$input['phone']]);
    $user = $stmt->fetch();
    if ($user && password_verify($input['password'], $user['password'])) {
        respond(['error' => false, 'message' => 'Login successfully', 'user' => $user]);
    } else {
        respond(['error' => true, 'message' => 'phone or password incorrect'], 401);
    }
}

// ==============================
// POST /api/create-admin
// ==============================
if ($method === 'POST' && $resource === 'api' && ($id ?? '') === 'create-admin') {
    $input = getInput();
    requireFields($input, ['name', 'email', 'password', 'phone']);
    $hashed = password_hash($input['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$input['name'], $input['email'], $hashed, $input['phone']]);
    respond(['message' => 'Admin added successfully', 'userId' => $pdo->lastInsertId()], 201);
}

// ==============================
// POST /api/create-daily
// ==============================
if ($method === 'POST' && $resource === 'api' && ($id ?? '') === 'create-daily') {
    $input = getInput();
    $fields = ['five_six','six_seven','seven_eight','eight_nine','nine_ten','ten_eleven',
               'eleven_twelve','twelve_thirteen','thirteen_fourteen','fourteen_fifteen',
               'fifteen_sixteen','sixteen_seventeen','seventeen_eighteen','eighteen_nineteen',
               'nineteen_twenty','twenty_twentyone','twentyone_twentytwo','twentytwo_twentythree',
               'twentythree_five','days'];
    requireFields($input, $fields);
    $stmt = $pdo->prepare("INSERT INTO daily (five_six,six_seven,seven_eight,eight_nine,nine_ten,ten_eleven,
        eleven_twelve,twelve_thirteen,thirteen_fourteen,fourteen_fifteen,fifteen_sixteen,sixteen_seventeen,
        seventeen_eighteen,eighteen_nineteen,nineteen_twenty,twenty_twentyone,twentyone_twentytwo,
        twentytwo_twentythree,twentythree_five,days,timestamp)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    $vals = array_map(fn($f) => $input[$f], $fields);
    $stmt->execute($vals);
    respond(['message' => 'Daily added successfully', 'userId' => $pdo->lastInsertId()], 201);
}

// ==============================
// POST /api/create-income
// ==============================
if ($method === 'POST' && $resource === 'api' && ($id ?? '') === 'create-income') {
    $input = getInput();
    requireFields($input, ['income', 'income_reason', 'm_status', 'currency_status']);
    $stmt = $pdo->prepare("INSERT INTO daily (income, income_reason, m_status, currency_status, timestamp) VALUES (?,?,?,?,NOW())");
    $stmt->execute([$input['income'], $input['income_reason'], $input['m_status'], $input['currency_status']]);
    respond(['message' => 'Income added successfully', 'incomeId' => $pdo->lastInsertId()], 201);
}

// ==============================
// POST /api/create-expenditure
// ==============================
if ($method === 'POST' && $resource === 'api' && ($id ?? '') === 'create-expenditure') {
    $input = getInput();
    requireFields($input, ['expenditure', 'expenditure_reason', 'm_status']);
    $stmt = $pdo->prepare("INSERT INTO daily (expenditure, expenditure_reason, m_status, timestamp) VALUES (?,?,?,NOW())");
    $stmt->execute([$input['expenditure'], $input['expenditure_reason'], $input['m_status']]);
    respond(['message' => 'Expenditure added successfully', 'expenditureId' => $pdo->lastInsertId()], 201);
}

// ==============================
// POST /api/create-note
// ==============================
if ($method === 'POST' && $resource === 'api' && ($id ?? '') === 'create-note') {
    $input = getInput();
    requireFields($input, ['content', 'module']);
    $stmt = $pdo->prepare("INSERT INTO notes (content, module, timestamp) VALUES (?,?,NOW())");
    $stmt->execute([$input['content'], $input['module']]);
    respond(['message' => 'Note added successfully', 'noteId' => $pdo->lastInsertId()], 201);
}

// ==============================
// POST /api/create-plan
// ==============================
if ($method === 'POST' && $resource === 'api' && ($id ?? '') === 'create-plan') {
    $input = getInput();
    requireFields($input, ['plan_name', 'description', 'percent', 'type']);
    $stmt = $pdo->prepare("INSERT INTO plans (plan_name, description, percent, type, timestamp) VALUES (?,?,?,?,NOW())");
    $stmt->execute([$input['plan_name'], $input['description'], $input['percent'], $input['type']]);
    respond(['message' => 'Plan added successfully', 'planId' => $pdo->lastInsertId()], 201);
}

// ==============================
// POST /api/create-school_table
// ==============================
if ($method === 'POST' && $resource === 'api' && ($id ?? '') === 'create-school_table') {
    $input = getInput();
    requireFields($input, ['days', 'first_time', 'second_time', 'third_time', 'fourth_time']);
    $stmt = $pdo->prepare("INSERT INTO school_tables (days, first_time, second_time, third_time, fourth_time, timestamp) VALUES (?,?,?,?,?,NOW())");
    $stmt->execute([$input['days'], $input['first_time'], $input['second_time'], $input['third_time'], $input['fourth_time']]);
    respond(['message' => 'Table added successfully', 's_tableId' => $pdo->lastInsertId()], 201);
}

// ==============================
// POST /api/create-withdraw
// ==============================
if ($method === 'POST' && $resource === 'api' && ($id ?? '') === 'create-withdraw') {
    $input = getInput();
    requireFields($input, ['amount', 'm_status']);
    $stmt = $pdo->prepare("INSERT INTO withdraw (amount, description, m_status, created_at) VALUES (?,?,?,NOW())");
    $stmt->execute([$input['amount'], $input['description'] ?? null, $input['m_status']]);
    respond(['message' => 'Withdraw added successfully', 'withdrawId' => $pdo->lastInsertId()], 201);
}

// ==============================
// POST /api/upload (image upload)
// ==============================
if ($method === 'POST' && $resource === 'api' && ($id ?? '') === 'upload') {
    if (!isset($_FILES['image'])) {
        respond(['error' => 'No image uploaded'], 400);
    }
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = time() . '.' . $ext;
    $filepath = '/' . $filename;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
        respond(['error' => 'Failed to save file'], 500);
    }
    $stmt = $pdo->prepare("INSERT INTO images (filename, path) VALUES (?,?)");
    $stmt->execute([$filename, $filepath]);
    respond(['filePath' => $filepath]);
}

// ==============================
// GET /api/images
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'images') {
    $stmt = $pdo->query("SELECT * FROM images");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/admin
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'admin') {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/admin/:id
// ==============================
if ($method === 'GET' && $resource === 'api' && isset($segments[2]) && $segments[2] !== '' && 
    isset($segments[1]) && $segments[1] === 'admin' && is_numeric($segments[2])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$segments[2]]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Admin not found'], 404);
    respond($row);
}

// ==============================
// PUT /api/update-admin/:id
// ==============================
if ($method === 'PUT' && $resource === 'api' && str_starts_with($id ?? '', 'update-admin')) {
    $adminId = $segments[3] ?? null;
    $input   = getInput();
    requireFields($input, ['name', 'phone', 'email']);
    if ($input['password'] ?? '') {
        $hashed = password_hash($input['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, email=?, password=? WHERE id=?");
        $stmt->execute([$input['name'], $input['phone'], $input['email'], $hashed, $adminId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, email=? WHERE id=?");
        $stmt->execute([$input['name'], $input['phone'], $input['email'], $adminId]);
    }
    if ($stmt->rowCount() === 0) respond(['error' => 'Admin not found'], 404);
    respond(['message' => 'Admin updated successfully', 'adminId' => $adminId]);
}

// ==============================
// DELETE /api/delete-admin/:id
// ==============================
if ($method === 'DELETE' && $resource === 'api' && str_starts_with($id ?? '', 'delete-admin')) {
    $userId = $segments[3] ?? null;
    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$userId]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Admin not found'], 404);
    respond(['message' => 'Admin deleted successfully']);
}

// ==============================
// GET /api/withdraw
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'withdraw' && !isset($segments[3])) {
    $stmt = $pdo->query("SELECT * FROM withdraw ORDER BY id DESC");
    respond($stmt->fetchAll());
}

// sdfsd

// ==============================
// GET /api/withdraw/:id
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'withdraw' && isset($segments[3])) {
    $stmt = $pdo->prepare("SELECT * FROM withdraw WHERE id=?");
    $stmt->execute([$segments[3]]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Withdraw not found'], 404);
    respond($row);
}

// ==============================
// PUT /api/update-withdraw/:id
// ==============================
if ($method === 'PUT' && $resource === 'api' && str_starts_with($id ?? '', 'update-withdraw')) {
    $withdrawID = $segments[3] ?? null;
    $input      = getInput();
    requireFields($input, ['amount', 'm_status']);
    $stmt = $pdo->prepare("UPDATE withdraw SET amount=?, description=?, m_status=? WHERE id=?");
    $stmt->execute([$input['amount'], $input['description'] ?? null, $input['m_status'], $withdrawID]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Withdraw not found'], 404);
    respond(['message' => 'Withdraw updated successfully', 'withdrawID' => $withdrawID]);
}

// ==============================
// DELETE /api/delete-withdraw/:id
// ==============================
if ($method === 'DELETE' && $resource === 'api' && str_starts_with($id ?? '', 'delete-withdraw')) {
    $withdrawID = $segments[3] ?? null;
    $stmt = $pdo->prepare("DELETE FROM withdraw WHERE id=?");
    $stmt->execute([$withdrawID]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Withdraw not found'], 404);
    respond(['message' => 'Withdraw deleted successfully']);
}

// ==============================
// GET /api/daily (list)
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'daily' && !isset($segments[3])) {
    $stmt = $pdo->query("SELECT * FROM daily WHERE five_six IS NOT NULL ORDER BY timestamp DESC");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/daily/:id
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'daily' && isset($segments[3])) {
    $stmt = $pdo->prepare("SELECT * FROM daily WHERE id=?");
    $stmt->execute([$segments[3]]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Daily not found'], 404);
    respond($row);
}

// ==============================
// PUT /api/update-daily/:id
// ==============================
if ($method === 'PUT' && $resource === 'api' && str_starts_with($id ?? '', 'update-daily')) {
    $dailyId = $segments[3] ?? null;
    $input   = getInput();
    $fields  = ['five_six','six_seven','seven_eight','eight_nine','nine_ten','ten_eleven',
                'eleven_twelve','twelve_thirteen','thirteen_fourteen','fourteen_fifteen',
                'fifteen_sixteen','sixteen_seventeen','seventeen_eighteen','eighteen_nineteen',
                'nineteen_twenty','twenty_twentyone','twentyone_twentytwo','twentytwo_twentythree',
                'twentythree_five','days'];
    requireFields($input, $fields);
    $stmt = $pdo->prepare("UPDATE daily SET five_six=?,six_seven=?,seven_eight=?,eight_nine=?,nine_ten=?,
        ten_eleven=?,eleven_twelve=?,twelve_thirteen=?,thirteen_fourteen=?,fourteen_fifteen=?,
        fifteen_sixteen=?,sixteen_seventeen=?,seventeen_eighteen=?,eighteen_nineteen=?,
        nineteen_twenty=?,twenty_twentyone=?,twentyone_twentytwo=?,twentytwo_twentythree=?,
        twentythree_five=?,days=? WHERE id=?");
    $vals = array_map(fn($f) => $input[$f], $fields);
    $vals[] = $dailyId;
    $stmt->execute($vals);
    if ($stmt->rowCount() === 0) respond(['error' => 'Daily not found'], 404);
    respond(['message' => 'Daily updated successfully', 'dailyId' => $dailyId]);
}

// ==============================
// DELETE /api/delete-daily/:id
// ==============================
if ($method === 'DELETE' && $resource === 'api' && str_starts_with($id ?? '', 'delete-daily')) {
    $dailyId = $segments[3] ?? null;
    $stmt = $pdo->prepare("DELETE FROM daily WHERE id=?");
    $stmt->execute([$dailyId]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Daily not found'], 404);
    respond(['message' => 'Daily deleted successfully']);
}

// ==============================
// GET /api/income
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'income') {
    $stmt = $pdo->query("SELECT * FROM daily WHERE income IS NOT NULL ORDER BY id DESC LIMIT 15");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/all-income
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'all-income') {
    $stmt = $pdo->query("SELECT * FROM daily WHERE income IS NOT NULL ORDER BY timestamp DESC");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/income/:id
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'income' && isset($segments[3])) {
    $stmt = $pdo->prepare("SELECT * FROM daily WHERE id=?");
    $stmt->execute([$segments[3]]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Income not found'], 404);
    respond($row);
}

// ==============================
// PUT /api/update-income/:id
// ==============================
if ($method === 'PUT' && $resource === 'api' && str_starts_with($id ?? '', 'update-income')) {
    $IncomeID = $segments[3] ?? null;
    $input    = getInput();
    requireFields($input, ['income', 'income_reason', 'm_status', 'currency_status']);
    $stmt = $pdo->prepare("UPDATE daily SET income=?, income_reason=?, m_status=?, currency_status=? WHERE id=?");
    $stmt->execute([$input['income'], $input['income_reason'], $input['m_status'], $input['currency_status'], $IncomeID]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Income not found'], 404);
    respond(['message' => 'Income updated successfully', 'IncomeID' => $IncomeID]);
}

// ==============================
// GET /api/expenditure
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'expenditure') {
    $stmt = $pdo->query("SELECT * FROM daily WHERE expenditure IS NOT NULL ORDER BY id DESC LIMIT 15");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/all-expenditure
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'all-expenditure') {
    $stmt = $pdo->query("SELECT * FROM daily WHERE expenditure IS NOT NULL ORDER BY timestamp DESC");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/Expenditure/:id
// ==============================
if ($method === 'GET' && $resource === 'api' && strtolower($id ?? '') === 'expenditure' && isset($segments[3])) {
    $stmt = $pdo->prepare("SELECT * FROM daily WHERE id=?");
    $stmt->execute([$segments[3]]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Expenditure not found'], 404);
    respond($row);
}

// ==============================
// PUT /api/update-expenditure/:id
// ==============================
if ($method === 'PUT' && $resource === 'api' && str_starts_with($id ?? '', 'update-expenditure')) {
    $ExpenditureID = $segments[3] ?? null;
    $input         = getInput();
    requireFields($input, ['expenditure', 'expenditure_reason', 'm_status']);
    $stmt = $pdo->prepare("UPDATE daily SET expenditure=?, expenditure_reason=?, m_status=? WHERE id=?");
    $stmt->execute([$input['expenditure'], $input['expenditure_reason'], $input['m_status'], $ExpenditureID]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Expenditure not found'], 404);
    respond(['message' => 'Expenditure updated successfully', 'ExpenditureID' => $ExpenditureID]);
}

// ==============================
// GET /api/note
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'note' && !isset($segments[3])) {
    $stmt = $pdo->query("SELECT * FROM notes ORDER BY id DESC LIMIT 15");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/all-note
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'all-note') {
    $stmt = $pdo->query("SELECT * FROM notes ORDER BY timestamp DESC");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/note/:id
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'note' && isset($segments[3])) {
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id=?");
    $stmt->execute([$segments[3]]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Note not found'], 404);
    respond($row);
}

// ==============================
// PUT /api/update-note/:id
// ==============================
if ($method === 'PUT' && $resource === 'api' && str_starts_with($id ?? '', 'update-note')) {
    $noteID = $segments[3] ?? null;
    $input  = getInput();
    requireFields($input, ['content', 'module']);
    $stmt = $pdo->prepare("UPDATE notes SET content=?, module=? WHERE id=?");
    $stmt->execute([$input['content'], $input['module'], $noteID]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Note not found'], 404);
    respond(['message' => 'Note updated successfully', 'noteID' => $noteID]);
}

// ==============================
// DELETE /api/delete-note/:id
// ==============================
if ($method === 'DELETE' && $resource === 'api' && str_starts_with($id ?? '', 'delete-note')) {
    $noteId = $segments[3] ?? null;
    $stmt   = $pdo->prepare("DELETE FROM notes WHERE id=?");
    $stmt->execute([$noteId]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Note not found'], 404);
    respond(['message' => 'Note deleted successfully']);
}

// ==============================
// GET /api/school_table
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'school_table') {
    $stmt = $pdo->query("SELECT * FROM school_tables ORDER BY timestamp DESC");
    respond($stmt->fetchAll());
}

// ==============================
// DELETE /api/delete-school_table/:id
// ==============================
if ($method === 'DELETE' && $resource === 'api' && str_starts_with($id ?? '', 'delete-school_table')) {
    $tableId = $segments[3] ?? null;
    $stmt    = $pdo->prepare("DELETE FROM school_tables WHERE id=?");
    $stmt->execute([$tableId]);
    if ($stmt->rowCount() === 0) respond(['error' => 'School table not found'], 404);
    respond(['message' => 'School Table deleted successfully']);
}

// ==============================
// GET /api/plan
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'plan' && !isset($segments[3])) {
    $stmt = $pdo->query("SELECT * FROM plans WHERE YEAR(timestamp) = 2026 ORDER BY type DESC");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/plan/:id
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'plan' && isset($segments[3])) {
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE id=?");
    $stmt->execute([$segments[3]]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Plan not found'], 404);
    respond($row);
}

// ==============================
// GET /api/plan_success
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'plan_success') {
    $stmt = $pdo->query("SELECT * FROM plans WHERE status = 1 ORDER BY timestamp DESC");
    respond($stmt->fetchAll());
}

// ==============================
// GET /api/plan_not_yet_success
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'plan_not_yet_success') {
    $stmt = $pdo->query("SELECT * FROM plans WHERE status = 0 OR status = 2 ORDER BY id DESC");
    respond($stmt->fetchAll());
}

// ==============================
// PUT /api/update-plan/:id
// ==============================
if ($method === 'PUT' && $resource === 'api' && str_starts_with($id ?? '', 'update-plan')) {
    $PlanID = $segments[3] ?? null;
    $input  = getInput();
    requireFields($input, ['plan_name', 'description', 'percent', 'active', 'status', 'type']);
    $stmt = $pdo->prepare("UPDATE plans SET plan_name=?, description=?, percent=?, active=?, status=?, type=? WHERE id=?");
    $stmt->execute([$input['plan_name'], $input['description'], $input['percent'],
                    $input['active'], $input['status'], $input['type'], $PlanID]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Plan not found'], 404);
    respond(['message' => 'Plan updated successfully', 'PlanID' => $PlanID]);
}

// ==============================
// DELETE /api/delete-plan/:id
// ==============================
if ($method === 'DELETE' && $resource === 'api' && str_starts_with($id ?? '', 'delete-plan')) {
    $planId = $segments[3] ?? null;
    $stmt   = $pdo->prepare("DELETE FROM plans WHERE id=?");
    $stmt->execute([$planId]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Plan not found'], 404);
    respond(['message' => 'Plan deleted successfully']);
}

// ==============================
// DELETE /api/delete-image/:id
// ==============================
if ($method === 'DELETE' && $resource === 'api' && str_starts_with($id ?? '', 'delete-image')) {
    $imageId = $segments[3] ?? null;
    $stmt    = $pdo->prepare("DELETE FROM images WHERE id=?");
    $stmt->execute([$imageId]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Image not found'], 404);
    respond(['message' => 'Image deleted successfully']);
}

// ==============================
// GET /api/count-plan_success
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'count-plan_success') {
    $stmt = $pdo->query("SELECT COUNT(plan_name) as count FROM plans WHERE status = 1 AND YEAR(timestamp) = 2026");
    respond(['count_plan_success' => (int)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/count-plan_not_yet_success
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'count-plan_not_yet_success') {
    $stmt = $pdo->query("SELECT COUNT(plan_name) as count FROM plans WHERE status = 0 OR status = 2 AND YEAR(timestamp) = 2026");
    respond(['count_plan_not_yet_success' => (int)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/count-plan
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'count-plan') {
    $stmt = $pdo->query("SELECT COUNT(plan_name) as count FROM plans WHERE YEAR(timestamp) = 2026");
    respond(['count_plan' => (int)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/count-days
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'count-days') {
    $stmt = $pdo->query("SELECT COUNT(id) AS count_days FROM daily WHERE five_six IS NOT NULL AND timestamp >= '2025-12-31'");
    respond(['count_days' => (int)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/sum-income?startDate=&endDate=
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-income') {
    $startDate = $_GET['startDate'] ?? null;
    $endDate   = $_GET['endDate']   ?? null;
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN currency_status = 0 THEN income ELSE 0 END), 0) AS sum_income,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '1' THEN income ELSE 0 END), 0) AS sum_income_transfer,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '0' THEN income ELSE 0 END), 0) AS sum_income_cashier
        FROM daily
        WHERE timestamp BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$startDate, $endDate]);
    respond($stmt->fetch());
}

// ==============================
// GET /api/sum-all-income
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-all-income') {
    $stmt = $pdo->query("SELECT COALESCE(SUM(income), 0) AS sum_income FROM daily WHERE currency_status = 0");
    respond(['sum_income' => (float)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/sum-all-expenditure
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-all-expenditure') {
    $stmt = $pdo->query("SELECT COALESCE(SUM(expenditure), 0) AS sum_expenditure FROM daily WHERE currency_status = 0");
    respond(['sum_expenditure' => (float)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/sum-expenditure?startDate=&endDate=
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-expenditure') {
    $startDate = $_GET['startDate'] ?? null;
    $endDate   = $_GET['endDate']   ?? null;
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN currency_status = 0 THEN expenditure ELSE 0 END), 0) AS sum_expenditure,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '1' THEN expenditure ELSE 0 END), 0) AS sum_expenditure_transfer,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '0' THEN expenditure ELSE 0 END), 0) AS sum_expenditure_cashier
        FROM daily
        WHERE timestamp BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$startDate, $endDate]);
    respond($stmt->fetch());
}

// ==============================
// GET /api/sum-money_remaining
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-money_remaining') {
    $stmt = $pdo->query("
        SELECT
            (COALESCE((SELECT SUM(income) FROM daily WHERE currency_status = 0), 0)
             - COALESCE((SELECT SUM(expenditure) FROM daily WHERE currency_status = 0), 0))
            AS sum_money_remaining,
            (COALESCE((SELECT SUM(income) FROM daily WHERE currency_status = 0 AND m_status = '1'), 0)
             - COALESCE((SELECT SUM(expenditure) FROM daily WHERE currency_status = 0 AND m_status = '1'), 0)
             - COALESCE((SELECT SUM(amount) FROM withdraw WHERE m_status = '0'), 0)
             + COALESCE((SELECT SUM(amount) FROM withdraw WHERE m_status = '1'), 0))
            AS sum_remaining_transfer,
            (COALESCE((SELECT SUM(income) FROM daily WHERE currency_status = 0 AND m_status = '0'), 0)
             - COALESCE((SELECT SUM(expenditure) FROM daily WHERE currency_status = 0 AND m_status = '0'), 0)
             + COALESCE((SELECT SUM(amount) FROM withdraw WHERE m_status = '0'), 0)
             - COALESCE((SELECT SUM(amount) FROM withdraw WHERE m_status = '1'), 0))
            AS sum_remaining_cashier
    ");
    respond($stmt->fetch());
}

// ==============================
// GET /api/sum-money_remaining_dollar
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-money_remaining_dollar') {
    $stmt = $pdo->query("SELECT COALESCE(SUM(income), 0) AS sum_money_remaining_dollar FROM daily WHERE currency_status = 1");
    respond(['sum_money_remaining_dollar' => (float)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/sum-expenditure_todays
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-expenditure_todays') {
    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN currency_status = 0 THEN expenditure ELSE 0 END), 0) AS sum_expenditure_todays,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '1' THEN expenditure ELSE 0 END), 0) AS sum_expenditure_transfer_todays,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '0' THEN expenditure ELSE 0 END), 0) AS sum_expenditure_cashier_todays
        FROM daily
        WHERE currency_status = 0 AND DATE(timestamp) = CURDATE()
    ");
    respond($stmt->fetch());
}

// ==============================
// GET /api/sum-expenditure_this_month
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-expenditure_this_month') {
    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN currency_status = 0 THEN expenditure ELSE 0 END), 0) AS sum_expenditure_this_month,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '1' THEN expenditure ELSE 0 END), 0) AS sum_expenditure_transfer_this_month,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '0' THEN expenditure ELSE 0 END), 0) AS sum_expenditure_cashier_this_month
        FROM daily
        WHERE MONTH(timestamp) = MONTH(CURDATE()) AND YEAR(timestamp) = YEAR(CURDATE())
    ");
    respond($stmt->fetch());
}

// ==============================
// GET /api/sum-income_this_month
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-income_this_month') {
    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN currency_status = 0 THEN income ELSE 0 END), 0) AS sum_income_this_month,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '1' THEN income ELSE 0 END), 0) AS sum_income_transfer_this_month,
            COALESCE(SUM(CASE WHEN currency_status = 0 AND m_status = '0' THEN income ELSE 0 END), 0) AS sum_income_cashier_this_month
        FROM daily
        WHERE MONTH(timestamp) = MONTH(CURDATE()) AND YEAR(timestamp) = YEAR(CURDATE())
    ");
    respond($stmt->fetch());
}

// ==============================
// GET /api/income-expenditure-by-month?year=
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'income-expenditure-by-month') {
    $year = $_GET['year'] ?? 2025;
    $stmt = $pdo->prepare("
        SELECT MONTH(timestamp) AS month, SUM(income) AS total_income, SUM(expenditure) AS total_expenditure
        FROM daily WHERE YEAR(timestamp) = ?
        GROUP BY MONTH(timestamp) ORDER BY MONTH(timestamp)
    ");
    $stmt->execute([$year]);
    $results = $stmt->fetchAll();
    $months      = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    $income      = array_fill(0, 12, 0);
    $expenditure = array_fill(0, 12, 0);
    foreach ($results as $row) {
        $i = $row['month'] - 1;
        $income[$i]      = (float)($row['total_income']      ?? 0);
        $expenditure[$i] = (float)($row['total_expenditure'] ?? 0);
    }
    respond(['months' => $months, 'income' => $income, 'expenditure' => $expenditure, 'year' => $year]);
}

// ==============================
// GET /api/income-expenditure-by-date?year=&month=
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'income-expenditure-by-date') {
    $year  = (int)($_GET['year']  ?? 2025);
    $month = (int)($_GET['month'] ?? 1);
    $stmt  = $pdo->prepare("
        SELECT DATE_FORMAT(timestamp,'%Y-%m-%d') AS date, SUM(income) AS total_income, SUM(expenditure) AS total_expenditure
        FROM daily WHERE YEAR(timestamp) = ? AND MONTH(timestamp) = ?
        GROUP BY DATE(timestamp) ORDER BY DATE(timestamp)
    ");
    $stmt->execute([$year, $month]);
    $results     = $stmt->fetchAll();
    $daysInMonth = (int)(new DateTime("$year-$month-01"))->format('t');
    $dates = $income = $expenditure = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dates[]       = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $income[]      = 0;
        $expenditure[] = 0;
    }
    foreach ($results as $row) {
        $idx = array_search($row['date'], $dates);
        if ($idx !== false) {
            $income[$idx]      = (float)($row['total_income']      ?? 0);
            $expenditure[$idx] = (float)($row['total_expenditure'] ?? 0);
        }
    }
    respond(['year' => $year, 'month' => $month, 'dates' => $dates, 'income' => $income, 'expenditure' => $expenditure]);
}

// ==============================
// GET /api/sum-income_by_mom?startDate=&endDate=
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-income_by_mom') {
    $startDate = $_GET['startDate'] ?? null;
    $endDate   = $_GET['endDate']   ?? null;
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(income), 0) AS sum_income_by_mom FROM daily WHERE income_reason = 'mom' AND timestamp BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
    $stmt->execute([$startDate, $endDate]);
    respond(['sum_income_by_mom' => (float)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/sum-expenditure_to_mom
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-expenditure_to_mom') {
    $stmt = $pdo->query("SELECT COALESCE(SUM(expenditure), 0) AS v FROM daily WHERE expenditure_reason = 'mom'");
    respond(['sum_expenditure_to_mom' => (float)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/sum-expenditure_to_wife
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-expenditure_to_wife') {
    $stmt = $pdo->query("SELECT COALESCE(SUM(expenditure), 0) AS v FROM daily WHERE expenditure_reason = 'wife'");
    respond(['sum_expenditure_to_wife' => (float)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/sum-income_by_brother
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-income_by_brother') {
    $stmt = $pdo->query("SELECT COALESCE(SUM(income), 0) AS v FROM daily WHERE income_reason = 'brother'");
    respond(['sum_income_by_brother' => (float)$stmt->fetchColumn()]);
}

// ==============================
// GET /api/sum-income_by_salary
// ==============================
if ($method === 'GET' && $resource === 'api' && ($id ?? '') === 'sum-income_by_salary') {
    $stmt = $pdo->query("SELECT COALESCE(SUM(income), 0) AS v FROM daily WHERE income_reason = 'salary'");
    respond(['sum_income_by_salary' => (float)$stmt->fetchColumn()]);
}

// ==============================
// Fallback
// ==============================
respond(['error' => 'Route not found'], 404);