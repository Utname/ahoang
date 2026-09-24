<?php

namespace App\FrontModule\Components;

class MetaInfoControl extends BaseControl
{
	/** @var ?string  */
	protected $defaultTitle = NULL;

    /** @var ?string  */
	protected $title = NULL;


	/** @var ?string */
	protected $defaultDescription = NULL;

	/** @var ?string */
	protected $description = NULL;


	/** @var ?string */
	protected $defaultImage = NULL;

	/** @var ?string */
	protected $image = NULL;


	/** @var ?string */
	protected $language = NULL;

	/** @var ?string */
	protected $websiteName = NULL;

	/** @var bool */
	protected $noindex = FALSE;


	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return ($this->title ?: ($this->defaultTitle ?: '')).' | '.$this->getWebsiteName();
	}


    /**
     * @param string|null $title
     * @return $this
     */
	public function setTitle(?string $title): MetaInfoControl
	{
		$this->title = $title;

		return $this;
	}


    /**
     * @param string|null $defaultTitle
     * @return $this
     */
	public function setDefaultTitle(?string $defaultTitle): MetaInfoControl
	{
		$this->defaultTitle = $defaultTitle;

		return $this;
	}


    /**
     * @return string
     */
	public function getDescription(): string
	{
		return $this->description ?: ($this->defaultDescription ?: '');
	}


    /**
     * @param string|null $description
     * @return $this
     */
	public function setDescription(?string $description): MetaInfoControl
	{
		$this->description = $description;

		return $this;
	}


    /**
     * @param string|null $defaultDescription
     * @return $this
     */
	public function setDefaultDescription(?string $defaultDescription): MetaInfoControl
	{
		$this->defaultDescription = $defaultDescription;

		return $this;
	}



	/**
	 * @return string
	 */
	public function getLanguage(): string
	{
		return $this->language ?: '';
	}


    /**
     * @param string|null $language
     * @return $this
     */
	public function setLanguage(?string $language): MetaInfoControl
	{
		$this->language = $language;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getWebsiteName(): string
	{
		return $this->websiteName ?: '';
	}


    /**
     * @param string|null $websiteName
     * @return $this
     */
	public function setWebsiteName(?string $websiteName): MetaInfoControl
	{
		$this->websiteName = $websiteName;

		return $this;
	}




	/**
	 * @return string
	 */
	public function getImage(): string
	{
		return $this->image ?: ($this->defaultImage ?: '');
	}


    /**
     * @param string|null $image
     * @return $this
     */
	public function setImage(?string $image): MetaInfoControl
	{
		$this->image = $image;

		return $this;
	}


	/**
	 * @param string|null $defaultImage
	 * @return $this
	 */
	public function setDefaultImage(?string $defaultImage): MetaInfoControl
	{
		$this->defaultImage = $defaultImage;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function getNoindex(): bool
	{
		return $this->noindex;
	}


	/**
	 * @param bool $noindex
	 * @return $this
	 */
	public function setNoindex(bool $noindex): MetaInfoControl
	{
		$this->noindex = $noindex;

		return $this;
	}
}
