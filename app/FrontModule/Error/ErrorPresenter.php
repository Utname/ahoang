<?php
namespace App\FrontModule\Presenters;

use Exception;
use LanguagesRepository;
use MetaInfoControl;
use PagesRepository;
use Tracy\Debugger;
use Tracy\ILogger;

class ErrorPresenter extends BasePresenter
{
	/** @var ILogger */
	private $logger;


    public function __construct(ILogger $logger) {
        $this->logger = $logger;
    }


	/**
	 * @throws \Nette\Application\BadRequestException
	 */
	protected function startup()
	{
		error_reporting(E_ERROR | E_PARSE);

		$urlParam = $GLOBALS['uri']->getPath();
		$urlParams = explode('/', $urlParam);
		$GLOBALS['lang'] = count($urlParams) && strlen($urlParams[1]) == 2 ? $urlParams[1] : $this->languagesRepo->findDefault()->id;
		$this->language = $this->languagesRepo->findEntity($GLOBALS['lang']);
		if(!$this->language instanceof \LanguagesEntity){
			$this->language = $this->languagesRepo->findDefault();
			$GLOBALS['lang'] = $this->language->id;
		}
		$this->page = $this->pagesRepo->getEntity($this->pagesRepo->findOneBy(['system' => 'error']));
		parent::startup();
	}


	/**
	 * @param Exception
	 */
	public function renderDefault($exception) {
		$code = $exception->getCode() ?: 500;
		if ($code == 500) {
			$this->logger->log($exception, ILogger::EXCEPTION);
		}
		$this->template->errorCode = $code;
	}

	/**
	 *
	 */
	protected function beforeRender()
	{
		parent::beforeRender();

		$this->template->isErrorPage = TRUE;
	}


}
