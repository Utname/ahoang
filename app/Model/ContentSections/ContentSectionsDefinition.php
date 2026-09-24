<?php

class ContentSectionsDefinition
{
	//definition keys
	const NAME = 'name';
	const PARAMETERS = 'parameters';

	//sections
	const SECTION_TYPE_STANDARD = 1;
	const SECTION_TYPE_COLLAPSE = 2;

	const SECTION_TYPES = [
		self::SECTION_TYPE_STANDARD => [
			self::NAME => 'admin.contentBlocks.sections.standard',
			self::PARAMETERS => []
		],
		/*
		self::SECTION_TYPE_COLLAPSE => [
			'name' => 'Otevírací / zavírací',
			'parameters' => [
				'isOpen' => [
					'name' => 'Otevřená po načtení',
					'type' => self::PARAMETER_TYPE_BOOLEAN,
					'defaultValue' => 0
				]
			]
		]*/
	];
}
