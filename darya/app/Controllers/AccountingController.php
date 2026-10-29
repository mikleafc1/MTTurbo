<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Services\AccountingService;

/** حسابداری: کدینگ حساب‌ها، اسناد، دفتر معین */
class AccountingController extends Controller
{
    /** برچسب انواع حساب */
    public const TYPES = [
        'asset'     => 'دارایی',
        'liability' => 'بدهی',
        'equity'    => 'حقوق صاحبان سهام',
        'income'    => 'درآمد',
        'expense'   => 'هزینه',
    ];

    /* ==================== کدینگ حساب‌ها ==================== */

    public function accountsAction(): void
    {
        $this->requirePerm('accounting.view');
        $all = Database::all('SELECT * FROM accounts ORDER BY code ASC');
        $this->view('accounting/accounts', [
            'title' => 'کدینگ حساب‌ها',
            'rows'  => $this->buildTree($all),
            'types' => self::TYPES,
        ]);
    }

    public function accountFormAction(array $args): void
    {
        $this->requirePerm('accounting.view');
        $id = (int) ($args[0] ?? 0);
        $row = null;
        if ($id) {
            $row = Database::one('SELECT * FROM accounts WHERE id = ?', [$id]);
            if (!$row) $this->notFound();
        }
        $all = Database::all('SELECT id, code, name, parent_id, is_group FROM accounts ORDER BY code ASC');

        // والد فقط از حساب‌های گروهی؛ خودِ حساب و زیرشاخه‌هایش حذف می‌شوند (جلوگیری از حلقه)
        $exclude = $id ? $this->descendantIds($id, $all) : [];
        $parents = [];
        foreach ($all as $a) {
            if (!(int) $a['is_group']) continue;
            if (in_array((int) $a['id'], $exclude, true)) continue;
            $parents[] = $a;
        }

        $this->view('accounting/account-form', [
            'title'   => $row ? 'ویرایش حساب' : 'حساب جدید',
            'row'     => $row,
            'parents' => $parents,
            'types'   => self::TYPES,
        ]);
    }

    public function accountStoreAction(array $args): void
    {
        $this->requirePerm('accounting.manage');
        $id = (int) ($args[0] ?? 0);
        $code = (string) $this->input('code', '');
        $name = (string) $this->input('name', '');
        $type = (string) $this->input('type', 'asset');
        $parentId = (int) $this->input('parent_id', 0) ?: null;
        $isGroup = (int) ($this->input('is_group', '') === '1');

        if ($code === '' || $name === '') {
            flash_set('error', 'کد و نام حساب الزامی است.');
            $this->redirect($id ? 'accounting/account-form/' . $id : 'accounting/account-form');
        }
        if (!isset(self::TYPES[$type])) {
            flash_set('error', 'نوع حساب معتبر نیست.');
            $this->redirect($id ? 'accounting/account-form/' . $id : 'accounting/account-form');
        }
        // کد باید یکتا باشد
        $dupe = Database::one('SELECT id FROM accounts WHERE code = ? AND id <> ?', [$code, $id]);
        if ($dupe) {
            flash_set('error', 'این کد حساب قبلاً استفاده شده است.');
            $this->redirect($id ? 'accounting/account-form/' . $id : 'accounting/account-form');
        }
        if ($parentId) {
            $parent = Database::one('SELECT * FROM accounts WHERE id = ?', [$parentId]);
            if (!$parent || !(int) $parent['is_group']) {
                flash_set('error', 'حساب والد باید از نوع گروه باشد.');
                $this->redirect($id ? 'accounting/account-form/' . $id : 'accounting/account-form');
            }
        }

        $data = ['code' => $code, 'name' => $name, 'type' => $type, 'parent_id' => $parentId, 'is_group' => $isGroup];
        if ($id) {
            Database::update('accounts', $data, 'id = ?', [$id]);
            flash_set('success', 'حساب بروزرسانی شد.');
        } else {
            Database::insert('accounts', $data);
            flash_set('success', 'حساب جدید ثبت شد.');
        }
        $this->redirect('accounting/accounts');
    }

