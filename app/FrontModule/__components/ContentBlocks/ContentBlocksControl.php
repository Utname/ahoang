<?php

namespace App\FrontModule\Components;

use App\FrontModule\Presenters\BasePresenter;
use App\FrontModule\Traits\ContentBlocksControlFormsTrait;
use BaseEntity;
use BaseRepository;
use BranchesRepository;
use CacheService;
use CacheTags;
use CategoriesRepository;
use ContentBlocksRepository;
use ContentsEntity;
use CtaRepository;
use EmailTemplatesRepository;
use FaqRepository;
use InfoBoxesRepository;
use LinksRepository;
use MailingService;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Utils\Html;
use PartnersRepository;
use ProductsRepository;
use PromoBoxesRepository;
use SlidersRepository;
use TeamRepository;
use TestimonialsRepository;
use Throwable;

class ContentBlocksControl extends BaseControl
{
	use ContentBlocksControlFormsTrait;

	/** @var ContentsEntity */
	protected ContentsEntity $content;

	/** @var array  */
	protected $cachedItems;


    /**
     * @param EmailTemplatesRepository $emailTemplatesRepo
     * @param ContentBlocksRepository $contentBlocksRepo
     * @param MailingService $mailingService
     * @param CacheService $cacheService
     * @param CtaRepository $ctaRepo
     * @param SlidersRepository $slidersRepo
     * @param TeamRepository $teamRepo
     * @param PartnersRepository $partnersRepo
     * @param PromoBoxesRepository $promoBoxesRepo
     * @param TestimonialsRepository $testimonialsRepo
     * @param FaqRepository $faqRepo
     * @param InfoBoxesRepository $infoBoxesRepo
     */
	public function __construct(
		protected EmailTemplatesRepository $emailTemplatesRepo,
		protected ContentBlocksRepository $contentBlocksRepo,
		protected MailingService $mailingService,
		protected CacheService $cacheService,
		protected CtaRepository $ctaRepo,
		protected SlidersRepository $slidersRepo,
		protected TeamRepository $teamRepo,
		protected PartnersRepository $partnersRepo,
		protected PromoBoxesRepository $promoBoxesRepo,
		protected TestimonialsRepository $testimonialsRepo,
		protected FaqRepository $faqRepo,
		protected InfoBoxesRepository $infoBoxesRepo,
        protected CategoriesRepository $categoriesRepository,
        protected ProductsRepository $productsRepository,
        protected BranchesRepository $branchesRepository,
        protected LinksRepository $linksRepository,
	)
	{
	}


