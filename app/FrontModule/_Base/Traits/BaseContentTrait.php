<?php

namespace App\FrontModule\Traits;

trait BaseContentTrait
{
    /** @var \PagesRepository @inject */
    public $pagesRepo;

    /** @var \SlidersRepository @inject */
    public $slidersRepo;

    /** @var array */
    protected $pagesBuffer;

    /** @var array */
    protected $menuItems;

    /** @var array */
    protected $menuBlocksItems = [];

    /** @var array */
    protected $footerItems;

    /** @var array */
    protected $submenuItems;

    /** @var \ContentsRepository @inject */
    public $contentsRepo;

    /** @var array */
    protected $contentsBuffer;

    /** @var \SettingsService @inject */
    public $settingsService;


    /**
     *
     */
    protected function processContents(): void
    {
        $this->template
            ->addFunction('page', function(string $system) {
                return $this->getPage($system);
            })->addFunction('content', function(string $contentSystem, string $parameterSystem) {
                return $this->getContent($contentSystem, $parameterSystem);
            })->addFunction('settings', function(string $system) {
                return $this->getSettings($system);
            });

        $this->template->addFunction('sliders', function() {
            return $this->getSliders();
        });
    }


    /**
     * @param string $system
     * @return string|null
     */
    public function getSettings(string $system): ?string
    {
        return $this->settingsService->getValue($system);
    }


    /**
     * @param string $system
     * @return \PagesEntity|null
     */
    public function getPage(string $system): ?\PagesEntity
    {
        $pages = $this->getPagesBuffer();

        if (!isset($pages[$system])) {
            return NULL;
        }

        return $pages[$system];
    }


    /**
     * @return array
     */
    protected function getPagesBuffer(): array
    {
        if (is_null($this->pagesBuffer)) {
            $this->pagesBuffer = [];
            foreach ($this->pagesRepo->findAllActiveEntities('pos_left') as $page) {
                $this->pagesBuffer[$page->system ?: 'page-'.$page->id] = $page;
            }
        }

        return $this->pagesBuffer;
    }


    /**
     * @param string $contentSystem
     * @param string $parameterSystem
     * @return string|null
     * @throws \Throwable
     */
    public function getContent(string $contentSystem, string $parameterSystem): ?string
    {
        $contents = $this->getContentsBuffer();

        if (!isset($contents[$contentSystem][$parameterSystem])) {
            return NULL;
        }

        return $contents[$contentSystem][$parameterSystem];
    }


    /**
     * @return array
     * @throws \Throwable
     */
    protected function getContentsBuffer(): array
    {
        $this->contentsBuffer = $this->cacheService->load($this->contentsRepo->getTableName());

        if (is_null($this->contentsBuffer)) {
            $this->contentsBuffer = [];

            /** @var \ContentsEntity $content */
            foreach ($this->contentsRepo->findAllEntities() as $content) {
                $this->contentsBuffer[$content->system] = [];

                /** @var \ContentParametersEntity $parameter */
                foreach ($content->getParameters() as $parameter) {
                    $this->contentsBuffer[$content->system][$parameter->system] = $parameter->getDataValue();
                }
            }

            $this->contentsBuffer = $this->cacheService->save(
                $this->contentsRepo->getTableName(),
                $this->contentsBuffer,
                [\CacheTags::CONTENTS]
            );
        }

        return $this->contentsBuffer;
    }


    /**
     * @return array
     */
    public function getMenuBlocksItems(): array
    {

        $contents = $this->contentsRepo->getEntities($this->contentsRepo->findAll()
            ->where("id IN (SELECT contents_id FROM pages_mld WHERE languages_id = ?)", $GLOBALS['lang'])
            ->where ("types_id = ? AND languages_id = ?",\ContentsEntity::TYPE_SECTIONS_WRAPPER, $GLOBALS['lang']));

        if($contents) {
            foreach($contents as $content){
                foreach($content->getSections() as $section){
                    foreach($section->getBlocks("pos_order_in_menu") as $block) {
                        if ($block->is_in_menu && $block->name_page) {
                            $this->menuBlocksItems[] = [
                                'name' => $block->name_page,
                                'slug' => $block->slug ? $block->slug : Strings::webalize($block->name_page),
                                'tag' => "#block-".$block->id
                            ];
                        }
                    }
                }
            }
        }

        return $this->menuBlocksItems;
    }


    /**
     * @return array
     */
    public function getMenuItems(): array
    {

        if (is_null($this->menuItems)) {
            $this->menuItems = [];

            $parentIdLevel1 = NULL;
            $parentIdLevel2 = NULL;


            /** @var \PagesEntity $page */
            foreach ($this->getPagesBuffer() as $page) {
                if (!$page->is_in_menu) {
                    if ($page->pos_level === 1) {
                        $parentIdLevel1 = NULL;
                        $parentIdLevel2 = NULL;
                    }

                    continue;
                }


                $item = (object)[
                    'pages_id' => $page->id,
                    'name' => $page->name,
                    'image' => $page->meta_image,
                    'url' => $page->full_url,
                    'blank' => $page->open_to_blank,
                    'current' => ($this->page->id == $page->id),
                    'system' => $page->system,
                    'items' => []
                ];

                if ($page->pos_level === 1) {
                    $parentIdLevel1 = $page->id;
                    $this->menuItems[$parentIdLevel1] = $item;
                }
                elseif ($parentIdLevel1 && $page->pos_level === 2) {
                    $parentIdLevel2 = $page->id;
                    if($item->current){
                        $this->menuItems[$parentIdLevel1]->current = TRUE;
                    }
                    $this->menuItems[$parentIdLevel1]->items[$parentIdLevel2] = $item;
                }
                elseif ($parentIdLevel1 && $parentIdLevel2 && $page->pos_level === 3) {
                    if($item->current){
                        $this->menuItems[$parentIdLevel1]->current = TRUE;
                    }
                    if($item->current){
                        $this->menuItems[$parentIdLevel1]->items[$parentIdLevel2]->current = TRUE;
                    }
                    $this->menuItems[$parentIdLevel1]->items[$parentIdLevel2]->items[] = $item;
                }

            }
        }

        return $this->menuItems;
    }


    /**
     * @return array
     */
    public function getFooterItems(): array
    {
        if (is_null($this->footerItems)) {
            $this->footerItems = [];

            /** @var \PagesEntity $page */
            foreach ($this->getPagesBuffer() as $page) {
                if (!$page->is_in_footer) {
                    continue;
                }

                $this->footerItems[$page->id] = (object)[
                    'name' => $page->name,
                    'url' => $page->full_url,
                    'blank' => $page->open_to_blank
                ];
            }
        }

        return $this->footerItems;
    }

    public function getSliders()
    {
        $sliders = $this->slidersRepo->findAllActiveEntities();
        return $sliders;
    }
}
