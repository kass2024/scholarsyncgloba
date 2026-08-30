<?php
declare(strict_types=1);

/**
 * Provision an `admins` row when an unregistered agent signs their contract.
 */

/**
 * @return array<string, true>
 */
function agent_contract_admin_columns(mysqli $conn): array
{
    static $cols = null;
    if (is_array($cols)) {
        return $cols;
    }
    $cols = [];
    $res = $conn->query('SHOW COLUMNS FROM `admins`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $field = strtolower((string) ($row['Field'] ?? ''));
            if ($field !== '') {
                $cols[$field] = true;
            }
        }
    }

    return $cols;
}

function agent_contract_split_full_name(string $fullName): array
{
    $parts = preg_split('/\s+/u', trim($fullName), 2, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts) || $parts === []) {
        return ['first' => '', 'last' => ''];
    }

    return [
        'first' => (string) $parts[0],
        'last'  => isset($parts[1]) ? trim((string) $parts[1]) : '',
    ];
}

function agent_contract_sanitize_username(string $raw): string
{
    $user = strtolower(trim($raw));
    $user = preg_replace('/[^a-z0-9._-]+/', '', $user) ?? '';
    $user = trim($user, '._-');

    return $user;
}

function agent_contract_unique_username(mysqli $conn, string $preferred): string
{
    $base = agent_contract_sanitize_username($preferred);
    if (strlen($base) < 3) {
        $base = 'agent_' . strtolower(bin2hex(random_bytes(3)));
    }
    if (strlen($base) > 40) {
        $base = substr($base, 0, 40);
    }

    $username = $base;
    for ($n = 0; $n < 80; $n++) {
        $stmt = $conn->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        if (!$stmt) {
            return $base . '_' . strtolower(bin2hex(random_bytes(2)));
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            return $username;
        }
        $username = $base . ($n + 2);
        if (strlen($username) > 50) {
            $username = substr($base, 0, 40) . '_' . strtolower(bin2hex(random_bytes(2)));
        }
    }

    return $base . '_' . strtolower(bin2hex(random_bytes(2)));
}

/**
 * Find or create an agent account and return its admins.id.
 *
 * @param array{
 *   name:string, email:string, phone?:string, address?:string, title?:string,
 *   username?:string, password?:string, national_id?:string, date_of_birth?:string,
 *   marital_status?:string, nationality?:string, place_of_birth?:string,
 *   employment_start_date?:string
 * } $profile
 * @return array{admin_id:int, created:bool, username:string, existing:bool}
 */