	/**
	 * @param $params
	 * @return void
	 * @throws Throwable
	 */
	public function render($params = [])
	{
		$this->getTemplate()
			//cached content from modules
			->addFunction('getCta', function($itemIds = []) {
				return $this->getCachedItemsForTemplate($this->getCtaRepo(), [CacheTags::CTA], NULL, $itemIds);
			})
			->addFunction('getSliders', function($itemIds = []) {
				return $this->getCachedItemsForTemplate($this->getSlidersRepo(), [CacheTags::SLIDERS], 'pos_order', $itemIds);
			})
			->addFunction('getPartners', function($itemIds = []) {
				return $this->getCachedItemsForTemplate($this->getPartnersRepo(), [CacheTags::PARTNERS], 'pos_order', $itemIds);
			})
			->addFunction('getPromoBoxes', function($itemIds = []) {
				return $this->getCachedItemsForTemplate($this->getPromoBoxesRepo(), [CacheTags::PROMO_BOXES], 'pos_order', $itemIds);
			})
			->addFunction('getTeam', function($itemIds = []) {
				return $this->getCachedItemsForTemplate($this->getTeamRepo(), [CacheTags::TEAM], 'pos_order', $itemIds);
			})
			->addFunction('getTestimonials', function($itemIds = []) {
				return $this->getCachedItemsForTemplate($this->getTestimonialsRepo(), [CacheTags::TESTIMONIALS], 'pos_order', $itemIds);
			})
			->addFunction('getFaq', function($itemIds = []) {
				return $this->getCachedItemsForTemplate($this->getFaqRepo(), [CacheTags::FAQ], 'pos_order', $itemIds);
			})
			->addFunction('getInfoBoxes', function($itemIds = []) {
				return $this->getCachedItemsForTemplate($this->getInfoBoxesRepo(), [CacheTags::INFO_BOXES], 'pos_order', $itemIds);
			})->addFunction('getCategories', function($itemIds = [], $mainCategories = false, $withProducts = true) {
                $categoriesItems = $this->getCategoriesRepository()->findAllActive();

                if(count($itemIds)){
                    $categoriesItems->where("id IN ?", $itemIds);
                }

                if($mainCategories) {
                    $categoriesItems->where("pos_level = ?", 1);
                }

                $categoriesItems->order('pos_left');
                $categoriesItems = $this->getCategoriesRepository()->getEntities($categoriesItems);

                if($withProducts) {
                    $categories = [];

                    foreach($categoriesItems as $categoriesItem){
                        if($categoriesItem->getProducts()){
                            $categories[$categoriesItem->id] = $categoriesItem;
                        }

                        $categoryChildrens = $this->getCategoriesRepository()->getChildren($categoriesItem);
                        if($categoryChildrens && !count($itemIds)){
                            foreach($this->getCategoriesRepository()->getEntities($categoryChildrens) as $categoryChild){
                                if($categoryChild->getProducts()){
                                    if($mainCategories) {
                                        $categories[$categoriesItem->id] = $categoriesItem;
                                        break;
                                    }else {
                                        $categories[$categoryChild->id] = $categoryChild;
                                    }
                                }
                            }
                        }
                    }

                    return $categories;
                }else{
                    return $categoriesItems;
                }
            })->addFunction('getProducts', function($itemIds = [], $sort = "name ASC", $limit = NULL) {
                return $this->getProducts($itemIds, $sort, $limit);
            })->addFunction('getBranches', function($itemIds = [], $sort = "pos_order") {

                if($items = $this->getBranchesRepository()->findAllActive()){
                    if($itemIds){
                        $items->where("id IN (?)", $itemIds);
                    }

                    $items->order($sort);

                    return $this->getBranchesRepository()->getEntities($items);
                }

            })->addFunction('getLinks', function($itemIds = []) {
                return $this->getCachedItemsForTemplate($this->getLinksRepository(), [CacheTags::LINKS], 'pos_order', $itemIds);
            })
			//system content
			->addFunction('page', function(string $system) {
				/** @var BasePresenter $presenter */
				$presenter = $this->getPresenter();
				return $presenter->getPage($system);
			})
			->addFunction('content', function(string $contentSystem, string $parameterSystem) {
				/** @var BasePresenter $presenter */
				$presenter = $this->getPresenter();
				return $presenter->getContent($contentSystem, $parameterSystem);
			})
			->addFunction('settings', function(string $system) {
				/** @var BasePresenter $presenter */
				$presenter = $this->getPresenter();
				return $presenter->getSettings($system);
			})
			//functions
			->addFunction('prepareHtml', function(string $string) {
				return BaseEntity::prepareHtml($string);
			})
			//render
			->setFile(__DIR__.'/ContentBlocksControl.latte')
			->render();
	}

    public function handleLoadBranchProducts($branch_id, $limit, $limitAdd, $categoriesId = NULL)
    {
        $this->template->limit = $limit + $limitAdd;
        $this->template->branchHandleId = $branch_id;
		$this->template->branchProductCategoriesId = $categoriesId;

        $this->redrawControl('contentBlocks');
        $this->redrawControl('blockTypeProducts');
    }


	/**
	 * @param BaseRepository $repo
	 * @param array $cacheTags
	 * @param string|NULL $order
	 * @param array $itemIds
	 * @return array|null
	 * @throws Throwable
	 */
	public function getCachedItemsForTemplate(BaseRepository $repo, array $cacheTags, string $order = NULL, array $itemIds = []): ?array
	{
		if(!is_array($itemIds)){
			return [];
		}

		$tableName = $repo->getTableName();
		if(!isset($this->cachedItems[$tableName])){
			$this->cachedItems[$tableName] = $this->getCacheService()->load($repo->getTableName());
		}
		if(is_null($this->cachedItems[$tableName])){
			$this->cachedItems[$tableName] = $this->getCacheService()->saveDatabaseSelection(
				$repo->getTableName(),
				$repo->findAllActive($order),
				$cacheTags
			);
		}

		if(empty($itemIds)){
			return $this->cachedItems[$tableName];
		}

		$items = [];
		if(is_array($this->cachedItems[$tableName]) && !empty($this->cachedItems[$tableName])){
			foreach($itemIds as $itemId){
				if(isset($this->cachedItems[$tableName][$itemId.'|'.$GLOBALS['lang']])){//mld
					$items[$itemId] = $this->cachedItems[$tableName][$itemId.'|'.$GLOBALS['lang']];
				}
				else{
					if(isset($this->cachedItems[$tableName][$itemId])){//basic
						$items[$itemId] = $this->cachedItems[$tableName][$itemId];
					}
				}
			}
		}
		return $items;
	}