    public function accountDeleteAction(array $args): void
    {
        $this->requirePerm('accounting.manage');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT * FROM accounts WHERE id = ?', [$id]);
        if (!$row) $this->notFound();

        $hasChildren = (bool) Database::val('SELECT COUNT(*) FROM accounts WHERE parent_id = ?', [$id]);
        $hasLines = (bool) Database::val('SELECT COUNT(*) FROM voucher_lines WHERE account_id = ?', [$id]);
        if ($hasChildren || $hasLines) {
            flash_set('error', 'این حساب قابل حذف نیست' . ($hasChildren ? ' (زیرشاخه دارد)' : ' (در اسناد استفاده شده)') . '.');
        } else {
            Database::delete('accounts', 'id = ?', [$id]);
            flash_set('success', 'حساب حذف شد.');
        }
        $this->redirect('accounting/accounts');
    }

    /* ==================== اسناد حسابداری ==================== */

    public function vouchersAction(): void
    {
        $this->requirePerm('accounting.view');

        $source = (string) $this->input('source', '');
        $status = (string) $this->input('status', '');
        $from = jinput_to_gregorian((string) $this->input('date_from', ''));
        $to = jinput_to_gregorian((string) $this->input('date_to', ''));

        $where = '1=1';
        $params = [];
        if ($source !== '') { $where .= ' AND v.source = ?'; $params[] = $source; }
        if (in_array($status, ['draft', 'posted'], true)) { $where .= ' AND v.status = ?'; $params[] = $status; }
        if ($from) { $where .= ' AND v.voucher_date >= ?'; $params[] = $from; }
        if ($to) { $where .= ' AND v.voucher_date <= ?'; $params[] = $to; }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $total = (int) Database::val("SELECT COUNT(*) FROM vouchers v WHERE $where", $params);
        $rows = Database::all(
            "SELECT v.*,
                    (SELECT COALESCE(SUM(vl.debit), 0) FROM voucher_lines vl WHERE vl.voucher_id = v.id) AS total_debit
             FROM vouchers v WHERE $where
             ORDER BY v.voucher_date DESC, v.id DESC
             LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            $params
        );

        $this->view('accounting/vouchers', [
            'title'  => 'سند حسابداری',
            'rows'   => $rows,
            'total'  => $total,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $perPage)),
            'source' => $source,
            'status' => $status,
            'from'   => (string) $this->input('date_from', ''),
            'to'     => (string) $this->input('date_to', ''),
        ]);
    }

    public function voucherFormAction(array $args): void
    {
        $this->requirePerm('accounting.edit');
        $id = (int) ($args[0] ?? 0);
        $voucher = null;
        $lines = [];
        if ($id) {
            $voucher = Database::one('SELECT * FROM vouchers WHERE id = ?', [$id]);
            if (!$voucher) $this->notFound();
            if ($voucher['source'] !== 'manual') {
                flash_set('error', 'فقط اسناد دستی قابل ویرایش هستند.');
                $this->redirect('accounting/vouchers');
            }
            $lines = Database::all('SELECT * FROM voucher_lines WHERE voucher_id = ? ORDER BY id ASC', [$id]);
        }

        $accounts = Database::all("SELECT id, code, name FROM accounts WHERE is_group = 0 ORDER BY code ASC");
        $this->view('accounting/voucher-form', [
            'title'    => $voucher ? 'ویرایش سند ' . $voucher['voucher_no'] : 'سند حسابداری جدید',
            'voucher'  => $voucher,
            'lines'    => $lines,
            'accounts' => $accounts,
            'rowCount' => 8,
        ]);
    }

    public function voucherStoreAction(array $args): void
    {
        $this->requirePerm('accounting.edit');
        $id = (int) ($args[0] ?? 0);
        $date = jinput_to_gregorian((string) $this->input('voucher_date', ''));
        $description = (string) $this->input('description', '');

        if (!$date) {
            flash_set('error', 'تاریخ سند نامعتبر است (نمونه: ۱۴۰۴/۰۵/۰۱).');
            $this->redirect($id ? 'accounting/voucher-form/' . $id : 'accounting/voucher-form');
        }

        $lines = $this->buildLines();
        if (count($lines) < 2 || abs(array_sum(array_column($lines, 'debit')) - array_sum(array_column($lines, 'credit'))) > 0.01
            || array_sum(array_column($lines, 'debit')) == 0) {
            flash_set('error', 'سند تراز نیست یا خطی ندارد.');
            $this->redirect($id ? 'accounting/voucher-form/' . $id : 'accounting/voucher-form');
        }

        $userId = (int) (Auth::user()['id'] ?? 0) ?: null;

        if ($id) {
            $voucher = Database::one('SELECT * FROM vouchers WHERE id = ?', [$id]);
            if (!$voucher) $this->notFound();
            if ($voucher['source'] !== 'manual') {
                flash_set('error', 'فقط اسناد دستی قابل ویرایش هستند.');
                $this->redirect('accounting/vouchers');
            }
            // ویرایش سند دستی: فیلدها بروزرسانی، شماره سند ثابت می‌ماند و خطوط از نو نوشته می‌شوند
            Database::update('vouchers', ['voucher_date' => $date, 'description' => $description], 'id = ?', [$id]);
            Database::delete('voucher_lines', 'voucher_id = ?', [$id]);
            foreach ($lines as $l) {
                Database::insert('voucher_lines', [
                    'voucher_id'  => $id,
                    'account_id'  => (int) $l['account_id'],
                    'debit'       => round((float) $l['debit'], 2),
                    'credit'      => round((float) $l['credit'], 2),
                    'description' => mb_substr((string) $l['description'], 0, 190),
                ]);
            }
            flash_set('success', 'سند ' . $voucher['voucher_no'] . ' بروزرسانی شد.');
            $this->redirect('accounting/voucher-view/' . $id);
        }

        $voucherId = AccountingService::postVoucher($date, $description, $lines, 'manual', null, null, $userId);
        if (!$voucherId) {
            flash_set('error', 'سند تراز نیست یا خطی ندارد.');
            $this->redirect('accounting/voucher-form');
        }
        flash_set('success', 'سند جدید ثبت شد.');
        $this->redirect('accounting/voucher-view/' . $voucherId);
    }

    public function voucherViewAction(array $args): void
    {
        $this->requirePerm('accounting.view');
        $id = (int) ($args[0] ?? 0);
        $voucher = Database::one('SELECT * FROM vouchers WHERE id = ?', [$id]);
        if (!$voucher) $this->notFound();

        $lines = Database::all(
            'SELECT vl.*, a.code AS account_code, a.name AS account_name
             FROM voucher_lines vl JOIN accounts a ON a.id = vl.account_id
             WHERE vl.voucher_id = ? ORDER BY vl.id ASC',
            [$id]
        );

        $this->view('accounting/voucher-view', [
            'title' => 'سند ' . $voucher['voucher_no'],
            'voucher' => $voucher,
            'lines' => $lines,
        ]);
    }

    public function voucherDeleteAction(array $args): void
    {
        $this->requirePerm('accounting.manage');
        $id = (int) ($args[0] ?? 0);
        $voucher = Database::one('SELECT * FROM vouchers WHERE id = ?', [$id]);
        if (!$voucher) $this->notFound();

        if ($voucher['source'] !== 'manual') {
            flash_set('error', 'اسناد خودکار (مرتبط با فاکتور/خزانه) قابل حذف نیستند.');
            $this->redirect('accounting/vouchers');
        }
        Database::delete('voucher_lines', 'voucher_id = ?', [$id]);
        Database::delete('vouchers', 'id = ?', [$id]);
        flash_set('success', 'سند ' . $voucher['voucher_no'] . ' حذف شد.');
        $this->redirect('accounting/vouchers');
    }

    /* ==================== دفتر معین ==================== */

    public function ledgerAction(): void
    {
        $this->requirePerm('accounting.view');

        $accountId = (int) $this->input('account_id', 0);
        $fromInput = (string) $this->input('date_from', '');
        $toInput = (string) $this->input('date_to', '');
        $from = jinput_to_gregorian($fromInput);
        $to = jinput_to_gregorian($toInput);

        $accounts = Database::all("SELECT id, code, name, type FROM accounts WHERE is_group = 0 ORDER BY code ASC");
        $account = null;
        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $finalBalance = 0.0;

        if ($accountId) {
            $account = Database::one('SELECT * FROM accounts WHERE id = ? AND is_group = 0', [$accountId]);
            if ($account) {
                $where = "vl.account_id = ? AND v.status = 'posted'";
                $params = [$accountId];
                if ($from) { $where .= ' AND v.voucher_date >= ?'; $params[] = $from; }
                if ($to) { $where .= ' AND v.voucher_date <= ?'; $params[] = $to; }

                $rows = Database::all(
                    "SELECT vl.debit, vl.credit, vl.description AS line_desc,
                            v.id AS voucher_id, v.voucher_no, v.voucher_date, v.description AS voucher_desc
                     FROM voucher_lines vl JOIN vouchers v ON v.id = vl.voucher_id
                     WHERE $where
                     ORDER BY v.voucher_date ASC, vl.id ASC",
                    $params
                );

                // مانده: دارایی/هزینه ← بدهکار-بستانکار | بدهی/درآمد/حقوق صاحبان سهام ← بستانکار-بدهکار
                $debitPositive = in_array($account['type'], ['asset', 'expense'], true);
                $balance = 0.0;
                foreach ($rows as &$r) {
                    $totalDebit += (float) $r['debit'];
                    $totalCredit += (float) $r['credit'];
                    $delta = $debitPositive ? ((float) $r['debit'] - (float) $r['credit']) : ((float) $r['credit'] - (float) $r['debit']);
                    $balance += $delta;
                    $r['balance'] = $balance;
                }
                unset($r);
                $finalBalance = $balance;
            }
        }

        $this->view('accounting/ledger', [
            'title'        => 'دفتر کل و معین',
            'accounts'     => $accounts,
            'account'      => $account,
            'accountId'    => $accountId,
            'rows'         => $rows,
            'totalDebit'   => $totalDebit,
            'totalCredit'  => $totalCredit,
            'finalBalance' => $finalBalance,
            'from'         => $fromInput,
            'to'           => $toInput,
            'types'        => self::TYPES,
        ]);
    }

    /* ==================== private ==================== */

    /** درخت حساب‌ها با عمق و نام والد */
    private function buildTree(array $all): array
    {
        $map = [];
        foreach ($all as $a) $map[(int) $a['id']] = $a;
        $children = [];
        foreach ($all as $a) {
            $pid = $a['parent_id'] !== null ? (int) $a['parent_id'] : 0;
            if ($pid && !isset($map[$pid])) $pid = 0;
            $children[$pid][] = $a;
        }
        $flat = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$flat, $children, $map): void {
            foreach ($children[$parentId] ?? [] as $a) {
                $a['depth'] = $depth;
                $a['parent_name'] = $a['parent_id'] !== null ? ($map[(int) $a['parent_id']]['name'] ?? '') : '';
                $flat[] = $a;
                $walk((int) $a['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $flat;
    }

    /** همه زیرشاخه‌های یک حساب */
    private function descendantIds(int $id, array $all): array
    {
        $out = [$id];
        $stack = [$id];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($all as $a) {
                $pid = (int) ($a['parent_id'] ?? 0);
                if ($pid === $cur && !in_array((int) $a['id'], $out, true)) {
                    $out[] = (int) $a['id'];
                    $stack[] = (int) $a['id'];
                }
            }
        }
        return $out;
    }

    /** خطوط سند از POST (آرایه‌های موازی) — فقط حساب‌های غیرگروهی معتبر */
    private function buildLines(): array
    {
        $accIds = (array) ($_POST['line_account_id'] ?? []);
        $debits = (array) ($_POST['line_debit'] ?? []);
        $credits = (array) ($_POST['line_credit'] ?? []);
        $descs = (array) ($_POST['line_desc'] ?? []);

        $validAccounts = [];
        foreach (Database::all("SELECT id FROM accounts WHERE is_group = 0") as $a) {
            $validAccounts[(int) $a['id']] = true;
        }

        $lines = [];
        foreach ($accIds as $i => $accId) {
            $accId = (int) $accId;
            if (!$accId || empty($validAccounts[$accId])) continue;
            $debit = $this->parseMoney((string) ($debits[$i] ?? '0'));
            $credit = $this->parseMoney((string) ($credits[$i] ?? '0'));
            if ($debit <= 0 && $credit <= 0) continue;
            $lines[] = [
                'account_id'  => $accId,
                'debit'       => $debit,
                'credit'      => $credit,
                'description' => trim((string) ($descs[$i] ?? '')),
            ];
        }
        return $lines;
    }

    /** «۱٬۲۳۴,۵۶» یا «1234.5» → float */
    private function parseMoney(string $raw): float
    {
        $raw = fa_num_reverse(str_replace([',', '٬', ' '], '', trim($raw)));
        return (float) ($raw === '' ? 0 : $raw);
    }
}