function agent_contract_ensure_admin(mysqli $conn, array $profile): array
{
    $name    = trim((string) ($profile['name'] ?? ''));
    $email   = trim((string) ($profile['email'] ?? ''));
    $phone   = trim((string) ($profile['phone'] ?? ''));
    $address = trim((string) ($profile['address'] ?? ''));
    $title   = trim((string) ($profile['title'] ?? '')) ?: 'Agent';
    $nid     = trim((string) ($profile['national_id'] ?? ''));
    $dob     = trim((string) ($profile['date_of_birth'] ?? ''));
    $marital = trim((string) ($profile['marital_status'] ?? ''));
    $nation  = trim((string) ($profile['nationality'] ?? ''));
    $birth   = trim((string) ($profile['place_of_birth'] ?? ''));
    $start   = trim((string) ($profile['employment_start_date'] ?? ''));
    $password = (string) ($profile['password'] ?? '');

    if ($name === '') {
        throw new InvalidArgumentException('Full name is required to create the agent account.');
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid email is required to create the agent account.');
    }
    if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $dob = '';
    }
    if ($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = '';
    }
    if ($marital !== '' && !in_array($marital, ['Single', 'Married', 'Divorced', 'Widowed'], true)) {
        $marital = '';
    }

    $stmt = $conn->prepare('SELECT id, username FROM admins WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Could not look up existing agent: ' . $conn->error);
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $adminId = (int) $existing['id'];
        agent_contract_fill_empty_admin_profile($conn, $adminId, [
            'name'     => $name,
            'phone'    => $phone,
            'address'  => $address,
            'title'    => $title,
            'nid'      => $nid,
            'dob'      => $dob,
            'marital'  => $marital,
            'nation'   => $nation,
            'birth'    => $birth,
        ]);

        return [
            'admin_id' => $adminId,
            'created'  => false,
            'existing' => true,
            'username' => (string) ($existing['username'] ?? ''),
        ];
    }

    $preferredUser = trim((string) ($profile['username'] ?? ''));
    if ($preferredUser === '') {
        $preferredUser = strstr($email, '@', true) ?: $name;
    }
    $username = agent_contract_unique_username($conn, $preferredUser);

    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Please choose a password of at least 8 characters for your agent login.');
    }
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $names = agent_contract_split_full_name($name);

    $wanted = [
        'username'               => $username,
        'first_name'             => $names['first'],
        'last_name'              => $names['last'],
        'full_name'              => $name,
        'email'                  => $email,
        'phone_number'           => $phone,
        'password_hash'          => $passwordHash,
        'role'                   => 'agent',
        'position'               => $title,
        'employment_type'        => 'Contract',
        'national_id'            => $nid,
        'nationality'            => $nation,
        'place_of_birth'         => $birth,
        'address'                => $address,
        'status'                 => 'active',
    ];
    if ($start !== '') {
        $wanted['employment_start_date'] = $start;
    }
    if ($dob !== '') {
        $wanted['date_of_birth'] = $dob;
    }
    if ($marital !== '') {
        $wanted['marital_status'] = $marital;
    }

    $cols = agent_contract_admin_columns($conn);
    $fields = [];
    $placeholders = [];
    $values = [];
    $types = '';
    foreach ($wanted as $col => $val) {
        if (!isset($cols[$col])) {
            continue;
        }
        $fields[] = '`' . $col . '`';
        $placeholders[] = '?';
        $values[] = $val;
        $types .= 's';
    }
    if ($fields === [] || !isset($cols['username']) || !isset($cols['password_hash'])) {
        throw new RuntimeException('The admins table is missing required columns.');
    }

    $sql = 'INSERT INTO admins (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Could not prepare agent insert: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not create agent account: ' . $err);
    }
    $adminId = (int) $conn->insert_id;
    $stmt->close();
    if ($adminId < 1) {
        throw new RuntimeException('Agent account was not created.');
    }

    return [
        'admin_id' => $adminId,
        'created'  => true,
        'existing' => false,
        'username' => $username,
    ];
}

/**
 * Fill blank profile fields on an existing admin without overwriting login credentials.
 *
 * @param array{name:string,phone:string,address:string,title:string,nid:string,dob:string,marital:string,nation:string,birth:string} $data
 */
function agent_contract_fill_empty_admin_profile(mysqli $conn, int $adminId, array $data): void
{
    $cols = agent_contract_admin_columns($conn);
    $stmt = $conn->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return;
    }

    $names = agent_contract_split_full_name($data['name']);
    $updates = [];
    $values = [];
    $types = '';

    $maybe = [
        'full_name'     => $data['name'],
        'first_name'    => $names['first'],
        'last_name'     => $names['last'],
        'phone_number'  => $data['phone'],
        'address'       => $data['address'],
        'position'      => $data['title'],
        'national_id'   => $data['nid'],
        'date_of_birth' => $data['dob'] !== '' ? $data['dob'] : null,
        'marital_status'=> $data['marital'] !== '' ? $data['marital'] : null,
        'nationality'   => $data['nation'],
        'place_of_birth'=> $data['birth'],
    ];

    foreach ($maybe as $col => $val) {
        if ($val === null || $val === '') {
            continue;
        }
        if (!isset($cols[$col])) {
            continue;
        }
        $current = trim((string) ($row[$col] ?? ''));
        if ($current !== '') {
            continue;
        }
        $updates[] = '`' . $col . '` = ?';
        $values[] = $val;
        $types .= 's';
    }

    if ($updates === []) {
        return;
    }
    $values[] = $adminId;
    $types .= 'i';
    $sql = 'UPDATE admins SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $upd = $conn->prepare($sql);
    if (!$upd) {
        return;
    }
    $upd->bind_param($types, ...$values);
    $upd->execute();
    $upd->close();
}