    public function createComponentForm(): Form
    {
        $form = new Form();
        $form->setAction($GLOBALS['container']->getUrlScript());

        /** @var BasePresenter $presenter */
        $presenter = $this->getPresenter();

        //antispam protection
        $form->addHidden('real_name');
        $form->addHidden('system');

        //fields
        $form->addText('name', $presenter->getContent('contact_form', 'name'))
            ->setRequired(TRUE);
        $form->addText('surname', $presenter->getContent('contact_form', 'surname'))
            ->setRequired(TRUE);

        $form->addText('email', $presenter->getContent('contact_form', 'email'))
            ->setRequired(TRUE)
            ->addRule(Form::EMAIL);

        $form->addText('phone', $presenter->getContent('contact_form', 'phone'))
            ->setRequired(TRUE);

        $form->addTextArea('text', $presenter->getContent('contact_form', 'message'), 64, 4)
            ->setRequired(TRUE);

        $label = Html::el('');
        $label->addText($presenter->getContent('contact_form', 'agree_1').' ');
        $label->addHtml(Html::el('a')
            ->href($presenter->getPage('privacy')->full_url)
            ->setText($presenter->getContent('contact_form', 'agree_2')));
        $form->addCheckbox('agree', $label)
            ->setRequired(TRUE)
            ->setHtmlAttribute('class', 'agree-checkbox');

        $form->addSubmit('send', $presenter->getContent('contact_form', 'send'));

        $form->onSuccess[] = [$this, 'formSubmitted'];

        return $form;
    }


    /**
     * @param Form $form
     * @throws AbortException
     * @throws Exception
     * @throws Exception
     */
    public function formSubmitted(Form $form)
    {
        $this->processSpamProtection($form);

        /** @var array $values */
        $values = $form->getValues(TRUE);

        /** @var BasePresenter $presenter */
        $presenter = $this->getPresenter();

        /** @var EmailTemplatesEntity $emailTemplatesEntity */
        if ($emailTemplatesEntity = $this->getEmailTemplatesRepo()->findEntityBySystem("contacts_form")) {

            $vars = [
                'name' => $values['name'],
                'surname' => $values['surname'],
                'email' => $values['email'],
                'text' => $values['text'],
                'phone' => $values['phone'],
            ];
            $this->getMailingService()
                ->sendTemplate($emailTemplatesEntity, $vars, [$values['email']]);
            $presenter->redirectUrlWithFlashMessage($presenter->getContent('contact_form', 'message_success'), 'success', $presenter->page->full_url);
        }

        $presenter->redirectUrlWithFlashMessage($presenter->getContent('contact_form', 'message_failed'), 'error', $presenter->page->full_url);
    }


