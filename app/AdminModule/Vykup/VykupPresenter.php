<?php

namespace App\AdminModule\Presenters;

use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Form;

/**
 * Výkup lahví (design_handoff_fikko/Vykup lahvi.dc.html):
 *   default    Příjem      – staff, bottles / crates, print a voucher
 *   historie   Historie    – one day, reprint / storno
 *   uzaverka   Uzávěrka    – today against the GoKasa day report
 *   obdobi     Období      – date range, CSV export
 *   pracovnici Pracovníci  – staff of the branch, voucher header
 *   print      the 80mm voucher (own page, prints itself)
 * Everything works in one branch: the admin's chosen branch (session), limited to
 * the branches the admin may see. Business rules live in \VykupService.
 */
class VykupPresenter extends BaseModulePresenter
{
	/** @var \VykupService @inject */
	public $vykup;

	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	/** @var array<int, string> branches this admin may use */
	protected $branchesPairs = [];

	/** @var int */
	protected $branchesId = 0;


	protected function startup()
	{
		parent::startup();
		$this->checkPermission('list');

		$selection = $this->branchesRepo->findAll()->order('pos_order');
		if (!$this->adminUser->is_all_branches_permission) {
			$ids = $this->adminUser->getBranchesIds();
			$selection->where('id', $ids ?: [0]);
		}
		$this->branchesPairs = $selection->fetchPairs('id', 'name');
		if (!$this->branchesPairs) {
			$this->redirect('Homepage:');
		}

		$section = $this->getSession('vykup');
		$branchesId = (int)($section->branch ?? 0) ?: (int)($this->adminUser->default_branches_id ?? 0);
		if (!isset($this->branchesPairs[$branchesId])) {
			$branchesId = (int)array_key_first($this->branchesPairs);
		}
		$this->branchesId = $branchesId;
	}


	public function beforeRender()
	{
		parent::beforeRender();

		$this->template->vkBranchesPairs = $this->branchesPairs;
		$this->template->vkBranchesId = $this->branchesId;
		$this->template->vkBranch = $this->vykup->branch($this->branchesId);
		$this->template->vkCanEdit = $this->hasPermission('edit');
		$this->template->vkToday = \VykupService::today();
		// item names in the admin language (the voucher itself is always Czech)
		$this->template->vkVi = str_starts_with((string)$this->translator->getLocale(), 'vi');
	}


