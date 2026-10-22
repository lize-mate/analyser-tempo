<?php

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dbFile = __DIR__ . '/../backend/storage/db.json';
            if (file_exists($dbFile)) {
                $db = json_decode(file_get_contents($dbFile), true);
                $host = $db['host'] ?? 'localhost';
                $dbname = $db['dbname'] ?? 'qa_analise';
                $user = $db['user'] ?? 'root';
                $pass = $db['pass'] ?? '';
            } else {
                // Fallback para valores padrão
                $host = 'localhost';
                $dbname = 'qa_analise';
                $user = 'root';
                $pass = '';
            }
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Erro de ligação à base de dados: ' . $e->getMessage());
        }
    }
    return $pdo;
}

/** ✅ Acrescentada — era chamada mas nunca definida */
function _normalizeRecord(array $r): array {
  return [
    'id'             => $r['id']             ?? null,
    'audio_filename' => $r['audio_filename'] ?? null,
    'pdf_filename'   => $r['pdf_filename']   ?? null,
    'qualidade'      => isset($r['qualidade']) ? (float) $r['qualidade'] : null,
    'created_by'     => $r['created_by']     ?? null,
    'created_at'     => $r['created_at']     ?? null,
  ];
}

function apiGet(string $endpoint): array {
  $ch = curl_init(API_BASE . $endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
  ]);
  $body   = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($status !== 200 || $body === false) return [];
  return json_decode($body, true) ?? [];
}

function fetchAllRecords(): array {
  $user     = currentUser();
  $role     = $user['role']     ?? 'gestor';
  $username = $user['username'] ?? '';
  $data = ($role === 'admin')
    ? apiGet('/results/all')
    : apiGet('/results/all?user=' . urlencode($username));
  return array_map('_normalizeRecord', $data['records'] ?? []);
}

function fetchDashboardStats(): array {
  $user     = currentUser();
  $role     = $user['role']     ?? 'gestor';
  $username = $user['username'] ?? '';
  $data = ($role === 'admin')
    ? apiGet('/results/all')
    : apiGet('/results/all?user=' . urlencode($username));
  $records = $data['records'] ?? [];
  $today   = date('Y-m-d');
  if ($role !== 'admin') {
    $records = array_values(array_filter($records, function ($r) use ($today) {
      return substr($r['created_at'] ?? '', 0, 10) === $today;
    }));
  }
  $total = count($records);
  $pdfs  = count(array_filter($records, fn($r) => !empty($r['pdf_filename'])));
  return [
    'total' => $total,
    'pdfs'  => $pdfs,
    'last'  => $total > 0 ? formatDate($records[0]['created_at']) : '—',
  ];
}

/* ── Áudios remotos ── */
function fetchRemoteAudios(bool $force = false): ?array {
  $url = API_BASE . '/audios/list' . ($force ? '?force=1' : '');
  $ch  = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
  ]);
  $body   = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($status !== 200 || $body === false) return null;
  $data = json_decode($body, true);
  if (!is_array($data) || !isset($data['files'])) return null;
  return $data['files'];
}

function remoteAudioStreamUrl(string $f): string {
  return API_BASE . '/audios/stream/' . rawurlencode($f);
}
function remoteAudioDownloadUrl(string $f): string {
  return API_BASE . '/audios/download/' . rawurlencode($f);
}

/* ── Transcrições remotas ── */
function fetchRemoteTranscricoes(bool $force = false): ?array {
  $url = API_BASE . '/transcricoes/list' . ($force ? '?force=1' : '');
  $ch  = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
  ]);
  $body   = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($status !== 200 || $body === false) return null;
  $data = json_decode($body, true);
  if (!is_array($data) || !isset($data['files'])) return null;
  return $data['files'];
}
function remoteTxtViewUrl(string $f): string {
  return API_BASE . '/transcricoes/view/' . rawurlencode($f);
}
function remoteTxtDownloadUrl(string $f): string {
  return API_BASE . '/transcricoes/download/' . rawurlencode($f);
}

/* ── Helpers ── */
function formatDate(?string $dt): string {
  if (!$dt) return '—';
  $ts = strtotime($dt);
  return $ts ? date('d/m/Y', $ts) : '—';
}
function formatTime(?string $dt): string {
  if (!$dt) return '—';
  $ts = strtotime($dt);
  return $ts ? date('H:i', $ts) : '—';
}
function scoreClass(?float $score): string {
  if ($score === null) return '';
  if ($score >= 70) return 'score-high';
  if ($score >= 50) return 'score-mid';
  return 'score-low';
}

/* ── PDFs remotos (relatórios de análise) ── */
function fetchRemotePdfs(bool $force = false): ?array {
    $url = API_BASE . '/pdfs/list' . ($force ? '?force=1' : '');
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200 || $body === false) return null;
    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['files'])) return null;
    return $data;
}

function remotePdfViewUrl(string $f): string {
    return API_BASE . '/pdfs/view/' . rawurlencode($f);
}

function remotePdfDownloadUrl(string $f): string {
    return API_BASE . '/pdfs/download/' . rawurlencode($f);
}

/**
 * Editar dados de um utilizador existente
 */
function editUser(string $username, array $data): bool {
    $pdo = getDB();
    $fields = [];
    $values = [];
    
    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $values[] = $data['name'];
    }
    if (isset($data['username']) && $data['username'] !== $username) {
        $fields[] = 'username = ?';
        $values[] = $data['username'];
    }
    if (isset($data['password'])) {
        $fields[] = 'password = ?';
        $values[] = $data['password'];
    }
    if (isset($data['email'])) {
        $fields[] = 'email = ?';
        $values[] = $data['email'];
    }
    
    if (empty($fields)) return false;
    
    $values[] = $username;
    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE username = ?';
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}

?>