    /**
     * @param Form $form
     * @return void
     * @throws AbortException
     */
    protected function processSpamProtection(Form $form): void
    {
        /** @var array $values */
        $values = $form->getValues(TRUE);

        /** @var BasePresenter $presenter */
        $presenter = $this->getPresenter();

        /** @var ContentBlocksEntity|null $contentBlocksEntity */
        /*$contentBlocksEntity = $this->getContentBlocksRepo()->findEntity($values['content_blocks_id']);
        if(!$contentBlocksEntity instanceof ContentBlocksEntity) {
            $presenter->redirectUrlWithFlashMessage($presenter->getContent('contact_form', 'message_failed'), 'error', $presenter->page->full_url);
            exit;
        }*/

       // $spamProtection = $contentBlocksEntity->getValue('spam_protection') ?: 'basic';

        //basic antispam protection

            $numbers = explode('-', $values['system']);
            $randomNumber1 = $numbers[0] ?? 0;
            $randomNumber2 = $numbers[1] ?? 0;
            $number3 = $numbers[2] ?? -1;
            if($values['real_name'] != '' || (int)$randomNumber1 + (int)$randomNumber2 != $number3){
                /** @var BasePresenter $presenter */
                $presenter = $this->getPresenter();
                $presenter->redirectUrlWithFlashMessage($presenter->getContent('default_form_message', 'message_antispam'), 'error', $presenter->page->full_url);
            }




        //recaptcha v2 & recaptcha v2 invisible
        /*$recaptchaSettingsKey = 'google_recaptcha'.($spamProtection == 'invisible_recaptcha' ? '_invisible' : '');
        $isRecaptcha = in_array($spamProtection, ['recaptcha', 'invisible_recaptcha']) && $presenter->getSettings($recaptchaSettingsKey.'_secret_key') && $presenter->getSettings($recaptchaSettingsKey.'_site_key');
        if($isRecaptcha){
            $data = $form->getHttpData();
            $verify = curl_init();
            curl_setopt($verify, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
            curl_setopt($verify, CURLOPT_POST, true);
            curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query([
                'secret' => $presenter->getSettings($recaptchaSettingsKey.'_secret_key'),
                'response' => $data['g-recaptcha-response'] ?? ''
            ]));
            curl_setopt($verify, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
            $rawResponse = curl_exec($verify);
            $response = json_decode($rawResponse);
            if (!isset($response->success) || !$response->success) {
                $presenter->redirectUrlWithFlashMessage($presenter->getContent('default_form_message', 'message_antispam_recaptcha'), 'error', $presenter->page->full_url);
                exit;
            }
        }*/
    }

    /**
     * @param $itemIds
     * @param $sort
     * @param $limit
     * @return array|void
     */
    public function getProducts($itemIds = [], $sort = "name ASC", $limit = NULL)
    {
        if($products = $this->getProductsRepository()->findAll()){
            if($itemIds){
                $products->where("id IN (?)", $itemIds);
            }

            if($limit){
                $products->limit($limit);
            }

            $products->order($sort);

            return $this->getProductsRepository()->getEntities($products);
        }
    }



	/**
	 * @return ContentsEntity|null
	 */
	public function getContent():? ContentsEntity
	{
		return $this->content;
	}


	/**
	 * @param ContentsEntity $content
	 */
	public function setContent(ContentsEntity $content): void
	{
		$this->content = $content;
	}


	/**
	 * @return ContentBlocksRepository
	 */
	public function getContentBlocksRepo(): ContentBlocksRepository
	{
		return $this->contentBlocksRepo;
	}

	/**
	 * @return EmailTemplatesRepository
	 */
	public function getEmailTemplatesRepo(): EmailTemplatesRepository
	{
		return $this->emailTemplatesRepo;
	}

	/**
	 * @return MailingService
	 */
	public function getMailingService(): MailingService
	{
		return $this->mailingService;
	}

	/**
	 * @return CtaRepository
	 */
	public function getCtaRepo(): CtaRepository
	{
		return $this->ctaRepo;
	}

	/**
	 * @return SlidersRepository
	 */
	public function getSlidersRepo(): SlidersRepository
	{
		return $this->slidersRepo;
	}

	/**
	 * @return TeamRepository
	 */
	public function getTeamRepo(): TeamRepository
	{
		return $this->teamRepo;
	}

	/**
	 * @return PartnersRepository
	 */
	public function getPartnersRepo(): PartnersRepository
	{
		return $this->partnersRepo;
	}

	/**
	 * @return PromoBoxesRepository
	 */
	public function getPromoBoxesRepo(): PromoBoxesRepository
	{
		return $this->promoBoxesRepo;
	}

	/**
	 * @return TestimonialsRepository
	 */
	public function getTestimonialsRepo(): TestimonialsRepository
	{
		return $this->testimonialsRepo;
	}

	/**
	 * @return FaqRepository
	 */
	public function getFaqRepo(): FaqRepository
	{
		return $this->faqRepo;
	}

	/**
	 * @return InfoBoxesRepository
	 */
	public function getInfoBoxesRepo(): InfoBoxesRepository
	{
		return $this->infoBoxesRepo;
	}
	/**
	 * @return CacheService
	 */
	public function getCacheService(): CacheService
	{
		return $this->cacheService;
	}

    public function getCategoriesRepository(): CategoriesRepository
    {
        return $this->categoriesRepository;
    }

    public function getProductsRepository(): ProductsRepository
    {
        return $this->productsRepository;
    }

    public function getBranchesRepository(): BranchesRepository
    {
        return $this->branchesRepository;
    }

    public function getLinksRepository(): LinksRepository
    {
        return $this->linksRepository;
    }



}
