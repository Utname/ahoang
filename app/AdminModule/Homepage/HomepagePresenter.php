<?php

namespace App\AdminModule\Presenters;

class HomepagePresenter extends BasePresenter
{
	/** @var \ProductsRepository @inject */
	public $productsRepo;

	/** @var \CategoriesRepository @inject */
	public $categoriesRepo;

	/** @var \ProductLabelsRepository @inject */
	public $productLabelsRepo;

	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	public function renderDefault()
	{
		$this->template->moduleName = $this->translator->translate('admin.modules.homepage');

		// 1. Thống kê số liệu (Metric cards)
		$totalProducts = 0;
		$activeProducts = 0;
		$inactiveProducts = 0;
		try {
			$totalProducts = $this->database->table('products')->count();
			// is_active is per-language (products_mld), not a column on products
			// itself, hence the join + current-content-language filter here.
			$activeProducts = $this->database->table('products_mld')
				->where('languages_id', $GLOBALS['lang'])
				->where('is_active', 1)
				->count();
			$inactiveProducts = $totalProducts - $activeProducts;
		} catch (\Exception $e) {}

		$totalCategories = 0;
		try {
			$totalCategories = $this->database->table('categories')->count();
		} catch (\Exception $e) {}

		$totalLabels = 0;
		try {
			$totalLabels = $this->database->table('product_labels')->count();
		} catch (\Exception $e) {}

		$totalBranches = 0;
		try {
			$totalBranches = $this->database->table('branches')->count();
		} catch (\Exception $e) {}

		$totalMessages = 0;
		try {
			// The admin's "Messages" module (MessagesPresenter) is backed by
			// MailingQueueRepository — there's no separate `messages` table.
			$totalMessages = $this->database->table('mailing_queue')->count();
		} catch (\Exception $e) {}

		$totalNewsletterEmails = 0;
		try {
			$totalNewsletterEmails = $this->database->table('newsletter_emails')->count();
		} catch (\Exception $e) {}

		$this->template->stats = [
			'totalProducts' => $totalProducts,
			'activeProducts' => $activeProducts,
			'inactiveProducts' => $inactiveProducts,
			'totalCategories' => $totalCategories,
			'totalLabels' => $totalLabels,
			'totalBranches' => $totalBranches,
			'totalMessages' => $totalMessages,
			'totalNewsletterEmails' => $totalNewsletterEmails,
		];

		// 2. Danh sách sản phẩm mới cập nhật gần đây (Recent Products)
		$recentProducts = [];
		try {
			$recentProducts = $this->productsRepo->findAll()
				->order('products.created_at DESC, products.id DESC')
				->limit(7)
				->fetchAll();
		} catch (\Exception $e) {
			try {
				$recentProducts = $this->database->table('products')
					->order('id DESC')
					->limit(7)
					->fetchAll();
			} catch (\Exception $e2) {}
		}
		$this->template->recentProducts = $recentProducts;

		// 3. Phân bổ danh mục chính (Categories breakdown)
		$topCategories = [];
		try {
			$categoriesList = $this->categoriesRepo->findAll()
				->where('pos_level', 1)
				->order('pos_left ASC')
				->limit(6)
				->fetchAll();

			foreach ($categoriesList as $cat) {
				$count = 0;
				try {
					$count = $this->database->table('products_categories_rel')
						->where('categories_id', $cat->id)
						->count();
				} catch (\Exception $e) {}
				$topCategories[] = [
					'id' => $cat->id,
					'name' => $cat->name,
					'count' => $count,
				];
			}
		} catch (\Exception $e) {}
		$this->template->topCategories = $topCategories;

		// 4. Thông tin hệ thống
		$this->template->systemInfo = [
			'phpVersion' => PHP_VERSION,
			'serverTime' => date('H:i - d/m/Y'),
			'defaultCurrency' => $this->defaultCurrency ? $this->defaultCurrency->name : 'CZK',
			'activeLocale' => $this->locale,
		];
	}
}
