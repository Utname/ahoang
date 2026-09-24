<?php

namespace App\SystemModule\Presenters;

use App\AdminModule\Traits\BaseAuthenticationTrait;
use Nette\Application\UI\Presenter;
use Nette\Http\SessionSection;

class DropzonePresenter extends Presenter
{
	use BaseAuthenticationTrait;


	/**
	 * @return void
	 */
	protected function startup()
	{
		parent::startup();

		$this->adminUser = $this->getAdminUser();

		if(!$this->adminUser) {
			throw new BadRequestException('Page not found', 404);
		}
	}


	/**
	 *
	 */
	public function actionUpload()
	{

		if(!empty($_FILES) && isset($_GET['directory']))
		{
			$tempFile = $_FILES['file']['tmp_name'];
			$targetPath = $_SERVER['DOCUMENT_ROOT'] . '/files/temp/'.$_GET['directory'];

			if(!file_exists($targetPath))
			{
				mkdir($targetPath.'/', 0755);
				chmod($targetPath.'/', 0755);
			}

			$randomFileName = time().'_'.substr(md5(rand()), 0, 7);
			$extension = strtolower(pathinfo(basename($_FILES['file']['name']), PATHINFO_EXTENSION));
			$newFileName = $randomFileName.'.'.$extension;
			$targetFile = rtrim($targetPath,'/').'/'.$newFileName;

			move_uploaded_file($tempFile, $targetFile);

			$_SESSION['preserveDropzoneUploadNames'][$newFileName] = $_FILES['file']['name'];

			echo 'done';
		}
	}


	/**
	 * @return void
	 */
	protected function beforeRender()
	{
		exit;
	}

}
