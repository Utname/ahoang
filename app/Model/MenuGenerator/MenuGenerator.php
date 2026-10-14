<?php

use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Localization\Translator;
use Nette\Utils\Html;
use Nette\Utils\Strings;

class MenuGenerator
{
	//structure
	const KEY_SUBITEMS = 'subItems';
	const KEY_PERMISSIONS = 'permissions';
	const KEY_ICON = 'icon';

	//defaults
	const DEFAULT_PERMISSION_SUFFIX = '_list';
	const DEFAULT_ICON = 'fas fa-list';

	/** @var array  */
	protected array $menuStructure = [];

	/** @var string|null  */
	protected ?string $nameSpace = NULL;

	/** @var Translator|null  */
	protected ?Translator $translator = NULL;

	/** @var AdminPermissionsService|null  */
	protected ?AdminPermissionsService $adminPermissionsService = NULL;

	/** @var string|null  */
	protected ?string $iconClassPrefix = NULL;

	/** @var MenuFilter|null  */
	protected ?MenuFilter $menuFilter = NULL;

	/**
	 * @param LinkGenerator $linkGenerator
	 */
	public function __construct(protected LinkGenerator $linkGenerator)
	{
		$this->menuFilter = new MenuFilter();
	}

	/**
	 * @return Html
	 * @throws InvalidLinkException
	 */
	/** @var array<string, int> menu key => count shown next to it */
	protected array $badges = [];


	/** @param array<string, int> $badges */
	public function setBadges(array $badges): void
	{
		$this->badges = $badges;
	}


	public function generate(): Html
	{
		$wrapper = Html::el();
		$itemsLvl1 = $this->getMenuStructure();

		foreach($itemsLvl1 as $keyLvl1 => $settingsLvl1){

			//CUSTOM ENTRIES
			if($keyLvl1 == 'Divider'){
				$wrapper->addHtml(
					Html::el('li')
						->setAttribute('class', 'divider')
				);
				continue;
			}

			//LEVEL 1=======================
			if(!$this->isMenuItemVisible($keyLvl1, $settingsLvl1)){
				continue;
			}

			$itemsLvl2 = $settingsLvl1[self::KEY_SUBITEMS] ?? [];

			$topLi = Html::el('li');
			$a = Html::el('a')
				->setAttribute('href', $this->getMenuLink(empty($itemsLvl2), $keyLvl1))
				->addHtml(
					Html::el('span')
						->setAttribute('class', $this->getIcon($settingsLvl1))
				)
				->addHtml(
					Html::el('p')
						->setText($this->getMenuName($keyLvl1))
				);
			// count next to the item, e.g. lists waiting in Tisk z mobilu
			if (!empty($this->badges[$keyLvl1])) {
				$a->addHtml(
					Html::el('span')
						->setAttribute('class', 'menu-badge')
						->setText((string)$this->badges[$keyLvl1])
				);
			}
			if(!empty($itemsLvl2)){
				$topLi->setAttribute('class', 'droplink top-level');
				$a->addHtml(
					Html::el('span')
						->setAttribute('class', 'arrow')
				);
			}
			$topLi->addHtml($a);


			if(!empty($itemsLvl2)){
				$topUl = Html::el('ul')
					->setAttribute('class', 'sub-menu');

				foreach($itemsLvl2 as $keyLvl2 => $settingsLvl2){

					if(!isset($settingsLvl2[self::KEY_SUBITEMS])){ //only has 2 levels
						//LEVEL 2==================================
						if(!$this->isMenuItemVisible($keyLvl2, $settingsLvl2)){
							continue;
						}

						$topUl->addHtml(
							Html::el('li')
								->addHtml(
									Html::el('a')
										->setAttribute('href', $this->getMenuLink(TRUE, $keyLvl2))
										->setText($this->getMenuName($keyLvl2))
								)
						);

					}
					else{
						//LEVEL 3==============================================
						if(!$this->isMenuItemVisible(self::KEY_SUBITEMS, $settingsLvl2[self::KEY_SUBITEMS])){
							continue;
						}

						$ul = Html::el('ul')
							->setAttribute('class', 'sub-menu');
						if(!empty($settingsLvl2[self::KEY_SUBITEMS])){
							$itemsLvl3 = $settingsLvl2[self::KEY_SUBITEMS];
							foreach($itemsLvl3 as $keyLvl3 => $settingsLvl3){
								if(!$this->isMenuItemVisible($keyLvl3, $settingsLvl3)){
									continue;
								}
								$ul->addHtml(
									Html::el('li')
										->addHtml(
											Html::el('a')
												->setAttribute('href', $this->getMenuLink(TRUE, $keyLvl3))
												->setText($this->getMenuName($keyLvl3))
										)
								);
							}
						}

						$li = Html::el('li')
							->setAttribute('class', 'droplink')
							->addHtml(
								Html::el('a')
									->setAttribute('href', $this->getMenuLink(FALSE, $keyLvl2))
									->addHtml(
										Html::el('p')
											->setText($this->getMenuName($keyLvl2))
									)
									->addHtml(
										Html::el('span')
											->setAttribute('class', 'arrow')
									)
							)
							->addHtml($ul);
						$topUl->addHtml($li);
					}
				}
				$topLi->addHtml($topUl);
			}


			$wrapper->addHtml($topLi);

		}

		return $wrapper;
	}


