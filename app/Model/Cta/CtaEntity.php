<?php


class CtaEntity extends BaseEntity
{
	/**
	 * @return string
	 */
	public function getBgStyle()
	{
		$style = [];
		if($this->bg_color){
			$style[] = 'background-color: '.$this->bg_color.' !important;';
		}

		return implode(' ', $style);
	}
	/**
	 * @return string
	 */
	public function getTextStyle()
	{
		$style = [];
		if($this->text_color){
			$style[] = 'color: '.$this->text_color.' !important;';
		}

		return implode(' ', $style);
	}
}
