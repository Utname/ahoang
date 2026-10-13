<?php

declare(strict_types=1);

use Nette\Database\Explorer;
use Nette\Database\Row;

/**
 * Výkup lahví (bottle / crate deposit buy-back), see design_handoff_fikko/
 * vykup-lahvi/README-TICH-HOP.md. Every method works inside one branch. Amounts are
 * always worked out here from vykup_items, never taken from the browser. Dates and
 * times are Prague time: a voucher is valid only on its day of issue.
 */
final class VykupService
{
	public const MAX_REPRINTS = 2;
	public const MAX_QTY = 9999;
	public const TIMEZONE = 'Europe/Prague';


	public function __construct(private Explorer $database)
	{
	}


	public static function now(): DateTimeImmutable
	{
		return new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
	}


	public static function today(): string
	{
		return self::now()->format('Y-m-d');
	}


	// ---------- catalogue / branch ----------

	/** @return list<Nette\Database\Table\ActiveRow> */
	public function items(): array
	{
		return array_values($this->database->table('vykup_items')->where('active', 1)->order('sort')->fetchAll());
	}


	/**
	 * Voucher header of the branch: name, address, company, IČO, number prefix.
	 *
	 * @return array{id: int, name: string, title: string, address: string, company: string, ico: string, code: string}
	 */
	public function branch(int $branchesId): array
	{
		$row = $this->database->query(
			"SELECT b.id, b.vykup_code, m.name, m.street, m.city, m.zip FROM branches b
			 LEFT JOIN branches_mld m ON m.branches_id = b.id AND m.languages_id = 'cs' WHERE b.id = ?",
			$branchesId,
		)->fetch();
		if (!$row) {
			throw new DomainException('Branch not found');
		}
		$settings = $this->database->table('settings')->where('id', ['vykup_company', 'vykup_ico'])->fetchPairs('id', 'value');
		$name = trim((string)$row->name);
		// "Most - Vítězslava Nezvala" → title "FIKKO MARKET MOST - VÍTĚZSLAVA NEZVALA"
		return [
			'id' => (int)$row->id,
			'name' => $name,
			'title' => mb_strtoupper('Fikko market ' . $name),
			'address' => implode(', ', array_filter([trim((string)$row->street), trim(trim((string)$row->zip) . ' ' . trim((string)$row->city))])),
			'company' => (string)($settings['vykup_company'] ?? 'Fikko market s.r.o.'),
			'ico' => (string)($settings['vykup_ico'] ?? ''),
			'code' => (string)($row->vykup_code ?: 'B' . $row->id),
		];
	}


	/** Voucher header settings (Pracovníci tab): number prefix of the branch, company, IČO. */
	public function saveHeader(int $branchesId, string $code, string $company, string $ico): void
	{
		$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
		if ($code === '' || strlen($code) > 6) {
			throw new DomainException('admin.vykup.err.code');
		}
		$taken = $this->database->table('branches')->where('vykup_code', $code)->where('id != ?', $branchesId)->count('*');
		if ($taken) {
			throw new DomainException('admin.vykup.err.codeTaken');
		}
		$this->database->table('branches')->where('id', $branchesId)->update(['vykup_code' => $code]);
		foreach (['vykup_company' => trim($company), 'vykup_ico' => trim($ico)] as $key => $value) {
			$this->database->query('INSERT INTO settings (id, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', $key, $value);
		}
	}


	// ---------- staff ----------

	/** @return list<Nette\Database\Table\ActiveRow> */
	public function staff(int $branchesId, bool $onlyActive): array
	{
		$selection = $this->database->table('vykup_staff')->where('branches_id', $branchesId)->order('name');
		if ($onlyActive) {
			$selection->where('active', 1);
		}

		return array_values($selection->fetchAll());
	}


	public function addStaff(int $branchesId, string $name): void
	{
		$name = trim($name);
		if ($name === '') {
			throw new DomainException('admin.vykup.err.emptyName');
		}
		if ($this->database->table('vykup_staff')->where(['branches_id' => $branchesId, 'name' => $name])->count('*')) {
			throw new DomainException('admin.vykup.err.nameTaken');
		}
		$this->database->table('vykup_staff')->insert(['branches_id' => $branchesId, 'name' => $name, 'created_at' => self::now()->format('Y-m-d H:i:s')]);
	}


	public function renameStaff(int $branchesId, int $staffId, string $name): void
	{
		$name = trim($name);
		if ($name === '') {
			throw new DomainException('admin.vykup.err.emptyName');
		}
		if ($this->database->table('vykup_staff')->where(['branches_id' => $branchesId, 'name' => $name])->where('id != ?', $staffId)->count('*')) {
			throw new DomainException('admin.vykup.err.nameTaken');
		}
		$this->database->table('vykup_staff')->where(['id' => $staffId, 'branches_id' => $branchesId])->update(['name' => $name]);
	}


