<?php
// Cache Buster: 2026-05-26 02:00:00
require_once __DIR__ . '/session_handler.php';

// Debug: Session-Status vor check_auth()
error_log("user_management.php: Session ID = " . session_id());
error_log("user_management.php: authenticated = " . (isset($_SESSION['authenticated']) ? $_SESSION['authenticated'] : "NOT SET"));
error_log("user_management.php: is_logged_in() = " . (is_logged_in() ? "true" : "false"));

check_auth();

$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Lade Benutzer-Info aus DB um is_admin zu überprüfen
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$is_admin = ($user_data && $user_data['is_admin']) ? true : false;

if (!$is_admin) {
    exit('Zugriff verweigert. Nur Administratoren können Benutzer verwalten.');
}

$current_user = null;
$db_error = "";

try {
    // Direkt aus DB laden, ohne get_current_user()
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT id, username, is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $db_error = "DB Error: " . $e->getMessage();
}

$status_meldung = $db_error;
$status_typ = $db_error ? "error" : "";

// Passwort ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $status_meldung = "FEHLER: Sicherheits-Token ungültig.";
        $status_typ = "error";
    } elseif ($_POST['action'] === 'change_password') {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $status_meldung = "FEHLER: Alle Felder sind erforderlich.";
            $status_typ = "error";
        } elseif ($new_password !== $confirm_password) {
            $status_meldung = "FEHLER: Neue Passwörter stimmen nicht überein.";
            $status_typ = "error";
        } elseif (strlen($new_password) < 8) {
            $status_meldung = "FEHLER: Passwort muss mindestens 8 Zeichen lang sein.";
            $status_typ = "error";
        } else {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$current_user['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($old_password, $user['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt_update = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt_update->execute([$new_hash, $current_user['id']]);
                $status_meldung = "✓ Passwort erfolgreich geändert.";
                $status_typ = "success";
            } else {
                $status_meldung = "FEHLER: Aktuelles Passwort ist falsch.";
                $status_typ = "error";
            }
        }
    } elseif ($_POST['action'] === 'create_user') {
        // Neuen Benutzer anlegen
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = isset($_POST['role']) ? (int)$_POST['role'] : -1;

        if (empty($username) || empty($password) || empty($confirm_password)) {
            $status_meldung = "FEHLER: Alle Felder sind erforderlich.";
            $status_typ = "error";
        } elseif (strlen($password) < 8) {
            $status_meldung = "FEHLER: Passwort muss mindestens 8 Zeichen lang sein.";
            $status_typ = "error";
        } elseif ($password !== $confirm_password) {
            $status_meldung = "FEHLER: Passwörter stimmen nicht überein.";
            $status_typ = "error";
        } elseif ($role !== 0 && $role !== 1) {
            $status_meldung = "FEHLER: Ungültige Rolle.";
            $status_typ = "error";
        } else {
            try {
                // Überprüfe, ob Username bereits existiert
                $stmt_check = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt_check->execute([$username]);
                if ($stmt_check->fetch()) {
                    $status_meldung = "FEHLER: Benutzername existiert bereits.";
                    $status_typ = "error";
                } else {
                    // Neuen User inserieren
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt_insert = $db->prepare("INSERT INTO users (username, password_hash, is_admin, created_at, updated_at) VALUES (?, ?, ?, DATETIME('now'), DATETIME('now'))");
                    $stmt_insert->execute([$username, $password_hash, $role]);
                    $status_meldung = "✓ Benutzer erfolgreich erstellt.";
                    $status_typ = "success";
                }
            } catch (Exception $e) {
                $status_meldung = "FEHLER: " . $e->getMessage();
                $status_typ = "error";
            }
        }
    } elseif ($_POST['action'] === 'edit_user') {
        // Benutzer bearbeiten
        $user_id = isset($_POST['edit_user_id']) ? (int)$_POST['edit_user_id'] : 0;
        $username = $_POST['edit_username'] ?? '';
        $role = isset($_POST['edit_role']) ? (int)$_POST['edit_role'] : -1;

        if ($user_id <= 0 || empty($username) || ($role !== 0 && $role !== 1)) {
            $status_meldung = "FEHLER: Ungültige Eingaben.";
            $status_typ = "error";
        } else {
            try {
                // Überprüfe, ob neuer Username bereits existiert (aber nicht für den aktuellen User)
                $stmt_check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt_check->execute([$username, $user_id]);
                if ($stmt_check->fetch()) {
                    $status_meldung = "FEHLER: Benutzername existiert bereits.";
                    $status_typ = "error";
                } else {
                    $stmt_update = $db->prepare("UPDATE users SET username = ?, is_admin = ?, updated_at = DATETIME('now') WHERE id = ?");
                    $stmt_update->execute([$username, $role, $user_id]);
                    $status_meldung = "✓ Benutzer erfolgreich aktualisiert.";
                    $status_typ = "success";
                }
            } catch (Exception $e) {
                $status_meldung = "FEHLER: " . $e->getMessage();
                $status_typ = "error";
            }
        }
    } elseif ($_POST['action'] === 'delete_user') {
        // Benutzer löschen
        $user_id = isset($_POST['delete_user_id']) ? (int)$_POST['delete_user_id'] : 0;

        if ($user_id <= 0) {
            $status_meldung = "FEHLER: Ungültige User-ID.";
            $status_typ = "error";
        } elseif ($user_id === $_SESSION['user_id']) {
            $status_meldung = "FEHLER: Sie können sich selbst nicht löschen.";
            $status_typ = "error";
        } else {
            try {
                $stmt_delete = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt_delete->execute([$user_id]);
                $status_meldung = "✓ Benutzer erfolgreich gelöscht.";
                $status_typ = "success";
            } catch (Exception $e) {
                $status_meldung = "FEHLER: " . $e->getMessage();
                $status_typ = "error";
            }
        }
    }
}

