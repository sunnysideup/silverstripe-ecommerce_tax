<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 **/

class GSTTaxModifierOptions extends DataObject {

	/**
	 * standard SS variable
	 * @var Array
	 */
	static $db = array(
		"CountryCode" => "Varchar(3)",
		"Code" => "Varchar(12)",
		"Name" => "Varchar(175)",
		"InclusiveOrExclusive" => "Enum('Inclusive,Exclusive', 'Inclusive')",
		"Rate" => "Double",
		"PriceSuffix" => "Varchar(25)",
		"DoesNotApplyToAllProducts" => "Boolean",
		"AppliesToAllCountries" => "Boolean"
	);

	/**
	 * standard SS variable
	 * @var Array
	 */
	public static $defaults = array(
		"InclusiveOrExclusive" => "Inclusive"
	);

	/**
	 * standard SS variable
	 * @var Array
	 */
	public static $casting = array(
		"CountryName" => "Varchar"
	);

	/**
	 * standard SS variable
	 * @var Array
	 */
	public static $indexes = array(
		"Code" => true
	);

	/**
	 * standard SS variable
	 * @var Array
	 */
	public static $searchable_fields = array(
		"Code" => "PartialMatchFilter",
		"Name" => "PartialMatchFilter"
	);

	/**
	 * standard SS variable
	 * @var Array
	 */
	public static $field_labels = array(
		"CountryName" => "Country Name",
		"CountryCode" => "Country Code",
		"Code" => "Code for tax",
		"Name" => "Name for tax",
		"InclusiveOrExclusive" => "Inclusive/Exclusive",
		"Rate" => "Rate (e.g. 0.125 = 12.5%)",
		"PriceSuffix" => "Price Suffix",
		"DoesNotApplyToAllProducts" => "Added to individual products only, if you un-tick this box, you can still 'exclude' any product from this tax, but the tax will be included by default."
	);


	/**
	 * standard SS variable
	 * @var Array
	 */
	public static $summary_fields = array(
		"CountryName",
		"Code",
		"Name",
		"InclusiveOrExclusive",
		"Rate"
	);

	/**
	 * standard SS variable
	 * @var String
	 */
	public static $singular_name = "Tax Option";
		function i18n_singular_name() { return _t("GSTTaxModifierOptions.TAXOPTION", "Tax Option");}


	/**
	 * standard SS variable
	 * @var String
	 */
	public static $plural_name = "Tax Options";
		function i18n_plural_name() { return _t("GSTTaxModifierOptions.TAXOPTIONS", "Tax Options");}

	/**
	 * standard SS method
	 * @return FieldSet
	 */
	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->replaceField("CountryCode", new DropDownField("CountryCode", self::$field_labels["CountryCode"], Geoip::getCountryDropDown()));
		$InclusiveOrExclusive = "Inclusive";
		if($this->EcomConfig()->ShopPricesAreTaxExclusive) {
			$InclusiveOrExclusive = "Exclusive";
		}
		$fields->replaceField("InclusiveOrExclusive", new ReadonlyField("InclusiveOrExclusive", "This tax is: $InclusiveOrExclusive, you can change this setting in the e-commerce configuration."));
		return $fields;
	}

	function Title() {return $this->getTitle();}
	function getTitle() {
		if($this->AppliesToAllCountries) {
			$country = _t("GSTTExModifierOption.WORLDWIDE", "world-wide");
		}
		else {
			$country = $this->CountryCode;
		}
		return $this->Name." ($country, ".number_format($this->Rate * 100, 2) . '%)';

	}

	/**
	 * standard SS method
	 */
	function populateDefaults(){
		parent::populateDefaults();
		if($this->EcomConfig()->ShopPricesAreTaxExclusive) {
			$this->InclusiveOrExclusive = "Exclusive";
		}
		else {
			$this->InclusiveOrExclusive = "Inclusive";
		}
	}

	/**
	 * standard SS method
	 */
	function onBeforeWrite(){
		parent::onBeforeWrite();
		if($this->EcomConfig()->ShopPricesAreTaxExclusive) {
			$this->InclusiveOrExclusive = "Exclusive";
		}
		else {
			$this->InclusiveOrExclusive = "Inclusive";
		}
	}

	/**
	 * standard SS method
	 */
	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		DB::query("
			UPDATE \"GSTTaxModifierOptions\"
			SET \"InclusiveOrExclusive\" = 'Inclusive'
			WHERE \"InclusiveOrExclusive\" <> 'Exclusive'"
		);
	}

	/**
	 * returns the instance of EcommerceDBConfig
	 *
	 * @return EcommerceDBConfig
	 **/
	public function EcomConfig(){
		return EcommerceDBConfig::current_ecommerce_db_config();
	}

	public function CountryName(){ return $this->getCountryName();}
	public function getCountryName(){
		return Geoip::countryCode2name($this->CountryCode);
	}
}