	/** Hide (never delete: history keeps the name); a branch keeps at least one active person. */
	public function setStaffActive(int $branchesId, int $staffId, bool $active): void
	{
		if (!$active) {
			$left = $this->database->table('vykup_staff')->where(['branches_id' => $branchesId, 'active' => 1])->where('id != ?', $staffId)->count('*');
			if ($left < 1) {
				throw new DomainException('admin.vykup.err.lastStaff');
			}
		}
		$this->database->table('vykup_staff')->where(['id' => $staffId, 'branches_id' => $branchesId])->update(['active' => (int)$active]);
	}


	// ---------- vouchers ----------

	/** Number the next voucher of today will get (preview only). */
	public function nextVoucherNo(int $branchesId): string
	{
		$today = self::today();
		$seq = (int)$this->database->query('SELECT COALESCE(MAX(day_seq), 0) + 1 FROM vykup_vouchers WHERE branches_id = ? AND issue_date = ?', $branchesId, $today)->fetchField();

		return $this->voucherNo($this->branch($branchesId)['code'], $today, $seq);
	}


	private function voucherNo(string $code, string $date, int $seq): string
	{
		return sprintf('%s-V%s-%03d', $code, date('ymd', (int)strtotime($date)), $seq);
	}


	/**
	 * @param array<string, int|string> $qty item id => count, e.g. ['lahev' => 24, 'prepravka' => 1]
	 * @return int voucher id
	 */
	public function createVoucher(int $branchesId, int $staffId, array $qty, ?int $adminUserId): int
	{
		$staff = $this->database->table('vykup_staff')->where(['id' => $staffId, 'branches_id' => $branchesId, 'active' => 1])->fetch();
		if (!$staff) {
			throw new DomainException('admin.vykup.err.staff');
		}

		$lines = [];
		foreach ($this->items() as $item) {
			$n = (int)($qty[$item->id] ?? 0);
			if ($n < 0 || $n > self::MAX_QTY) {
				throw new DomainException('admin.vykup.err.qty');
			}
			if ($n > 0) {
				$lines[] = [$item, $n];
			}
		}
		if (!$lines) {
			throw new DomainException('admin.vykup.err.empty');
		}

		$branch = $this->branch($branchesId);
		$now = self::now();
		$today = $now->format('Y-m-d');
		$this->database->beginTransaction();
		try {
			// locks today's rows of the branch: two counters printing at once get different numbers
			$seq = (int)$this->database->query(
				'SELECT COALESCE(MAX(day_seq), 0) + 1 FROM vykup_vouchers WHERE branches_id = ? AND issue_date = ? FOR UPDATE',
				$branchesId, $today,
			)->fetchField();
			$total = 0;
			foreach ($lines as [$item, $n]) {
				$total += $n * (int)$item->price_kc;
			}
			$voucher = $this->database->table('vykup_vouchers')->insert([
				'branches_id' => $branchesId,
				'issue_date' => $today,
				'day_seq' => $seq,
				'voucher_no' => $this->voucherNo($branch['code'], $today, $seq),
				'vykup_staff_id' => $staffId,
				'admin_users_id' => $adminUserId,
				'total_kc' => $total,
				'created_at' => $now->format('Y-m-d H:i:s'),
			]);
			foreach ($lines as [$item, $n]) {
				$this->database->table('vykup_voucher_lines')->insert([
					'vykup_vouchers_id' => $voucher->id,
					'vykup_items_id' => $item->id,
					'qty' => $n,
					'price_kc' => $item->price_kc,
				]);
			}
			$this->database->commit();
		}
		catch (Throwable $e) {
			$this->database->rollBack();
			throw $e;
		}

		return (int)$voucher->id;
	}


	private function voucher(int $branchesId, int $voucherId): Nette\Database\Table\ActiveRow
	{
		$voucher = $this->database->table('vykup_vouchers')->where(['id' => $voucherId, 'branches_id' => $branchesId])->fetch();
		if (!$voucher) {
			throw new DomainException('admin.vykup.err.notFound');
		}

		return $voucher;
	}


