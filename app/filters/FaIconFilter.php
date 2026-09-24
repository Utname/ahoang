<?php


class FaIconFilter
{
	/**
	 * @param string $iconClass
	 * @param string $requiredType
	 * @return string
	 */
    public function process(string $iconClass, string $requiredType = 'fa'): string
    {
	    return str_replace(['fas ', 'fab ', 'far ', 'fal ', 'fa '], $requiredType.' ', $iconClass);
    }
}
