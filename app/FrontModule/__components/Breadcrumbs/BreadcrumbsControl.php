<?php

namespace App\FrontModule\Components;

class BreadcrumbsControl extends BaseControl
{
	/** @var array */
	protected $parts = [];

	/** @var string */
	protected $title = '';

	/** @var string */
	protected $text = '';


	/**
	 * @return array
	 */
	public function getParts(): array
	{
		return $this->parts;
	}


	/**
	 * @param array $parts
	 * @return BreadcrumbsControl
	 */
	public function setParts(array $parts): BreadcrumbsControl
	{
		$this->parts = $parts;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->title;
	}


	/**
	 * @param string $title
	 * @return BreadcrumbsControl
	 */
	public function setTitle(string $title): BreadcrumbsControl
	{
		$this->title = $title;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getText(): string
	{
		return $this->text;
	}


	/**
	 * @param string $text
	 * @return BreadcrumbsControl
	 */
	public function setText(string $text): BreadcrumbsControl
	{
		$this->text = $text;

		return $this;
	}


	/**
	 * @param string $title
	 * @param string $url
	 * @return $this
	 */
	public function addPart(string $title, string $url): BreadcrumbsControl
	{
		$this->parts[] = (object)[
			'title' => $title,
			'url' => $url
		];

		return $this;
	}
}