	/** Everything the 80mm voucher prints (always Czech). */
	public function voucherForPrint(int $branchesId, int $voucherId, bool $duplicate = FALSE): array
	{
		$voucher = $this->voucher($branchesId, $voucherId);
		$lines = [];
		foreach ($this->database->table('vykup_voucher_lines')->where('vykup_vouchers_id', $voucher->id)->order('vykup_items.sort') as $line) {
			$item = $line->ref('vykup_items', 'vykup_items_id');
			$lines[] = [
				'id' => $item->id,
				'name' => $item->name_cs,
				'qty' => (int)$line->qty,
				'price' => (int)$line->price_kc,
				'sum' => (int)$line->qty * (int)$line->price_kc,
				'ean' => $item->ean8,
				'svg' => VykupEan8::svg($item->ean8),
			];
		}
		$reprints = array_values($this->database->table('vykup_reprints')->where('vykup_vouchers_id', $voucher->id)->order('printed_at')->fetchAll());
		$last = $reprints ? end($reprints) : NULL;

		return [
			'branch' => $this->branch($branchesId),
			'voucher' => $voucher,
			'staff' => $voucher->ref('vykup_staff', 'vykup_staff_id')?->name ?? '',
			'lines' => $lines,
			'total' => (int)$voucher->total_kc,
			'dup' => $duplicate && $last ? ['n' => count($reprints), 'max' => self::MAX_REPRINTS, 'time' => $last->printed_at->format('H:i')] : NULL,
		];
	}


	/** Reprint: a valid voucher, on its day of issue, at most MAX_REPRINTS times. */
	public function canReprint(int $voucherId, string $status, string $issueDate): bool
	{
		return $status === 'valid'
			&& $issueDate === self::today()
			&& $this->database->table('vykup_reprints')->where('vykup_vouchers_id', $voucherId)->count('*') < self::MAX_REPRINTS;
	}


	public function reprint(int $branchesId, int $voucherId, ?int $staffId, ?int $adminUserId): void
	{
		$voucher = $this->voucher($branchesId, $voucherId);
		if (!$this->canReprint((int)$voucher->id, (string)$voucher->status, $voucher->issue_date->format('Y-m-d'))) {
			throw new DomainException('admin.vykup.err.reprint');
		}
		$this->database->table('vykup_reprints')->insert([
			'vykup_vouchers_id' => $voucherId,
			'vykup_staff_id' => $staffId ?: NULL,
			'admin_users_id' => $adminUserId,
			'printed_at' => self::now()->format('Y-m-d H:i:s'),
		]);
	}


	/** Storno: never deleted, only marked; not counted in any total. */
	public function void(int $branchesId, int $voucherId, ?int $adminUserId): void
	{
		$this->database->table('vykup_vouchers')->where(['id' => $voucherId, 'branches_id' => $branchesId, 'status' => 'valid'])
			->update(['status' => 'void', 'voided_at' => self::now()->format('Y-m-d H:i:s'), 'voided_by' => $adminUserId]);
	}


	/** Undo a storno made by mistake – only on the voucher's day of issue. */
	public function restore(int $branchesId, int $voucherId): void
	{
		$voucher = $this->voucher($branchesId, $voucherId);
		if ($voucher->issue_date->format('Y-m-d') !== self::today()) {
			throw new DomainException('admin.vykup.err.restore');
		}
		$voucher->update(['status' => 'valid', 'voided_at' => NULL, 'voided_by' => NULL]);
	}


	// ---------- history / reports ----------

	private const QTY_COLUMNS = "COALESCE(SUM(CASE WHEN l.vykup_items_id = 'lahev' THEN l.qty ELSE 0 END), 0) AS lahev,
		COALESCE(SUM(CASE WHEN l.vykup_items_id = 'prepravka' THEN l.qty ELSE 0 END), 0) AS prepravka";


	/** One day, optionally one staff member and a search in number / name. @return list<Row> */
	public function history(int $branchesId, string $date, ?int $staffId, string $q = ''): array
	{
		$sql = 'SELECT v.*, s.name AS staff_name, ' . self::QTY_COLUMNS . ',
				(SELECT COUNT(*) FROM vykup_reprints r WHERE r.vykup_vouchers_id = v.id) AS reprints
			FROM vykup_vouchers v
			JOIN vykup_staff s ON s.id = v.vykup_staff_id
			LEFT JOIN vykup_voucher_lines l ON l.vykup_vouchers_id = v.id
			WHERE v.branches_id = ? AND v.issue_date = ?';
		$args = [$branchesId, $date];
		if ($staffId) {
			$sql .= ' AND v.vykup_staff_id = ?';
			$args[] = $staffId;
		}
		if ($q !== '') {
			$sql .= ' AND (v.voucher_no LIKE ? OR s.name LIKE ?)';
			$args[] = "%$q%";
			$args[] = "%$q%";
		}
		$sql .= ' GROUP BY v.id ORDER BY v.day_seq DESC';

		return $this->database->query($sql, ...$args)->fetchAll();
	}


