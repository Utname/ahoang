<?php

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;


/**
 * A label that exists only for printing one item of a phone list (Tisk z mobilu): the
 * product card's data plus the item's overrides, never saved. The print templates read it
 * like a queued ProductLabelsEntity. Product, header and country are given up front: a row
 * outside its selection cannot use ref().
 */
final class PrintListLabelEntity extends ProductLabelsEntity
{
	private ?ProductsEntity $listProduct = NULL;

	private ?ProductLabelHeadersEntity $listHeader = NULL;

	private ?CountriesEntity $listCountry = NULL;


	/** @param array<string, mixed> $data all product_labels columns */
	public static function create(Explorer $db, array $data, ProductsEntity $product, ?ProductLabelHeadersEntity $header, ?CountriesEntity $country): self
	{
		// select('*') keeps Nette from re-querying the table for a column it has not seen
		$row = new ActiveRow($data, $db->table('product_labels')->select('*')->where('id', -1));
		$label = new self($row);
		$label->listProduct = $product;
		$label->listHeader = $header;
		$label->listCountry = $country;

		return $label;
	}


	public function getProduct(): ?ProductsEntity
	{
		return $this->listProduct;
	}


	public function getHeader(): ?ProductLabelHeadersEntity
	{
		return $this->listHeader;
	}


	public function getCountry(): ?CountriesEntity
	{
		return $this->listCountry;
	}
}
