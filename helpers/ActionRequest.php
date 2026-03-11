<?php
// helpers/ActionRequest.php
// ---------------------------------------------------------------
// Central helper for the Super-Admin approval workflow.
// Usage examples at the bottom of this file.
// ---------------------------------------------------------------

class ActionRequest {

    private PDO|mysqli $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // -----------------------------------------------------------
    // REQUEST  — called by an admin before doing a sensitive action
    // -----------------------------------------------------------
    /**
     * @param int    $adminUserId  $_SESSION['user_id']
     * @param string $actionType   One of the enum values in action_requests.action_type
     * @param string $actionLabel  Human-readable, e.g. "Download Master File.xlsx"
     * @param int|null $targetId   ID of the resource (document id, personnel id, etc.)
     * @param string|null $targetType  'document' | 'personnel' | 'user' | etc.
     * @param array  $meta         Any extra data to store (filename, path …)
     * @return int   The new request ID
     */
    public function createRequest(
        int    $adminUserId,
        string $actionType,
        string $actionLabel,
        ?int   $targetId   = null,
        ?string $targetType = null,
        array  $meta       = []
    ): int {
        $token    = bin2hex(random_bytes(32)); // 64-char unique token
        $metaJson = !empty($meta) ? json_encode($meta) : null;

        $stmt = $this->conn->prepare("
            INSERT INTO action_requests
                (admin_user_id, action_type, action_label, target_id, target_type, meta_json, token)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issiiss',
            $adminUserId, $actionType, $actionLabel,
            $targetId, $targetType, $metaJson, $token
        );
        $stmt->execute();
        $requestId = $this->conn->insert_id;

        // Notify super admin
        $notify = $this->conn->prepare("
            INSERT INTO superadmin_notifications (request_id) VALUES (?)
        ");
        $notify->bind_param('i', $requestId);
        $notify->execute();

        return $requestId;
    }

    // -----------------------------------------------------------
    // APPROVE  — called by super admin
    // -----------------------------------------------------------
    /**
     * Approves a pending request and sets a 15-minute expiry token.
     */
    public function approve(int $requestId, int $superAdminUserId): bool {
        // Use MySQL NOW() for expires_at so timezone is always consistent
        // with the NOW() used in validateAndConsume()
        $stmt = $this->conn->prepare("
            UPDATE action_requests
            SET status      = 'approved',
                reviewed_at = NOW(),
                reviewed_by = ?,
                expires_at  = NOW() + INTERVAL 15 MINUTE
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param('ii', $superAdminUserId, $requestId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    // -----------------------------------------------------------
    // REJECT  — called by super admin
    // -----------------------------------------------------------
    public function reject(int $requestId, int $superAdminUserId, string $note = ''): bool {
        $stmt = $this->conn->prepare("
            UPDATE action_requests
            SET status = 'rejected',
                reviewed_at = NOW(),
                reviewed_by = ?,
                rejection_note = ?
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param('isi', $superAdminUserId, $note, $requestId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    // -----------------------------------------------------------
    // VALIDATE TOKEN  — call before actually executing the action
    // Returns the request row (array) on success, false on failure
    // -----------------------------------------------------------
    /**
     * @param int    $requestId
     * @param int    $adminUserId  Must match the original requester
     * @param string $actionType   Must match the stored action_type
     */
    public function validateAndConsume(
        int    $requestId,
        int    $adminUserId,
        string $actionType
    ): array|false {
        $stmt = $this->conn->prepare("
            SELECT * FROM action_requests
            WHERE id = ?
              AND admin_user_id = ?
              AND action_type   = ?
              AND status        = 'approved'
              AND token_used    = 0
              AND expires_at    > NOW()
        ");
        $stmt->bind_param('iis', $requestId, $adminUserId, $actionType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) return false;

        // Mark token as used (one-time)
        $upd = $this->conn->prepare("
            UPDATE action_requests SET token_used = 1 WHERE id = ?
        ");
        $upd->bind_param('i', $requestId);
        $upd->execute();

        return $row;
    }

    // -----------------------------------------------------------
    // EXPIRE old approved-but-unused tokens (run via cron or on boot)
    // -----------------------------------------------------------
    public function expireOldRequests(): void {
        $this->conn->query("
            UPDATE action_requests
            SET status = 'expired'
            WHERE status = 'approved'
              AND token_used = 0
              AND expires_at IS NOT NULL
              AND expires_at < NOW()
        ");
    }

    // -----------------------------------------------------------
    // COUNT unread super-admin notifications
    // -----------------------------------------------------------
    public function unreadCount(): int {
        $res = $this->conn->query("
            SELECT COUNT(*) AS cnt FROM superadmin_notifications WHERE is_read = 0
        ");
        return (int)$res->fetch_assoc()['cnt'];
    }

    // -----------------------------------------------------------
    // GET pending requests (for super-admin dashboard)
    // -----------------------------------------------------------
    public function getPendingRequests(): array {
        $res = $this->conn->query("
            SELECT ar.*, u.username AS admin_username
            FROM action_requests ar
            JOIN users u ON u.id = ar.admin_user_id
            WHERE ar.status = 'pending'
            ORDER BY ar.requested_at DESC
        ");
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    // -----------------------------------------------------------
    // GET all requests for an admin (their history)
    // -----------------------------------------------------------
    public function getAdminRequests(int $adminUserId, int $limit = 50): array {
        $stmt = $this->conn->prepare("
            SELECT * FROM action_requests
            WHERE admin_user_id = ?
            ORDER BY requested_at DESC
            LIMIT ?
        ");
        $stmt->bind_param('ii', $adminUserId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // -----------------------------------------------------------
    // AUDIT LOG helper
    // -----------------------------------------------------------
    public function log(int $userId, string $role, string $action, string $details = ''): void {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $this->conn->prepare("
            INSERT INTO admin_audit_log (user_id, role, action, details, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issss', $userId, $role, $action, $details, $ip);
        $stmt->execute();
    }
}

/*
 * ---------------------------------------------------------------
 * USAGE EXAMPLES
 * ---------------------------------------------------------------
 *
 * // In an admin page (e.g., before showing a download button):
 * require_once 'helpers/ActionRequest.php';
 * $ar = new ActionRequest($conn);
 * $requestId = $ar->createRequest(
 *     $_SESSION['user_id'],
 *     'download_file',
 *     'Download Master File.xlsx',
 *     6,           // document id
 *     'document',
 *     ['filename' => 'Master_File.xlsx', 'path' => 'uploads/documents/...']
 * );
 * // Then redirect to a waiting page: approval_pending.php?req=<requestId>
 *
 *
 * // In the actual download handler (download_file.php):
 * $row = $ar->validateAndConsume($_GET['req'], $_SESSION['user_id'], 'download_file');
 * if (!$row) { die('Action not approved or token expired.'); }
 * $meta = json_decode($row['meta_json'], true);
 * // ... serve the file ...
 */