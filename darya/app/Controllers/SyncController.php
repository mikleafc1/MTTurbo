<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\SyncService;

/** همگام‌سازی — sync.view / sync.manage */
class SyncController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('sync.view');

        $entityType = (string) $this->input('entity_type', '');
        $where = '1=1';
        $params = [];
        if ($entityType !== '') {
            $where = 'entity_type = ?';
            $params = [$entityType];
        }

        $rows = Database::all(
            "SELECT * FROM sync_log WHERE $where ORDER BY id DESC LIMIT 100",
            $params
        );

        $entityTypes = Database::all(
            "SELECT DISTINCT entity_type FROM sync_log WHERE entity_type IS NOT NULL AND entity_type <> '' ORDER BY entity_type"
        );

        $pending = (int) Database::val("SELECT COUNT(*) FROM sync_events WHERE status = 'pending'");
        $failed = (int) Database::val("SELECT COUNT(*) FROM sync_events WHERE status = 'failed'");
        $processing = (int) Database::val("SELECT COUNT(*) FROM sync_events WHERE status = 'processing'");

        $apiKeys = Database::all('SELECT key_prefix, is_active FROM api_keys ORDER BY id DESC LIMIT 10');

        // آدرس پایه API برای کارت راهنما
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'your-domain';
        $dir = str_replace('/index.php', '', (string) dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $apiBase = $scheme . '://' . $host . rtrim($dir, '/') . '/api/index.php';

        $this->view('sync/index', [
            'title'       => 'همگام‌سازی',
            'rows'        => $rows,
            'entityTypes' => $entityTypes,
            'entityType'  => $entityType,
            'pending'     => $pending,
            'failed'      => $failed,
            'processing'  => $processing,
            'apiKeys'     => $apiKeys,
            'apiBase'     => $apiBase,
            'syncEnabled' => (int) setting('sync_enabled', '1') === 1,
        ]);
    }

    /** اجرای صف رویدادها (POST) */
    public function runQueueAction(): void
    {
        $this->requirePerm('sync.manage');

        $res = SyncService::queueRun();
        $done = (int) ($res['processed'] ?? 0);
        $failed = (int) ($res['failed'] ?? 0);
        if ($done === 0 && $failed === 0) {
            flash_set('success', 'صف خالی بود؛ رویداد در انتظاری وجود ندارد.');
        } else {
            flash_set($failed > 0 ? 'error' : 'success',
                'صف اجرا شد: ' . fa_num($done) . ' رویداد انجام شد، ' . fa_num($failed) . ' ناموفق.');
        }
        $this->redirect('sync');
    }
}
