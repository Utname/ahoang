<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
	use Nette\StaticClass;

	/**
	 * @return RouteList
	 */
	public static function createRouter(Nette\DI\Container $container, Nette\Database\Explorer $database): RouteList
	{
		$GLOBALS['uri'] = $container->getByType('Nette\Http\Request')->getUrl();

		// redirects
		$inputUri = rtrim($GLOBALS['uri']->getPath(), '/');
		if ($inputUri && $redirect = $database->table('redirects')
			->where('input_url = ? OR input_url = ?', rtrim($GLOBALS['uri']->getPath(), '/'), $_SERVER['REQUEST_URI'])
			->limit(1)
			->fetch()) {
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: ".$redirect->output_url);
			exit;
		}

		$router = new RouteList();

		// media
		$router->addRoute('media/<folder>/<subfolder>/<filename>', [
			'module' => 'Front',
			'presenter' => 'Image',
			'action' => 'default'
		]);

		// admin
		$router->addRoute('admin/<presenter>/<action>[/<id>]', [
			'module' => 'Admin',
			'presenter' => 'Homepage',
			'action' => 'default'
		]);

		// sitemap
		$router->addRoute('sitemap.xml', [
			'module' => 'System',
			'presenter' => 'Sitemap',
			'action' => 'default'
		]);

		// robots
		$router->addRoute('robots.txt', [
			'module' => 'System',
			'presenter' => 'Robots',
			'action' => 'default'
		]);

		// system
		$router->addRoute('system/<presenter>/<action>[/<slug .*>]', [
			'module' => 'System',
			'presenter' => 'Homepage',
			'action' => 'default'
		]);

		// cron
		$router->addRoute('cron/<action>', [
			'module' => 'System',
			'presenter' => 'Cron',
			'action' => 'default'
		]);

		// front
		$urlParam = $GLOBALS['uri']->getPath();
		$urlParams = explode('/', trim($urlParam, '/'));

		$languagesId = count($urlParams) && isset($urlParams[0]) && strlen($urlParams[0]) == 2 ? $urlParams[0] : NULL;

		// pages
		$pages = $database->table('pages_mld')
			->select('pages_mld.*, pages.*, pages.page_types.presenter, pages.page_types.action')
			->where('pages_mld.is_active', TRUE)
			->where('languages.is_active', TRUE);

        // block page
        $pageBlock = $database->query("
            SELECT content_blocks.slug FROM content_blocks 
                     LEFT JOIN content_sections ON content_sections.id = content_blocks.sections_id
                     LEFT JOIN contents_mld ON contents_mld.contents_id = content_sections.contents_id
                     WHERE contents_mld.languages_id = ? AND content_blocks.slug = ? AND content_blocks.is_active = ? AND contents_mld.contents_id IN (SELECT contents_id FROM pages_mld WHERE languages_id = ?) LIMIT 1", $languagesId ?: 'cs', isset($urlParams[1]) ? $urlParams[1] : $urlParams[0], TRUE, $languagesId ?: 'cs')->fetch();

		if ($languagesId) {
			$pages->where('languages.id', $languagesId);
		}
		else {
			$pages->where('languages.is_default', TRUE);
		}


		$blogPage = NULL;
		$branchesPage = NULL;

		foreach ($pages->order('pos_left DESC') as $pageRow) {
			$page = \PagesRepository::getEntity($pageRow);

            if($pageBlock && $pageBlock->slug && Nette\Utils\Strings::endsWith($urlParam, $pageBlock->slug) && $page->system == 'home'){
                $router->addRoute($urlParam . '[/<slug .*>]', [
                    'module' => 'Front',
                    'lang' => $page->languages_id,
                    'presenter' => $page->presenter,
                    'action' => $page->action ?: 'default',
                    'page' => $page,
                    'slug' => $urlParam,
                    'product' => NULL,
                    'category' => NULL
                ]);
            }

			if ($page->system === 'blog') {
				$blogPage = $page;
			}
			elseif ($page->system === 'branches') {
				$branchesPage = $page;
			}
			else {
				if(!Nette\Utils\Strings::startsWith($GLOBALS['uri']->getPath(), $page->full_url)){
					continue;
				}

				$router->addRoute(ltrim($page->full_url, '/').'[/<slug .*>]', [
					'module' => 'Front',
					'lang' => $page->languages_id,
					'presenter' => $page->presenter,
					'action' => $page->action ?: 'default',
					'page' => $page,
					'slug' => NULL,
					'product' => NULL,
					'category' => NULL
				]);
			}
		}

		// blog page
		if ($page = $blogPage) {

			//categories
			if(isset($urlParams[2]) && $urlParams[2]){
				$slug = $urlParams[2];

				$selection = $database->table('blog_categories_mld')->where('slug', $slug);
				if ($languagesId) {
					$selection->where('languages.id', $languagesId);
				}
				else {
					$selection->where('languages.is_default', TRUE);
				}

				if ($category = $selection->limit(1)->fetch()) {
					$router->addRoute(ltrim($page->full_url, '/').'/'.$slug.'[/<slug .*>]', [
						'module' => 'Front',
						'lang' => $category->languages_id,
						'presenter' => $page->presenter,
						'action' => $page->action ?: 'default',
						'page' => $page,
						'slug' => NULL,
						'product' => NULL,
						'category' => $category->blog_categories_id
					]);
				}
			}

			$router->addRoute(ltrim($page->full_url, '/').'[/<slug .*>]', [
				'module' => 'Front',
				'lang' => $page->languages_id,
				'presenter' => $page->presenter,
				'action' => $page->action ?: 'default',
				'page' => $page,
				'slug' => NULL,
				'product' => NULL,
				'category' => NULL
			]);
		}

		// branches page
		if ($page = $branchesPage) {
			$router->addRoute(ltrim($page->full_url, '/').'[/<slug .*>]', [
				'module' => 'Front',
				'lang' => $page->languages_id,
				'presenter' => $page->presenter,
				'action' => $page->action ?: 'default',
				'page' => $page,
				'slug' => NULL,
				'product' => NULL,
				'category' => NULL
			]);
		}

		return $router;
	}
}