	/**
	 * Valid vouchers of a date range: per day, per staff member, total, reprints, stornos.
	 *
	 * @return array{byDay: list<Row>, byStaff: list<Row>, total: Row, dups: int, voided: int}
	 */
	public function period(int $branchesId, string $from, string $to): array
	{
		$base = "FROM vykup_vouchers v
			JOIN vykup_voucher_lines l ON l.vykup_vouchers_id = v.id
			JOIN vykup_staff s ON s.id = v.vykup_staff_id
			WHERE v.branches_id = ? AND v.status = 'valid' AND v.issue_date BETWEEN ? AND ?";
		$cols = 'COUNT(DISTINCT v.id) AS vouchers, ' . self::QTY_COLUMNS . ', COALESCE(SUM(l.qty * l.price_kc), 0) AS kc';

		return [
			'byDay' => $this->database->query("SELECT v.issue_date AS d, $cols $base GROUP BY v.issue_date ORDER BY v.issue_date", $branchesId, $from, $to)->fetchAll(),
			'byStaff' => $this->database->query("SELECT s.name, $cols $base GROUP BY s.id, s.name ORDER BY kc DESC", $branchesId, $from, $to)->fetchAll(),
			'total' => $this->database->query("SELECT $cols $base", $branchesId, $from, $to)->fetch(),
			'dups' => (int)$this->database->query(
				'SELECT COUNT(*) FROM vykup_reprints r JOIN vykup_vouchers v ON v.id = r.vykup_vouchers_id WHERE v.branches_id = ? AND v.issue_date BETWEEN ? AND ?',
				$branchesId, $from, $to,
			)->fetchField(),
			'voided' => (int)$this->database->query(
				"SELECT COUNT(*) FROM vykup_vouchers WHERE branches_id = ? AND status = 'void' AND issue_date BETWEEN ? AND ?",
				$branchesId, $from, $to,
			)->fetchField(),
		];
	}


	public function dailyClose(int $branchesId, string $date): ?Nette\Database\Table\ActiveRow
	{
		return $this->database->table('vykup_daily_close')->where(['branches_id' => $branchesId, 'close_date' => $date])->fetch();
	}


	/** Compares the app's day totals with GoKasa's; saved (last one wins) for the history. */
	public function saveDailyClose(int $branchesId, string $date, array $pos, ?int $adminUserId): bool
	{
		$total = $this->period($branchesId, $date, $date)['total'];
		$app = ['lahev' => (int)$total->lahev, 'prepravka' => (int)$total->prepravka, 'kc' => (int)$total->kc];
		$ok = $app['lahev'] === (int)$pos['lahev'] && $app['prepravka'] === (int)$pos['prepravka'] && $app['kc'] === (int)$pos['kc'];
		$this->database->query('REPLACE INTO vykup_daily_close ?', [
			'branches_id' => $branchesId,
			'close_date' => $date,
			'app_lahev' => $app['lahev'], 'app_prepravka' => $app['prepravka'], 'app_kc' => $app['kc'],
			'pos_lahev' => (int)$pos['lahev'], 'pos_prepravka' => (int)$pos['prepravka'], 'pos_kc' => (int)$pos['kc'],
			'ok' => (int)$ok,
			'admin_users_id' => $adminUserId,
			'closed_at' => self::now()->format('Y-m-d H:i:s'),
		]);

		return $ok;
	}


	/** CSV for ČOI / accounting: ";" separated, UTF-8 with BOM (Czech Excel opens it right). */
	public function csv(int $branchesId, string $from, string $to): string
	{
		$rows = $this->database->query(
			'SELECT v.voucher_no, DATE_FORMAT(v.issue_date, "%d.%m.%Y") AS d, DATE_FORMAT(v.created_at, "%H:%i") AS t, s.name,
				' . self::QTY_COLUMNS . ', v.total_kc, v.status,
				(SELECT COUNT(*) FROM vykup_reprints r WHERE r.vykup_vouchers_id = v.id) AS dotisky
			FROM vykup_vouchers v
			JOIN vykup_staff s ON s.id = v.vykup_staff_id
			LEFT JOIN vykup_voucher_lines l ON l.vykup_vouchers_id = v.id
			WHERE v.branches_id = ? AND v.issue_date BETWEEN ? AND ?
			GROUP BY v.id ORDER BY v.issue_date, v.day_seq',
			$branchesId, $from, $to,
		);
		$out = "\xEF\xBB\xBFpoukaz;datum;cas;prijal;lahve_ks;prepravky_ks;castka_kc;stav;dotisky\n";
		foreach ($rows as $r) {
			$name = str_replace([';', "\n", "\r"], ' ', (string)$r->name);
			$out .= implode(';', [$r->voucher_no, $r->d, $r->t, $name, (int)$r->lahev, (int)$r->prepravka, (int)$r->total_kc,
				$r->status === 'void' ? 'storno' : 'platny', (int)$r->dotisky]) . "\n";
		}

		return $out;
	}
}
