<?php

class ContentBlocksDefinition
{
    //default content block for new entities
    const DEFAULT_CONTENT_BLOCK_ID = self::BLOCK_TYPE_TEXT;

    //definition keys
    const NAME = 'name';
    const HELP_TEXT = 'help_text';
    const TABS = 'tabs';
    const PARAMETERS = 'parameters';
    const TYPE = 'type';
    const COLS = 'cols';
    const OPTIONS__DB_PAIRS = 'options__db_pairs';
    const OPTIONS__DB_QUERY = 'options__db_query';
    const OPTIONS = 'options';
    const INPUTS = 'inputs';
    const CUSTOM_CLASS = 'custom_class';

    //parameter types
    const PARAMETER_TYPE_TEXT = 1;
    const PARAMETER_TYPE_TEXTAREA = 2;
    const PARAMETER_TYPE_WSW = 3;
    const PARAMETER_TYPE_IMAGE = 4;
    const PARAMETER_TYPE_IMAGES = 5;
    const PARAMETER_TYPE_FILES = 6;
    const PARAMETER_TYPE_BOOLEAN = 7;
    const PARAMETER_TYPE_SELECT = 8;
    const PARAMETER_TYPE_FILE = 9;
    const PARAMETER_TYPE_CLEARFIX = 10;
    const PARAMETER_TYPE_CHECKBOX = 11;
    const PARAMETER_TYPE_MULTIPLICATOR = 12; //only basic inputs are possible to use at this time. TODO: IMAGE, IMAGES, FILE, FILES, CHECKBOX, MULTISELECT
    const PARAMETER_TYPE_MULTISELECT = 13;

    //blocks
    const BLOCK_TYPE_TEXT = 1;
    //const BLOCK_TYPE_CONTENT = 2;
    const BLOCK_TYPE_FORM_WITH_BRANCHES = 3;
    const BLOCK_TYPE_PRODUCTS = 4;
    const BLOCK_TYPE_CATEGORIES = 5;
    const BLOCK_TYPE_MAP = 6;
    const BLOCK_TYPE_TEXT_IMAGE = 8;


