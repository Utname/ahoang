<?php


class SlidersEntity extends BaseEntity
{
	const DARK_FONT_COLOR = '#333333';


	/**
	 * @return string
	 */
	public function getFontStyle(): string
	{
		$style = [];

		if(isset($this->is_dark) && $this->is_dark){
			$style['color'] = self::DARK_FONT_COLOR;
		}

		$styleString = '';
		foreach($style as $key => $val){
			$styleString .= $key.': '.$val.'; ';
		}
		return $styleString;
	}
}
