<?php

namespace App\AdminModule\Presenters;


use AdminPermissionsService;
use ContentBlocksRepository;
use ContentSectionsRepository;
use ContentsRepository;
use ContentsService;
use FilesDataService;
use ImagesService;
use Nette\Database\Context;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Nette\Localization\Translator;

class ContentBlocksControl extends BaseControl
{
	use \ContentBlocksSectionsTrait;
	use \ContentBlocksBlocksTrait;

	/** @var \ContentsEntity */
	protected $content;

	/** @var bool */
	protected $renderModals = FALSE;

	/** @var string */
	protected $action;


	/**
	 * @param ContentsRepository $contentsRepo
	 * @param ContentsService $contentsService
	 * @param ContentSectionsRepository $sectionsRepo
	 * @param ContentBlocksRepository $blocksRepo
	 * @param ImagesService $imagesService
	 * @param FilesDataService $filesDataService
	 * @param Explorer $database
	 * @param AdminPermissionsService $aps
	 * @param Translator $translator
	 */
	public function __construct(
		protected ContentsRepository $contentsRepo,
		protected ContentsService $contentsService,
		protected ContentSectionsRepository $sectionsRepo,
		protected ContentBlocksRepository $blocksRepo,
		protected ImagesService $imagesService,
		protected FilesDataService $filesDataService,
		protected Explorer $database,
		protected AdminPermissionsService $aps,
		protected Translator $translator)
	{
	}


	/**
	 * @param array $params
	 */
	public function render($params = [])
	{
		if ($this->renderModals) {
			$this->template->setFile(__DIR__.'/templates/modals.latte');
		}
		else {
			$this->template->setFile(__DIR__.'/templates/template.latte');
		}

		$this->template->isCopy = $this->getAction() == 'copy';
		$this->template->aps = $this->aps;
		$this->template->render();
	}

    /**
     * @param array $params
     */
    public function renderSorting($params = [])
    {

        $this->template->setFile(__DIR__ . '/templates/templateSorting.latte');

        $this->template->isCopy = $this->getAction() == 'copy';
        $this->template->aps = $this->aps;
        $this->template->render();
    }


	/**
	 *
	 */
	public function renderModals()
	{
		$this->renderModals = TRUE;

		$this->render();
	}


	/**
	 * @param array $snippets
	 */
	protected function redrawModals(array $snippets = [])
	{
		$this->renderModals = TRUE;

		$this->redrawControl('modals');

		foreach ($snippets as $snippet) {
			$this->redrawControl($snippet);
		}
	}


	/**
	 * @return \ContentsEntity|null
	 */
	public function getContent(): ?\ContentsEntity
	{
		return $this->content;
	}


	/**
	 * @param \ContentsEntity $content
	 */
	public function setContent(\ContentsEntity $content): void
	{
		$this->content = $content;
	}


	/**
	 * @return Translator
	 */
	public function getTranslator(): Translator
	{
		return $this->translator;
	}


	/**
	 * @return string
	 */
	public function getAction(): string
	{
		return $this->action;
	}


	/**
	 * @param string $action
	 */
	public function setAction(string $action): void
	{
		$this->action = $action;
	}

}
