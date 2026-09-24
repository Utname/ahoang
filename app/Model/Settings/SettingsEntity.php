<?php


class SettingsEntity extends BaseEntity
{
	/**
	 * @param $faviconHtml
	 * @return array|mixed|string|string[]
	 */
	public static function getFaviconCode($faviconHtml){
		if($faviconHtml){
			if(preg_match_all('/\s+href=["\']([^"\']+)["\']/i', $faviconHtml, $links, PREG_PATTERN_ORDER)){
				$allHrefs = array_unique($links[1]);
			}
			if(count($allHrefs)){
				foreach($allHrefs as $href){
					$hrefExploded = explode('/', $href);
					if($item = end($hrefExploded)){
						$faviconHtml = str_replace('href="'.$href.'"', 'href="/files/favicon'.$href.'"', $faviconHtml);
					}
				}
			}
		}
		return $faviconHtml;
	}


	/**
	 * @return string
	 */
	public static function getFaviconImage()
	{
		$faviconIco = '';
		if(!file_exists(WWW_DIR.'/files/favicon')){
			mkdir(WWW_DIR.'/files/favicon');
		}
		/** @var SplFileInfo $file */
		foreach (\Nette\Utils\Finder::findFiles('*.ico', '*.png', '*.jpg', '*.jpeg')->from(WWW_DIR.'/files/favicon/')->exclude('temp') as $file) {
			$faviconIco = "/files/favicon/".$file->getFilename();
		}
		return $faviconIco;
	}
}
