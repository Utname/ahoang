<?php


class UnitsEntity extends BaseEntity
{
	/**
	 * @return string
	 */
	public function getShortcut(): string
	{
		return implode('', [
			$this->shortcut,
			$this->shortcut_upper_index ? ('<sup>'.$this->shortcut_upper_index.'</sup>') : '',
			$this->shortcut_lower_index ? ('<sub>'.$this->shortcut_lower_index.'</sub>') : '',
		]);
	}
}