    const BLOCK_TYPES = [
        self::BLOCK_TYPE_TEXT => [
            self::NAME => 'admin.contentBlocks.blocks.text',
            self::TABS => [
                'text' => [
                    self::NAME => 'admin.contentBlocks.tabs.text',
                    self::PARAMETERS => ['title', 'perex', 'text', 'buttons']
                ],
                'image' => [
                    self::NAME => 'admin.contentBlocks.tabs.image',
                    self::PARAMETERS => ['image_pos', 'clearfix', 'image']
                ],
                'settings' => [
                    self::NAME => 'admin.contentBlocks.tabs.settings',
                    self::PARAMETERS => ['text_size', 'background_color']
                ],
            ],
            self::PARAMETERS => [
                'title' => [
                    self::NAME => 'admin.terms.title',
                    self::CUSTOM_CLASS => 'form-control-bigger',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'perex' => [
                    self::NAME => 'admin.terms.perex',
                    self::TYPE => self::PARAMETER_TYPE_TEXTAREA,
                ],
                'text' => [
                    self::NAME => 'admin.terms.text',
                    self::TYPE => self::PARAMETER_TYPE_WSW,
                ],
                'buttons' => [
                    self::NAME => 'admin.terms.buttons',
                    self::TYPE => self::PARAMETER_TYPE_MULTIPLICATOR,
                    self::INPUTS => [
                        'button_text' => [
                            self::NAME =>  'admin.terms.text',
                            self::TYPE => self::PARAMETER_TYPE_TEXT,
                            self::COLS => 6
                        ],
                        'button_link' => [
                            self::NAME => 'admin.terms.link',
                            self::TYPE => self::PARAMETER_TYPE_TEXT,
                            self::COLS => 6
                        ],
                    ]
                ],
                'image' => [
                    self::NAME => 'admin.terms.image',
                    self::TYPE => self::PARAMETER_TYPE_IMAGE,
                    self::COLS => '6'
                ],
                'clearfix' => [
                    self::TYPE => self::PARAMETER_TYPE_CLEARFIX,
                ],
                'image_pos' => [
                    self::NAME => 'admin.terms.imagePos',
                    self::TYPE => self::PARAMETER_TYPE_SELECT,
                    self::OPTIONS => [
                        'left' => 'admin.terms.left',
                        'right' => 'admin.terms.right'
                    ],
                    self::COLS => '6'
                ],
                'text_size' => [
                    self::NAME => 'admin.terms.textSize',
                    self::TYPE => self::PARAMETER_TYPE_SELECT,
                    self::OPTIONS => [
                        'standard' => 'admin.terms.standardText',
                        'small' => 'admin.terms.smallText'
                    ],
                    self::COLS => '6'
                ],
                'background_color' => [
                    self::NAME => 'admin.terms.backgroundColor',
                    self::TYPE => self::PARAMETER_TYPE_SELECT,
                    self::OPTIONS => [
                        'standard' => 'admin.terms.standard',
                        'grey' => 'admin.terms.grey'
                    ],
                    self::COLS => '6'
                ],
            ]
        ],
        self::BLOCK_TYPE_FORM_WITH_BRANCHES => [
            self::NAME => 'Formulář - Kontakt s údaji',
            self::TABS => [
                'text' => [
                    self::NAME => 'admin.contentBlocks.tabs.text',
                    self::PARAMETERS => ['title','text','branches','links']
                ],
                'form' => [
                    self::NAME => 'Formulář',
                    self::PARAMETERS => ['formTitle', 'formBtnText']
                ],
                'settings' => [
                    self::NAME => 'admin.contentBlocks.tabs.settings',
                    self::PARAMETERS => ['namePage', 'slug']
                ],
            ],
            self::PARAMETERS => [
                'title' => [
                    self::NAME => 'admin.terms.title',
                    self::CUSTOM_CLASS => 'form-control-bigger',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'text' => [
                    self::NAME => 'Text',
                    self::COLS => 12,
                    self::TYPE => self::PARAMETER_TYPE_WSW,
                ],
                'links' => [
                    self::NAME => 'Sociální sítě a odkazy',
                    self::TYPE => self::PARAMETER_TYPE_MULTISELECT,
                    self::COLS => '6',
                    self::OPTIONS__DB_PAIRS => 'links__id__name',
                ],
                'formTitle' => [
                    self::NAME => 'Nadpis ',
                    self::COLS => 6,
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'formBtnText' => [
                    self::NAME => 'Tlačítko: Odeslat',
                    self::COLS => 6,
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'namePage' => [
                    self::NAME => 'Název v menu ',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'slug' => [
                    self::NAME => 'URL',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'branches' => [
                    self::NAME => 'Pobočky',
                    self::TYPE => self::PARAMETER_TYPE_MULTISELECT,
                    self::COLS => '6',
                    self::OPTIONS__DB_PAIRS => 'branches_mld__id__name',
                ],
            ]
        ],
        self::BLOCK_TYPE_PRODUCTS => [
            self::NAME => 'Produkty',
            self::TABS => [
                'content' => [
                    self::NAME => 'admin.contentBlocks.tabs.content',
                    self::PARAMETERS => ['title', 'btnMoreProductsText', 'countProducts']
                ],
                'settings' => [
                    self::NAME => 'admin.contentBlocks.tabs.settings',
                    self::PARAMETERS => ['namePage', 'slug']
                ],
            ],
            self::PARAMETERS => [
                'title' => [
                    self::NAME => 'admin.terms.title',
                    self::CUSTOM_CLASS => 'form-control-bigger',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'btnMoreProductsText' => [
                    self::NAME => 'Tlačítko další: Text',
                    self::COLS => 6,
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'countProducts' => [
                    self::NAME => 'Počet produktů na stránku',
                    self::COLS => 6,
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'namePage' => [
                    self::NAME => 'Název v menu ',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'slug' => [
                    self::NAME => 'URL',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
            ]
        ],
        self::BLOCK_TYPE_CATEGORIES => [
            self::NAME => 'Sortiment (Kategorie)',
            self::TABS => [
                'text' => [
                    self::NAME => 'admin.contentBlocks.tabs.text',
                    self::PARAMETERS => ['title', 'categoriesSlider', 'categories']
                ],
                'settings' => [
                    self::NAME => 'admin.contentBlocks.tabs.settings',
                    self::PARAMETERS => ['namePage', 'slug']
                ],
            ],
            self::PARAMETERS => [
                'title' => [
                    self::NAME => 'admin.terms.title',
                    self::CUSTOM_CLASS => 'form-control-bigger',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'categoriesSlider' => [
                    self::NAME => 'Kategorie (Slider)',
                    self::TYPE => self::PARAMETER_TYPE_MULTISELECT,
                    self::COLS => '6',
                    self::OPTIONS__DB_PAIRS => 'categories_mld__id__name',
                ],
                'categories' => [
                    self::NAME => 'Kategorie',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_MULTISELECT,
                    self::OPTIONS__DB_PAIRS => 'categories_mld__id__name',
                ],
                'namePage' => [
                    self::NAME => 'Název v menu',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'slug' => [
                    self::NAME => 'URL',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
            ]
        ],
        self::BLOCK_TYPE_TEXT_IMAGE => [
            self::NAME => 'admin.contentBlocks.blocks.textImage',
            self::TABS => [
                'contentText' => [
                    self::NAME => 'Textový obsah',
                    self::PARAMETERS => ['title', 'text', 'btnText', 'btnLink']
                ],
                'images' => [
                    self::NAME => 'Obrázky',
                    self::PARAMETERS => ['images']
                ],
                'options' => [
                    self::NAME => 'Nastavení',
                    self::PARAMETERS => ['namePage', 'slug']
                ],
            ],
            self::PARAMETERS => [
                'title' => [
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                    self::NAME => 'Nadpis',
                    self::COLS => '12',
                ],
                'text' => [
                    self::NAME => 'Text',
                    self::COLS => '12',
                    self::TYPE => self::PARAMETER_TYPE_WSW,
                ],
                'btnText' => [
                    self::NAME => 'Tlačítko: Text',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'btnLink' => [
                    self::NAME => 'Tlačítko: Odkaz',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'images' => [
                    self::NAME => 'Obrázky',
                    self::COLS => '12',
                    self::TYPE => self::PARAMETER_TYPE_IMAGES,
                ],
                'namePage' => [
                    self::NAME => 'Název v menu',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
                'slug' => [
                    self::NAME => 'URL',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                ],
            ]
        ],
        self::BLOCK_TYPE_MAP => [
            self::NAME => 'Mapa s výběrem pobočky',
            self::TABS => [
                'contentText' => [
                    self::NAME => 'Textový obsah',
                    self::PARAMETERS => ['titleBranches', 'branches']
                ],
            ],
            self::PARAMETERS => [
                'titleBranches' => [
                    self::TYPE => self::PARAMETER_TYPE_TEXT,
                    self::NAME => 'Nadpis',
                    self::COLS => '12',
                ],
                'branches' => [
                    self::NAME => 'Pobočky',
                    self::COLS => '6',
                    self::TYPE => self::PARAMETER_TYPE_MULTISELECT,
                    self::OPTIONS__DB_PAIRS => 'branches_mld__id__name',
                ],
            ]
        ],

    ];
}