// Benutzer auflisten
$stmt = $db->prepare("SELECT id, username, is_admin, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzerverwaltung — REVISION100™</title>
    <style>
        :root {
            --bg-color: #1a2324;
            --panel-bg: #111819;
            --border-color: #2c3a3c;
            --text-main: #d1dcd3;
            --text-muted: #a8b8ba;
            --teal-accent: #339999;
            --orange-action: #ff6600;
            --font-mono: ui-monospace, SFMono-Regular, "SF Pro Mono", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: #fff !important; background-image: none !important; color: #000; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important; font-size: 14px; padding: 0; }
        .container { max-width: 900px; margin: 0 auto; background-color: #fff; }
        header {
            background: #fff !important;
            background-image: none !important;
            padding: 45px 32px 35px 32px !important;
            border-bottom: 1px solid #000 !important;
            margin-bottom: 40px !important;
            display: block !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .brand {
            display: flex !important;
            align-items: center !important;
            gap: 16px !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .brand-name {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
            font-size: 32px !important;
            font-weight: 700 !important;
            letter-spacing: -1px !important;
            line-height: 1.0 !important;
            color: #000 !important;
            margin: 0 !important;
            padding: 0 !important;
            display: inline-block !important;
        }
        .status-led {
            width: 12px !important;
            height: 12px !important;
            display: inline-block !important;
            background-color: #2ecc71 !important;
            border: 1px solid #000 !important;
        }
        .status-led.unsaved {
            background-color: #e74c3c !important;
        }
        .status-led.loading {
            background-color: #f1c40f !important;
        }
        .header-claim {
            font-family: monospace !important;
            font-size: 11px !important;
            color: #666 !important;
            margin-top: 8px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            display: block !important;
        }
        .user-info { font-size: 12px; color: #666; display: flex; gap: 15px; align-items: center; }
        .section { margin-bottom: 40px; padding: 0 20px; }
        .section-title { font-size: 11px; color: #666; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #000; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        input[type="text"], input[type="password"], input[type="email"] { background-color: #fff; border: 1px solid #000; color: #000; padding: 12px; font-family: var(--font-mono); font-size: 14px; }
        input:focus { outline: none; border-color: #666; }
        .form-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .form-layout { grid-template-columns: 1fr; } header { padding: 25px 16px 19px 16px !important; margin-bottom: 22px !important; } .brand-name { font-size: 24px !important; } }
        button { background-color: #000; color: #fff; border: 1px solid #000; padding: 12px 20px; font-family: var(--font-mono); font-size: 12px; font-weight: bold; text-transform: uppercase; cursor: pointer; letter-spacing: 1px; }
        button:hover { background-color: #333; }
        .status-message { padding: 15px; margin: 15px 0; border: 1px solid #000; font-size: 13px; }
        .status-message.success { background-color: #f0f9f6; border-color: #0d8659; color: #0d8659; }
        .status-message.error { background-color: #faf0f0; border-color: #ff3131; color: #ff3131; }
        .users-table { width: 100%; border-collapse: collapse; }
        .users-table thead { border-bottom: 1px solid #000; }
        .users-table th { text-align: left; padding: 12px; font-size: 11px; color: #666; text-transform: uppercase; font-weight: normal; letter-spacing: 0.5px; }
        .users-table td { padding: 12px; border-bottom: 1px solid #ddd; font-size: 13px; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 10px; background-color: #fff; color: #0d8659; border: 1px solid #0d8659; text-transform: uppercase; letter-spacing: 0.5px; }
        .back-link { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #fff; border: 1px solid #000; color: #000; text-decoration: none; font-size: 12px; text-transform: uppercase; }
        .back-link:hover { background-color: #f0f0f0; }
        .hidden { display: none; }
    </style>
    <script>
        function editUser(userId, username, isAdmin) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = isAdmin;
            document.getElementById('edit-section').classList.remove('hidden');
            window.scrollTo(0, document.getElementById('edit-section').offsetTop - 50);
        }

        function deleteUser(userId, username) {
            if (confirm('Möchten Sie den Benutzer "' + username + '" wirklich löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="csrf_token" value="' + document.querySelector('input[name="csrf_token"]').value + '">' +
                                 '<input type="hidden" name="action" value="delete_user">' +
                                 '<input type="hidden" name="delete_user_id" value="' + userId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function cancelEdit() {
            document.getElementById('edit-section').classList.add('hidden');
        }
    </script>
</head>
<body>
    <header>
        <div class="brand"><span class="brand-name">Revision100™</span><span id="statusLed" class="status-led"></span></div>
        <div class="header-claim">Interne Werkbank // System-Zentrale</div>
        <div id="statusSquares" style="display: flex; gap: 4px; margin-top: 12px; height: 12px;"></div>
    </header>
    <div class="container">

        <main>
            <section class="section">
                <div class="section-title">Passwort ändern</div>

                <?php if (!empty($status_meldung)): ?>
                    <div class="status-message <?php echo $status_typ; ?>">
                        <?php echo htmlspecialchars($status_meldung); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-layout">
                        <div class="form-group">
                            <label for="old_password">Aktuelles Passwort:</label>
                            <input type="password" id="old_password" name="old_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Neues Passwort:</label>
                            <input type="password" id="new_password" name="new_password" required placeholder="Min. 8 Zeichen">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Passwort bestätigen:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>

                    <button type="submit" style="align-self: flex-start;">Passwort ändern</button>
                </form>
            </section>

            <section class="section hidden" id="edit-section">
                <div class="section-title">Benutzer bearbeiten</div>

                <?php if (!empty($status_meldung) && $status_typ): ?>
                    <div class="status-message <?php echo $status_typ; ?>">
                        <?php echo htmlspecialchars($status_meldung); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="edit_user_id" id="edit_user_id" value="">

                    <div class="form-layout">
                        <div class="form-group">
                            <label for="edit_username">Benutzername:</label>
                            <input type="text" id="edit_username" name="edit_username" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_role">Rolle:</label>
                            <select id="edit_role" name="edit_role" required style="background-color: #182224; border: 1px solid var(--border-color); color: #ffffff; padding: 12px; font-family: var(--font-mono); font-size: 14px;">
                                <option value="0">Mitarbeiter</option>
                                <option value="1">Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; align-self: flex-start;">
                        <button type="submit" style="background-color: var(--teal-accent);">Speichern</button>
                        <button type="button" style="background-color: var(--text-muted);" onclick="cancelEdit()">Abbrechen</button>
                    </div>
                </form>
            </section>

            <section class="section">
                <div class="section-title">Neuen Benutzer anlegen</div>

                <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <input type="hidden" name="action" value="create_user">

                    <div class="form-layout">
                        <div class="form-group">
                            <label for="username">Benutzername:</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Passwort:</label>
                            <input type="password" id="password" name="password" required placeholder="Min. 8 Zeichen">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password_new">Passwort bestätigen:</label>
                            <input type="password" id="confirm_password_new" name="confirm_password" required>
                        </div>
                        <div class="form-group">
                            <label for="role">Rolle:</label>
                            <select id="role" name="role" required style="background-color: #182224; border: 1px solid var(--border-color); color: #ffffff; padding: 12px; font-family: var(--font-mono); font-size: 14px;">
                                <option value="">-- Wählen Sie eine Rolle --</option>
                                <option value="0">Mitarbeiter</option>
                                <option value="1">Administrator</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" style="align-self: flex-start;">Benutzer erstellen</button>
                </form>
            </section>

            <section class="section">
                <div class="section-title">Alle Benutzer</div>

                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Benutzername</th>
                            <th>Rolle</th>
                            <th>Erstellt am</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="badge">Admin</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Benutzer</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(substr($user['created_at'], 0, 10)); ?></td>
                                <td style="display: flex; gap: 8px;">
                                    <button type="button" style="background-color: var(--teal-accent); padding: 6px 10px; font-size: 11px;" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', <?php echo $user['is_admin']; ?>)">Bearbeiten</button>
                                    <button type="button" style="background-color: #cc3a21; padding: 6px 10px; font-size: 11px;" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">Löschen</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

<script>
const projectData = {
  id: null,
  tunnel: '',
  last_interaction_date: ''
};

const phaseIndex = {anfrage:0, analyse:1, kontakt:2, beauftragung:3, umsetzung:4, abgeschlossen:5};
const colorPalettes = {
  green: ['#a3e4d7', '#7ed4c1', '#5cc4ab', '#3bb495', '#1fa47f', '#0d8659'],
  orange: ['#FFE4B5', '#FFD699', '#FFC87D', '#FFBA61', '#FFAB45', '#FF9529'],
  red: ['#FFB3B3', '#FF9999', '#FF7F7F', '#FF6565', '#FF4B4B', '#FF3131'],
  gray: ['#D3D3D3', '#BEBEBE', '#A9A9A9', '#949494', '#7F7F7F', '#696969']
};

function getAgeStatus(lastInteractionDate) {
  if (!lastInteractionDate) return 'green';
  const last = new Date(lastInteractionDate);
  const now = new Date();
  const days = Math.floor((now - last) / (1000 * 60 * 60 * 24));
  if (days >= 13) return 'gray';
  if (days >= 12) return 'red';
  if (days >= 7) return 'orange';
  return 'green';
}

function renderPhaseSquares() {
  const phaseIdx = phaseIndex[projectData.tunnel] || 0;
  const status = getAgeStatus(projectData.last_interaction_date);
  const colors = colorPalettes[status] || colorPalettes.gray;
  const container = document.getElementById('statusSquares');

  if (!container) return;
  container.innerHTML = '';

  for (let i = 0; i < 6; i++) {
    const square = document.createElement('div');
    square.className = 'status-square';
    square.style.background = i <= phaseIdx ? colors[i] : '#eee';
    square.style.color = i <= phaseIdx ? '#fff' : '#ccc';
    square.textContent = String(i + 1);

    const phaseName = Object.keys(phaseIndex).find(key => phaseIndex[key] === i);
    square.title = phaseName ? phaseName.charAt(0).toUpperCase() + phaseName.slice(1) : '';

    container.appendChild(square);
  }
}

document.addEventListener('DOMContentLoaded', renderPhaseSquares);
</script>

</body>
</html>
