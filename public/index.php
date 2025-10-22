<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Database.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

// ========= API Key for admin/plugin routes =========
const API_KEY = 'changeme-YourSuperSecretKey';
// Change this to actual key
// ==========================================

$app = AppFactory::create();
$app->setBasePath('/trouidees-fb-queue-api/public');
$app->addBodyParsingMiddleware();

$db = new Database();
$conn = $db->getConnection();

// -- Utility: Security Check --
function require_api_key(Request $request) {
    $key = $request->getHeaderLine('X-API-KEY');
    if ($key !== API_KEY) {
        throw new Slim\Exception\HttpUnauthorizedException($request, 'Invalid API key');
    }
}

// ----------- ROUTES -----------

// 0. Test route: GET /
$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write('Trouidees FB Queue API is running!');
    return $response;
});

// 1. GET /dbtest (public test DB connection/count)
$app->get('/dbtest', function (Request $request, Response $response, array $args) use ($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM fb_submissions");
    $row = $result->fetch_assoc();
    $response->getBody()->write('Submissions in DB: ' . $row['count']);
    return $response;
});

// 2. POST /submissions (public: submit new data, with safe date handling)
$app->post('/submissions', function (Request $request, Response $response, array $args) use ($conn) {
    $data = $request->getParsedBody();
    $email = isset($data['email']) ? $conn->real_escape_string($data['email']) : '';
    $area = isset($data['area']) ? $conn->real_escape_string($data['area']) : '';
    $begroting = isset($data['begroting']) ? $conn->real_escape_string($data['begroting']) : '';
    $datum_van_troue = isset($data['datum_van_troue']) ? $conn->real_escape_string($data['datum_van_troue']) : null;
    // -- Validate date, allow only YYYY-MM-DD, else NULL
    if (!$datum_van_troue || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum_van_troue)) {
        $datum_van_troue = null;
    }
    $vraag = isset($data['vraag']) ? $conn->real_escape_string($data['vraag']) : '';
    $terme_aanvaar = isset($data['terme_aanvaar']) ? (int)$data['terme_aanvaar'] : 0;

    $sql = "INSERT INTO fb_submissions (email, area, begroting, datum_van_troue, vraag, terme_aanvaar, status)
            VALUES (
                '$email',
                '$area',
                '$begroting',
                " . ($datum_van_troue ? "'$datum_van_troue'" : "NULL") . ",
                '$vraag',
                $terme_aanvaar,
                'pending'
            )";
    if ($conn->query($sql)) {
        $response->getBody()->write(json_encode(['success' => true, 'id' => $conn->insert_id]));
        return $response->withHeader('Content-Type', 'application/json');
    } else {
        $response->getBody()->write(json_encode(['success' => false, 'error' => $conn->error]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ===== STATIC ROUTES FIRST =====

$app->get('/submissions', function (Request $request, Response $response, array $args) use ($conn) {
    require_api_key($request);
    $result = $conn->query("SELECT * FROM fb_submissions ORDER BY created_at DESC");
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    $response->getBody()->write(json_encode($submissions));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/submissions/pending', function (Request $request, Response $response, array $args) use ($conn) {
    require_api_key($request);
    $result = $conn->query("SELECT * FROM fb_submissions WHERE status='pending' ORDER BY created_at DESC");
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    $response->getBody()->write(json_encode($submissions));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/submissions/approved', function (Request $request, Response $response, array $args) use ($conn) {
    require_api_key($request);
    $result = $conn->query("SELECT * FROM fb_submissions WHERE status='approved' ORDER BY created_at DESC");
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    $response->getBody()->write(json_encode($submissions));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/submissions/readytopost', function (Request $request, Response $response, array $args) use ($conn) {
    require_api_key($request);
    $result = $conn->query("SELECT * FROM fb_submissions WHERE status='approved' ORDER BY created_at ASC");
    $to_post = [];
    while ($row = $result->fetch_assoc()) {
        $to_post[] = $row;
    }
    $response->getBody()->write(json_encode($to_post));
    return $response->withHeader('Content-Type', 'application/json');
});

// ===== DYNAMIC ROUTE LAST =====

$app->get('/submissions/{id}', function (Request $request, Response $response, array $args) use ($conn) {
    require_api_key($request);
    $id = (int)$args['id'];
    $result = $conn->query("SELECT * FROM fb_submissions WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $response->getBody()->write(json_encode($row));
        return $response->withHeader('Content-Type', 'application/json');
    } else {
        $response->getBody()->write(json_encode(['success'=>false, 'error'=>'Not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
});

// ===== PATCH ROUTES FOR ADMIN =====

$app->patch('/submissions/{id}/approve', function (Request $request, Response $response, array $args) use ($conn) {
    require_api_key($request);
    $id = (int)$args['id'];
    $ok = $conn->query("UPDATE fb_submissions SET status='approved' WHERE id=$id");
    $response->getBody()->write(json_encode(['success'=> $ok, 'id'=>$id]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->patch('/submissions/{id}/deny', function (Request $request, Response $response, array $args) use ($conn) {
    require_api_key($request);
    $id = (int)$args['id'];
    $ok = $conn->query("UPDATE fb_submissions SET status='denied' WHERE id=$id");
    $response->getBody()->write(json_encode(['success'=> $ok, 'id'=>$id]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->patch('/submissions/{id}/publish', function (Request $request, Response $response, array $args) use ($conn) {
    require_api_key($request);
    $id = (int)$args['id'];
    $ok = $conn->query("UPDATE fb_submissions SET status='published' WHERE id=$id");
    $response->getBody()->write(json_encode(['success'=> $ok, 'id'=>$id]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
