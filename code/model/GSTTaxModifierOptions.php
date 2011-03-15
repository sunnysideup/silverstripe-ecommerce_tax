<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 **/

class GSTTaxModifierOptions extends DataObject {

	static $db = array(
		"CountryCode" => "Varchar(3)",
		"Code" => "Varchar(12)",
		"Name" => "Varchar(175)",
		"InclusiveOrExclusive" => "Enum('Inclusive,Exclusive', 'Exclusive')",
		"Rate" => "Double",
		"PriceSuffix" => "Varchar(25)",
		"AppliesToAllCountries" => "Boolean"
	);

	public static $defaults = array(
		"CountryCode" => "NZ"
	);
	public static $indexes = array(
		"Code" => true
	);

	public static $searchable_fields = array(
		"Code",
		"Name" => "PartialMatchFilter",
	);

	public static $field_labels = array(
		"CountryCode" => "Country Code",
		"Code" => "Code for tax",
		"Name" => "Name for tax",
		"InclusiveOrExclusive" => "Inclusive/Exclusive",
		"Rate" => "Rate (e.g. 0.125)",
		"PriceSuffix" => "Price Suffix"
	);

	public static $summary_fields = array(
		"CountryCode",
		"Code",
		"Name",
		"InclusiveOrExclusive",
		"Rate"
	);

	public static $singular_name = "Tax Option";

	public static $plural_name = "Tax Options";


	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->replaceField("CountryCode", new DropDownField("CountryCode", self::$field_labels["CountryCode"], Geoip::getCountryDropDown()));
		return $fields;
	}

	function onBeforeWrite() {
		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		if($this->ID && DataObject::get_one("GSTTaxModifierOptions", "{$bt}CountryCode{$bt} = '".$this->CountryCode."' AND {$bt}ID{$bt} <> ".$this->ID)) {
			die("can not save two taxes for one country!");
		}
		parent::onBeforeWrite();
	}


}

