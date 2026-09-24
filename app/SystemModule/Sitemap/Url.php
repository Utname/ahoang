<?php

namespace App\SystemModule;

class Url
{
	/**
	 * @param string $loc
	 * @param \DateTime $lastmod
	 * @param string $frequency
	 * @param float $priority
	 */
	public function __construct(public string $loc, public \DateTime $lastmod, public string $frequency, public float $priority)
	{
	}
}
