<?php
// Logger.php - Stufe 4: Structured logging & monitoring
class Logger {
    private static $db;
    private static $requestId;

    public static function init($pdo) {
        self::$db = $pdo;
        self::$requestId = self::generateRequestId();
    }

    private static function generateRequestId() {
        return substr(bin2hex(random_bytes(8)), 0, 16);
    }

    public static function getRequestId() {
        return self::$requestId ?? 'unknown';
    }

    private static function log($level, $eventType, $message, $actor = null, $context = null, $duration = null) {
        if (!self::$db) return;

        try {
            $stmt = self::$db->prepare("
                INSERT INTO system_logs
                (request_id, level, event_type, actor_type, actor_id, message, context_json, duration_ms, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $contextJson = $context ? json_encode($context) : null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $actorType = null;
            $actorId = null;
            if ($actor) {
                if (is_array($actor)) {
                    $actorType = $actor['type'] ?? null;
                    $actorId = $actor['id'] ?? null;
                }
            }

            $stmt->execute([
                self::$requestId,
                $level,
                $eventType,
                $actorType,
                $actorId,
                $message,
                $contextJson,
                $duration,
                $ipAddress,
                $userAgent
            ]);
        } catch (Exception $e) {
            error_log("Logger error: " . $e->getMessage());
        }
    }

    public static function info($eventType, $message, $actor = null, $context = null) {
        self::log('INFO', $eventType, $message, $actor, $context);
    }

    public static function warn($eventType, $message, $actor = null, $context = null) {
        self::log('WARN', $eventType, $message, $actor, $context);
    }

    public static function error($eventType, $message, $actor = null, $context = null) {
        self::log('ERROR', $eventType, $message, $actor, $context);
    }

    public static function logRequest($action, $success, $duration, $context = null) {
        $level = $success ? 'INFO' : 'ERROR';
        $eventType = 'API_REQUEST';
        $message = $success ? "API action '$action' completed" : "API action '$action' failed";

        self::log($level, $eventType, $message, ['type' => 'user'], $context, (int)$duration);
    }

    public static function logJobExecution($jobName, $success, $duration, $result = null) {
        $level = $success ? 'INFO' : 'ERROR';
        $eventType = 'JOB_EXECUTION';
        $message = $success ? "Job '$jobName' completed successfully" : "Job '$jobName' failed";

        self::log($level, $eventType, $message, ['type' => 'cron'], $result, (int)$duration);
    }

    public static function logAuthAttempt($username, $success) {
        $eventType = 'AUTH_ATTEMPT';
        $level = $success ? 'INFO' : 'WARN';
        $message = $success ? "Successful login: $username" : "Failed login attempt: $username";

        self::log($level, $eventType, $message, ['type' => 'user', 'id' => $username]);
    }

    public static function logTokenValidation($projectId, $tokenValid) {
        $eventType = 'TOKEN_VALIDATION';
        $level = $tokenValid ? 'INFO' : 'WARN';
        $message = $tokenValid ? "Token validated for project $projectId" : "Token validation failed for project $projectId";

        self::log($level, $eventType, $message, ['type' => 'system'], ['project_id' => $projectId]);
    }

    public static function logException($exception, $context = null) {
        $eventType = 'EXCEPTION';
        $message = get_class($exception) . ': ' . $exception->getMessage();

        self::log('ERROR', $eventType, $message, ['type' => 'system'], $context);
    }
}
?>