	/** The base beforeRender() wants an edit form; this module has no repository. */
	protected function createComponentEditForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		return $form;
	}


	public function handleBranch(int $id): void
	{
		if (isset($this->branchesPairs[$id])) {
			$this->getSession('vykup')->branch = $id;
		}
		$this->redirect('this', ['issued' => NULL]);
	}


	private function adminUserId(): ?int
	{
		return isset($this->adminUser->id) ? (int)$this->adminUser->id : NULL;
	}


	private function failMessage(\Throwable $e): void
	{
		$message = $e->getMessage();
		$this->flashMessage(str_starts_with($message, 'admin.') ? $this->trans($message) : $message, 'danger');
	}


	// ---------- Příjem ----------

	public function actionDefault(?int $issued = NULL)
	{
		$staff = $this->vykup->staff($this->branchesId, TRUE);
		$lastStaff = (int)($this->getSession('vykup')->lastStaff ?? 0);
		$staffIds = array_map(static fn($s) => (int)$s->id, $staff);

		$items = $this->vykup->items();
		$selectedStaff = in_array($lastStaff, $staffIds, TRUE) ? $lastStaff : ($staffIds[0] ?? 0);
		$selectedStaffName = '';
		foreach ($staff as $person) {
			if ((int)$person->id === $selectedStaff) {
				$selectedStaffName = (string)$person->name;
			}
		}
		// preview of the next voucher: every item with 0, filled in by the page script
		$draftLines = [];
		foreach ($items as $item) {
			$draftLines[] = [
				'id' => $item->id, 'name' => $item->name_cs, 'qty' => 0, 'price' => (int)$item->price_kc, 'sum' => 0,
				'ean' => $item->ean8, 'svg' => \VykupEan8::svg($item->ean8),
			];
		}
		$now = \VykupService::now();

		$this->template->items = $items;
		$this->template->staff = $staff;
		$this->template->selectedStaff = $selectedStaff;
		$this->template->selectedStaffName = $selectedStaffName;
		$this->template->draftLines = $draftLines;
		$this->template->nowText = $now->format('d.m.Y H:i');
		$this->template->todayText = $now->format('d.m.Y');
		$this->template->nextNo = $this->vykup->nextVoucherNo($this->branchesId);
		$this->template->issued = NULL;
		$this->template->issuedCanReprint = FALSE;
		if ($issued) {
			try {
				$data = $this->vykup->voucherForPrint($this->branchesId, $issued);
				$this->template->issued = $data;
				$this->template->issuedCanReprint = $this->vykup->canReprint((int)$data['voucher']->id, (string)$data['voucher']->status, $data['voucher']->issue_date->format('Y-m-d'));
			}
			catch (\DomainException) {
			}
		}
	}


	protected function createComponentVoucherForm(): Form
	{
		$form = new Form();
		$form->addProtection();
		$form->addHidden('staff_id');
		foreach ($this->vykup->items() as $item) {
			$form->addText('qty_' . $item->id)->setHtmlType('number')->setDefaultValue(0);
		}
		$form->addSubmit('print');
		$form->onSuccess[] = function (Form $form, array $values): void {
			$qty = [];
			foreach ($this->vykup->items() as $item) {
				$qty[$item->id] = (int)($values['qty_' . $item->id] ?? 0);
			}
			try {
				$id = $this->vykup->createVoucher($this->branchesId, (int)$values['staff_id'], $qty, $this->adminUserId());
			}
			catch (\DomainException $e) {
				$this->failMessage($e);
				$this->redirect('default');
			}
			$this->getSession('vykup')->lastStaff = (int)$values['staff_id'];
			$this->redirect('print', ['id' => $id]);
		};

		return $form;
	}


	// ---------- voucher ----------

	public function actionPrint(int $id, bool $dup = FALSE, string $back = 'default')
	{
		try {
			$data = $this->vykup->voucherForPrint($this->branchesId, $id, $dup);
		}
		catch (\DomainException $e) {
			$this->failMessage($e);
			$this->redirect('default');
		}
		$this->setLayout(FALSE);
		$this->template->setParameters($data);
		$this->template->backUrl = $back === 'historie'
			? $this->link('historie', ['date' => $data['voucher']->issue_date->format('Y-m-d')])
			: $this->link('default', ['issued' => $id]);
	}


	public function handleReprint(int $id, string $back = 'default'): void
	{
		try {
			$this->vykup->reprint($this->branchesId, $id, (int)($this->getSession('vykup')->lastStaff ?? 0) ?: NULL, $this->adminUserId());
		}
		catch (\DomainException $e) {
			$this->failMessage($e);
			$this->redirect('this');
		}
		$this->redirect('print', ['id' => $id, 'dup' => TRUE, 'back' => $back]);
	}


	public function handleVoid(int $id): void
	{
		$this->vykup->void($this->branchesId, $id, $this->adminUserId());
		$this->redirect('this');
	}


	public function handleRestore(int $id): void
	{
		try {
			$this->vykup->restore($this->branchesId, $id);
		}
		catch (\DomainException $e) {
			$this->failMessage($e);
		}
		$this->redirect('this');
	}


	// ---------- Historie ----------

	public function actionHistorie(?string $date = NULL, ?int $staff = NULL, string $q = '')
	{
		$date = $this->validDate($date) ?? \VykupService::today();
		$rows = $this->vykup->history($this->branchesId, $date, $staff ?: NULL, trim($q));
		$total = ['count' => 0, 'lahev' => 0, 'prepravka' => 0, 'kc' => 0];
		$canReprint = [];
		foreach ($rows as $row) {
			$canReprint[$row->id] = $this->vykup->canReprint((int)$row->id, (string)$row->status, $row->issue_date->format('Y-m-d'));
			if ($row->status === 'valid') {
				$total['count']++;
				$total['lahev'] += (int)$row->lahev;
				$total['prepravka'] += (int)$row->prepravka;
				$total['kc'] += (int)$row->total_kc;
			}
		}
		$this->template->date = $date;
		$this->template->yesterday = (new \DateTimeImmutable(\VykupService::today()))->modify('-1 day')->format('Y-m-d');
		$this->template->staffId = $staff ?: NULL;
		$this->template->q = trim($q);
		$this->template->rows = $rows;
		$this->template->canReprint = $canReprint;
		$this->template->total = $total;
		$this->template->staffList = $this->vykup->staff($this->branchesId, FALSE);
	}


	// ---------- Období ----------

	public function actionObdobi(?string $from = NULL, ?string $to = NULL)
	{
		$today = new \DateTimeImmutable(\VykupService::today());
		$from = $this->validDate($from) ?? $today->format('Y-m-01');
		$to = $this->validDate($to) ?? $today->format('Y-m-d');
		if ($to < $from) {
			[$from, $to] = [$to, $from];
		}
		$monday = $today->modify('-' . ((int)$today->format('N') - 1) . ' days');
		$this->template->presets = [
			'week' => [$monday->format('Y-m-d'), $today->format('Y-m-d')],
			'month' => [$today->format('Y-m-01'), $today->format('Y-m-d')],
			'lastMonth' => [$today->modify('first day of last month')->format('Y-m-d'), $today->modify('last day of last month')->format('Y-m-d')],
			'days30' => [$today->modify('-29 days')->format('Y-m-d'), $today->format('Y-m-d')],
		];
		$this->template->from = $from;
		$this->template->to = $to;
		$this->template->report = $this->vykup->period($this->branchesId, $from, $to);
	}


	public function actionExport(?string $from = NULL, ?string $to = NULL): void
	{
		$from = $this->validDate($from) ?? \VykupService::today();
		$to = $this->validDate($to) ?? $from;
		if ($to < $from) {
			[$from, $to] = [$to, $from];
		}
		$code = $this->vykup->branch($this->branchesId)['code'];
		$this->getHttpResponse()->setContentType('text/csv', 'UTF-8');
		$this->getHttpResponse()->setHeader('Content-Disposition', 'attachment; filename="vykup-' . $code . '-' . $from . '_' . $to . '.csv"');
		$this->sendResponse(new TextResponse($this->vykup->csv($this->branchesId, $from, $to)));
	}


	// ---------- Uzávěrka ----------

	public function actionUzaverka()
	{
		$today = \VykupService::today();
		$this->template->report = $this->vykup->period($this->branchesId, $today, $today);
		$this->template->closed = $this->vykup->dailyClose($this->branchesId, $today);
	}


	protected function createComponentCloseForm(): Form
	{
		$form = new Form();
		$form->addProtection();
		foreach (['pos_lahev', 'pos_prepravka', 'pos_kc'] as $name) {
			$form->addText($name)->setHtmlType('number')->setRequired()->addRule(Form::INTEGER)->addRule(Form::MIN, NULL, 0);
		}
		$form->addSubmit('save');
		if ($closed = $this->vykup->dailyClose($this->branchesId, \VykupService::today())) {
			$form->setDefaults(['pos_lahev' => $closed->pos_lahev, 'pos_prepravka' => $closed->pos_prepravka, 'pos_kc' => $closed->pos_kc]);
		}
		$form->onSuccess[] = function (Form $form, array $values): void {
			$ok = $this->vykup->saveDailyClose($this->branchesId, \VykupService::today(), [
				'lahev' => (int)$values['pos_lahev'], 'prepravka' => (int)$values['pos_prepravka'], 'kc' => (int)$values['pos_kc'],
			], $this->adminUserId());
			$this->flashMessage($this->trans($ok ? 'admin.vykup.reconOk' : 'admin.vykup.reconBad'), $ok ? 'success' : 'danger');
			$this->redirect('uzaverka');
		};

		return $form;
	}


	// ---------- Pracovníci ----------

	public function actionPracovnici()
	{
		$this->template->staffList = $this->vykup->staff($this->branchesId, FALSE);
	}


	protected function createComponentStaffForm(): Form
	{
		$form = new Form();
		$form->addProtection();
		$form->addText('name')->setRequired()->setMaxLength(60);
		$form->addSubmit('add');
		$form->onSuccess[] = function (Form $form, array $values): void {
			$this->checkPermission('edit');
			try {
				$this->vykup->addStaff($this->branchesId, (string)$values['name']);
			}
			catch (\Throwable $e) {
				$this->failMessage($e);
			}
			$this->redirect('pracovnici');
		};

		return $form;
	}


	public function handleRenameStaff(int $id, string $name = ''): void
	{
		$this->checkPermission('edit');
		try {
			$this->vykup->renameStaff($this->branchesId, $id, $name);
		}
		catch (\Throwable $e) {
			$this->failMessage($e);
		}
		$this->redirect('pracovnici');
	}


	public function handleStaffActive(int $id, bool $active): void
	{
		$this->checkPermission('edit');
		try {
			$this->vykup->setStaffActive($this->branchesId, $id, $active);
		}
		catch (\Throwable $e) {
			$this->failMessage($e);
		}
		$this->redirect('pracovnici');
	}


	protected function createComponentHeaderForm(): Form
	{
		$form = new Form();
		$form->addProtection();
		$branch = $this->vykup->branch($this->branchesId);
		$form->addText('code')->setRequired()->setMaxLength(6)->setDefaultValue($branch['code']);
		$form->addText('company')->setMaxLength(120)->setDefaultValue($branch['company']);
		$form->addText('ico')->setMaxLength(20)->setDefaultValue($branch['ico']);
		$form->addSubmit('save');
		$form->onSuccess[] = function (Form $form, array $values): void {
			$this->checkPermission('edit');
			try {
				$this->vykup->saveHeader($this->branchesId, (string)$values['code'], (string)$values['company'], (string)$values['ico']);
				$this->flashMessage($this->trans('admin.vykup.saved'), 'success');
			}
			catch (\Throwable $e) {
				$this->failMessage($e);
			}
			$this->redirect('pracovnici');
		};

		return $form;
	}


	private function validDate(?string $date): ?string
	{
		return $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? $date : NULL;
	}
}
