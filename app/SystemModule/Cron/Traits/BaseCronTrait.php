<?php

namespace App\SystemModule\Traits;


use Nette\Database\Explorer;
use Nette\DI\Container;
use Tracy\Debugger;

trait BaseCronTrait
{
	/** @var Explorer @inject */
	public $database;

	/** @var Container @inject */
	public $container;

	/** @var array */
	protected $parameters;

	/** @var \LanguagesRepository @inject */
	public $languagesRepo;

	/** @var \LanguagesEntity[] */
	protected $languages;

	/** @var \LanguagesEntity */
	protected $language;

	/** @var \CronsRepository @inject */
	public $cronsRepo;

	/** @var \CronsService @inject */
	public $cronsService;

	/** @var \SettingsRepository @inject */
	public $settingsRepo;

	/** @var \GlobalContainer @inject */
	public $globalContainer;


	/**
	 *
	 */
	protected function startup()
	{
		parent::startup();

		ini_set('max_execution_time', 600);

		// parameters
		$this->parameters = $this->container->getParameters();

		// This presenter has no session/login check anywhere in its chain
		// (confirmed: SystemModule's BasePresenter has none) — before this,
		// anyone who found the URL could hit it and run EVERY due cron job with
		// no authentication at all, not just a specific known one via ?run=.
		// A shared secret (CRON_SECRET, see .env.example) is now required on
		// every request to this presenter, checked against ?token=. Fails
		// closed: an unconfigured/empty CRON_SECRET rejects every request
		// rather than leaving the endpoint open, so a deploy that forgets to
		// set it breaks cron loudly instead of silently staying unauthenticated.
		$expectedSecret = (string)($this->parameters['cronSecret'] ?? '');
		$providedSecret = (string)$this->getHttpRequest()->getQuery('token');
		if ($expectedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
			$this->getHttpResponse()->setCode(403);
			echo 'Forbidden';
			$this->terminate();
		}

		// settings
		$GLOBALS['settings'] = $this->settingsRepo->findAll()->fetchPairs('id', 'value');

		// languages
		$GLOBALS['languages'] = $this->languages = $this->languagesRepo->getEntities($this->languagesRepo->findAllActive()
			->order('pos_order'));

		$GLOBALS['language'] = $this->language = $this->languagesRepo->findDefault();

		$GLOBALS['lang'] = $this->language->id;

		//global container
		$GLOBALS['container'] = $this->globalContainer;
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function actionDefault()
	{
		$successCount = 0;

		if($hash = $this->getParameter('run')){
			$cronsToRun = $this->cronsRepo->findAll()->where('hash', $hash);
		}
		else{
			$cronsToRun = $this->cronsRepo->findAllActive()->where('next_run_at <= ?', date('Y-m-d H:i:s'));
		}

		if($cronsToRun){
			$cronsToRun = \CronsRepository::getEntities($cronsToRun);
			/** @var \CronsEntity $cronsEntity */
			foreach($cronsToRun as $cronsEntity){
				echo '<b>Running cron "'.$cronsEntity->name.'"...</b><br>';
				$cronsEntity = $this->cronsService->updateNextRunAt($cronsEntity);
				if(method_exists($this, $cronsEntity->command) && is_callable([$this, $cronsEntity->command])){
					try
					{
						$this->{$cronsEntity->command}();
						$cronsEntity = $this->cronsService->updateLastRunAt($cronsEntity);
						echo ' <span style="color: green;"><b>[SUCCESS]</b></span>';
						$successCount++;
					}
					catch(\Exception $e){
						Debugger::log($e, 'cron_'.$cronsEntity->command);
						echo ' <span style="color: red;"><b>[FAILED]</b></span>';
					}
				}
				else{
					Debugger::log('Method '.$cronsEntity->command.'() does not exist for cron "'.$cronsEntity->name.'"', 'cron_'.$cronsEntity->command);
					echo ' <span style="color: red;"><b>[FAILED]</b></span>';
				}
				echo '<br>';
			}
		}


		echo '-------------------------------<br>';
		echo 'A total of <b>'.$successCount.'</b> crons were ran sucessfully.';
		exit;
	}


	/**
	 * @param string $message
	 * @param int|NULL $processed
	 * @return void
	 */
	public static function sendInfoMessage(string $message, int $processed = NULL): void
	{
		if(!is_null($processed)){
			$message = str_replace('%d', '<b>'.$processed.'</b>', $message);
		}
		echo('- '.$message.'<br>');
	}
}