	/**
	 * @param string $key
	 * @param array|null $settings
	 * @return bool
	 */
	public function isMenuItemVisible(string $key, ?array $settings = []): bool
	{
		$permissions = $this->getMenuPermissions($key, $settings);
		$hasPermissions = !count($permissions) || $this->hasAnyPermission($permissions);

		return $hasPermissions;
	}


	/**
	 * @param array $permissions
	 * @return bool
	 */
	protected function hasAnyPermission(array $permissions): bool
	{
		if($this->getAdminPermissionsService() instanceof AdminPermissionsService){
			return $this->getAdminPermissionsService()->hasAny($permissions);
		}
		return TRUE;
	}


	/**
	 * @param array|null $settings
	 * @return string
	 */
	protected function getIcon(?array $settings): string
	{
		if(!is_array($settings)){
			return '';
		}
		$iconParts = [];
		if($this->getIconClassPrefix()){
			$iconParts[] = $this->getIconClassPrefix();
		}
		$iconParts[] = $settings[self::KEY_ICON] ?? self::DEFAULT_ICON;

		return implode(' ', $iconParts);
	}


	/**
	 * @param bool $isSingle
	 * @param string $name
	 * @return string
	 * @throws InvalidLinkException
	 */
	protected function getMenuLink(bool $isSingle, string $name): string
	{
		if($isSingle){
			$url =  $this->linkGenerator->link(($this->getNameSpace() ? ($this->getNameSpace().':') : '').$name.':');
			return parse_url($url, PHP_URL_PATH); //return only path for morern.js to display .active page
		}
		else{
			return '#';
		}
	}


	/**
	 * @param string $name
	 * @return string
	 */
	protected function getMenuName(string $name): string
	{
		return $this->menuFilter->process($this->getTranslator() ? ($this->getTranslator()->translate('admin.modules.'.Strings::firstLower($name))) : $name);
	}


	/**
	 * @param string|NULL $name
	 * @param array|null $options
	 * @return array
	 */
	protected function getMenuPermissions(string $name = NULL, ?array $options = []): array
	{
		$hasCustomPermissions = isset($options[self::KEY_PERMISSIONS]);
		$permissions = $hasCustomPermissions ? array_combine($options[self::KEY_PERMISSIONS], $options[self::KEY_PERMISSIONS]) : [];
		if($name == self::KEY_SUBITEMS){
			foreach($options as $key => $val){
				$permissions = array_merge($permissions, $this->getMenuPermissions($key, is_array($val) ? $val : []));
			}
		}
		else{
			if(isset($options[self::KEY_SUBITEMS]) && is_array($options[self::KEY_SUBITEMS]) && !empty($options[self::KEY_SUBITEMS])){
				$permissions = array_merge($permissions, $this->getMenuPermissions(self::KEY_SUBITEMS, $options[self::KEY_SUBITEMS]));
			}
			else{
				if(!is_null($name) && !$hasCustomPermissions){
					$permissions[$name.self::DEFAULT_PERMISSION_SUFFIX] = $name.self::DEFAULT_PERMISSION_SUFFIX;
				}
			}
		}

		return $permissions;
	}


	//GETTERS & SETTERS


	/**
	 * @return array
	 */
	public function getMenuStructure(): array
	{
		return $this->menuStructure;
	}


	/**
	 * @param array $menuStructure
	 */
	public function setMenuStructure(array $menuStructure): void
	{
		$this->menuStructure = $menuStructure;
	}

	/**
	 * @return string|null
	 */
	public function getNameSpace(): ?string
	{
		return $this->nameSpace;
	}


	/**
	 * @param string|null $nameSpace
	 */
	public function setNameSpace(?string $nameSpace): void
	{
		$this->nameSpace = $nameSpace;
	}


	/**
	 * @return Translator|null
	 */
	public function getTranslator(): ?Translator
	{
		return $this->translator;
	}


	/**
	 * @param Translator|null $translator
	 */
	public function setTranslator(?Translator $translator): void
	{
		$this->translator = $translator;
	}


	/**
	 * @return AdminPermissionsService|null
	 */
	public function getAdminPermissionsService(): ?AdminPermissionsService
	{
		return $this->adminPermissionsService;
	}


	/**
	 * @param AdminPermissionsService|null $adminPermissionsService
	 */
	public function setAdminPermissionsService(?AdminPermissionsService $adminPermissionsService): void
	{
		$this->adminPermissionsService = $adminPermissionsService;
	}


	/**
	 * @return string|null
	 */
	public function getIconClassPrefix(): ?string
	{
		return $this->iconClassPrefix;
	}


	/**
	 * @param string|null $iconClassPrefix
	 */
	public function setIconClassPrefix(?string $iconClassPrefix): void
	{
		$this->iconClassPrefix = $iconClassPrefix;
	}




}
