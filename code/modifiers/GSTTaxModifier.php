<?php

/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_modifiers
 * @description: allows adding  GST sales tax to order
 *
 * NOTA BENE :: NOTA BENE :: NOTA BENE :: NOTA BENE :: NOTA BENE ::
 * @important: in the order templates, change as follows:
 * FROM: <td id="$TableTotalID" class="price"><% if IsChargeable %>$Amount.Nice<% else %>-$Amount.Nice<% end_if %></td>
 * TO: <td id="$TableTotalID" class="price">$TableValue</td>
 *
 */

class GSTTaxModifier extends OrderModifier {

// ######################################## *** model defining static variables (e.g. $db, $has_one)

	static $db = array(
		'Country' => 'Text',
		'Rate' => 'Double',
		'TaxType' => "Enum('Exclusive, Inclusive','Exclusive')",
		'IsRefundSituation' => "Boolean",
		'DebugString' => 'HTMLText',
		'TaxableAmount' => 'Currency',
		'RawTableValue' => 'Currency'
	);

	public static $defaults = array("Type" => "Chargeable");

// ######################################## *** cms variables + functions (e.g. getCMSFields, $searchableFields)

	public static $singular_name = "Tax Charge";
		function i18n_single_name() { return _t("GSTTaxModifier.TAXCHARGE", "Tax Charge");}

	public static $plural_name = "Tax Charges";
		function i18n_plural_name() { return _t("GSTTaxModifier.TAXCHARGES", "Tax Charges");}

	function getCMSFields(){
		$fields = parent::getCMSFields();
		$fields->replaceField("Country", new DropDownField("Country", "Country", Geoip::getCountryDropDown()));
		$fields->removeByName("Rate");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("RateShown", "Rate", $this->Rate));
		$fields->replaceField("Root.Main", new DropdownField("TaxType", "Tax Type", singleton($this->ClassName)->dbObject('TaxType')->enumValues()));
		$fields->removeByName("RawTableValueShown");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("RawTableValueShown", "Raw table value", $this->RawTableValue));
		$fields->removeByName("TaxableAmountShown");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("TaxableAmountShown", "Taxable Amount", $this->TaxableAmount));
		$fields->removeByName("DebugStringShown");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("DebugStringShown", "Debug String", $this->DebugString));
		return $fields;
	}

// ######################################## *** other (non) static variables (e.g. protected static $special_name_for_something, protected $order)

	protected static $default_country_code = "NZ";
		static function set_default_country_code($v) {self::$default_country_code = $v;}
		static function get_default_country_code() {return self::$default_country_code;}

	protected static $fixed_country_code = "";
		static function set_fixed_country_code($v) {self::$fixed_country_code = $v;}
		static function get_fixed_country_code() {return self::$fixed_country_code;}

	protected static $exclusive_explanation = " (added to the above price) ";
		static function set_exclusive_explanation($v) {self::$exclusive_explanation = $v;}
		static function get_exclusive_explanation() {return self::$exclusive_explanation;}

	protected static $inclusive_explanation = " (included in the above price) ";
		static function set_inclusive_explanation($v) {self::$inclusive_explanation = $v;}
		static function get_inclusive_explanation() {return self::$inclusive_explanation;}

	protected static $based_on_country_note = " - based on a sale to: ";
		static function set_based_on_country_note($v) {self::$based_on_country_note = $v;}
		static function get_based_on_country_note() {return self::$based_on_country_note;}

	protected static $refund_title = "Tax Exemption";
		static function set_refund_title($v) {self::$refund_title = $v;}
		static function get_refund_title() {return self::$refund_title;}

	protected static $no_tax_description = "tax-exempt";
		static function set_no_tax_description($v) {self::$no_tax_description = $v;}
		static function get_no_tax_description() {return self::$no_tax_description;}

	protected static $order_item_function_for_tax_exclusive_portion = "";//PortionWithoutTax
		static function set_order_item_function_for_tax_exclusive_portion($v) {self::$order_item_function_for_tax_exclusive_portion = $v;}
		static function get_order_item_function_for_tax_exclusive_portion() {return self::$order_item_function_for_tax_exclusive_portion;}

	protected static $default_tax_objects = null;

	static function override_country($countryCode) {
		user_error("GSTTaxModifier::override_country is no longer in use, please use GSTTaxModifier::set_fixed_country_code", E_USER_NOTICE);
		self::set_fixed_country_code($countryCode);
	}

	protected $debugMessage = '';


// ######################################## *** CRUD functions (e.g. canEdit)
// ######################################## *** init and update functions

	public function runUpdate() {
		$this->checkField("Country");
		$this->checkField("IsRefundSituation");
		$this->checkField("Rate");
		$this->checkField("TaxType");
		$this->checkField("DebugString");
		$this->checkField("TaxableAmount");
		$this->checkField("RawTableValue");
		parent::runUpdate();
	}


// ######################################## *** form functions (e. g. showform and getform)
// ######################################## *** template functions (e.g. ShowInTable, TableTitle, etc...) ... USES DB VALUES


	function CanBeRemoved() {
		return false;
	}

	function ShowInTable() {
		return true;
	}

	function TableValue() {
		return $this->RawTableValue;
	}


// ######################################## ***  inner calculations.... USES CALCULATED VALUES


	protected function DefaultTaxObjects() {
		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		if(!self::$default_tax_objects) {
			$defaultCountryCode = GSTTaxModifier::get_default_country_code();
			if($defaultCountryCode) {
				$this->debugMessage .= "<hr />There are current live DEFAULT country code: ".$defaultCountryCode;
				if( self::$default_tax_objects = DataObject::get("GSTTaxModifierOptions", "{$bt}CountryCode{$bt} = '".$defaultCountryCode."'")){
					$this->debugMessage .= "<hr />there are DEFAULT tax objects available for ".$defaultCountryCode;
				}
				else {
					$this->debugMessage .= "<hr />there are no DEFAULT tax object available for ".$defaultCountryCode;
				}
			}
			else {
				$this->debugMessage .= "<hr />There are no current live DEFAULT tax object";
			}
		}
		return self::$default_tax_objects;
	}

	protected function TaxObjects() {
		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		if($countryCode = $this->LiveCountry()) {
			$this->debugMessage .= "<hr />There is a current live country: ".$countryCode;
			if($dos = DataObject::get("GSTTaxModifierOptions", "{$bt}CountryCode{$bt} = '".$countryCode."' OR \"AppliesToAllCountries\" = 1")) {
				$this->debugMessage .= "<hr />There are tax objects available for ".$countryCode;
			}
			else {
				$this->debugMessage .= "<hr />there are no tax objects available for ".$countryCode;
			}
		}
		else {
			$this->debugMessage .= "<hr />There are no current live tax objects (no country specified), using default country instead";
			$dos = $this->DefaultTaxObjects();
		}
		return $dos;
	}

	protected function workOutSumRate($taxObjects) {
		$sumRate = 0;
		if($taxObjects) {
			foreach($taxObjects as $obj) {
				$this->debugMessage .= "<hr />found a rate of ".$obj->Rate;
				$sumRate += floatval($obj->Rate);
			}
		}
		else {
			$this->debugMessage .= "<hr />could not find a rate";
		}
		return $sumRate;
	}

	/*
	* returns boolean value true / false
	*/
	public function IsExclusive() {
		$countryCode = $this->LiveCountry();
		if($obj = $this->TaxObjects()) {
			return $obj->TaxType == "Exclusive";
		}
		return false;
	}


// ######################################## *** calculate database fields: protected function Live[field name]  ... USES CALCULATED VALUES

	//this occurs when there is no country match and the rate is inclusive
	protected function LiveIsRefundSituation() {
		if(!$this->TaxObjects()) {
			if($this->DefaultTaxObjects()) {
				if(!$this->IsExclusive()) {
					//IMPORTANT
					$this->debugMessage .= "<hr />IS REFUND SITUATION";
					$this->Type = "Deductable";
					return 1;
				}
			}
		}
		return 0;
	}

	protected function LiveTaxType() {
		if($this->IsExclusive()) {
			return "Exclusive";
		}
		return "Inclusive";
	}

	protected function LiveCountry() {
		return EcommerceCountry::get_country();
	}

	protected function LiveRate() {
		if($this->LiveIsRefundSituation()) {
			//need to use default here as refund is always based on default country!
			$taxObjects = $this->DefaultTaxObjects();
			if($sumRate = $this->workOutSumRate($taxObjects)) {
				$this->debugMessage .= "<hr />using DEFAULT (REFUND) rate: ".$sumRate;
				$rate = $sumRate;
			}
			else {
				$this->debugMessage .= "<hr />no DEFAULT (REFUND) rate found, using: 0 ";
				$rate = 0;
			}
		}
		else {
			$taxObjects = $this->TaxObjects();
			if($sumRate = $this->workOutSumRate($taxObjects)) {
				$this->debugMessage .= "<hr />using rate: ".$sumRate;
				$rate = $sumRate;
			}
			else {
				$this->debugMessage .= "<hr />no rate found, using: 0";
				$rate = 0;
			}
		}
		return $rate;
	}


	// note that this talks about AddedCharge, which can actually be zero while the table shows a value (inclusive case).

	function LiveCalculationValue() {
		if($this->LiveIsRefundSituation()) {
			return $this->LiveRawTableValue();
		}
		else {
			return $this->IsExclusive() ? $this->LiveRawTableValue() : 0;
		}
	}

	function LiveRawTableValue() {
		$rate = ($this->IsExclusive() ? $this->LiveRate() : (1 - (1 / (1 + $this->LiveRate()))));
		return $this->LiveTaxableAmount() * $rate;
	}

	function LiveDebugString() {
		return $this->debugMessage;
	}

	function LiveTaxableAmount() {
		$order = $this->Order();
		$deduct = 0;
		if($functionName = self::$order_item_function_for_tax_exclusive_portion) {
			$items = $this->Order()->Items();
			if($items) {
				foreach($items as $itemIndex => $item) {
					if(method_exists($item, $functionName)) {
						$deduct += $item->$functionName();
					}
				}
			}
		}
		$subTotal = $order->SubTotal();
		$modifierTotal = $order->ModifiersSubTotal(array("GSTTaxModifier"));
		$this->debugMessage .= "<hr />using sub-total: ".$subTotal;
		$this->debugMessage .= "<hr />using modifer-total: ".$modifierTotal;
		$this->debugMessage .= "<hr />using non-taxable portion: ".$deduct;
		return  $subTotal + $modifierTotal - $deduct;
	}

	protected function LiveName() {
		$finalString = "tax could not be determined";
		$countryCode = $this->LiveCountry();
		if($this->LiveIsRefundSituation()) {
			$finalString = self::$refund_title;
		}
		else {
			$start = '';
			$name = '';
			$end = '';
			$taxObjects = $this->TaxObjects();
			if($taxObjects) {
				$objectArray = array();
				foreach($taxObjects as $object) {
					$objectArray[] = $object->Name;
				}
				if(count($objectArray)) {
					$name = implode(", ", $objectArray);
				}
				if($rate = $this->LiveRate()) {
					$startString = number_format($this->LiveRate() * 100, 2) . '% ';
				}
				if( $this->IsExclusive()) {
					$endString = self::$exclusive_explanation;
				}
				else {
					$endString = self::$inclusive_explanation;
				}
				if($name && $rate) {
					$finalString = $startString.$name.$endString;
				}
			}
			else {
				$finalString = self::$no_tax_description;
			}
		}
		if($countryCode && $finalString) {
			$countryName = Geoip::countryCode2name($countryCode);
			if(self::$based_on_country_note && $countryName  && $countryCode != self::$default_country_code) {
				$finalString .= self::$based_on_country_note.$countryName;
			}
		}
		return $finalString;
	}




// ######################################## *** Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)

	public function IsDeductable() {
		if($this->LiveIsRefundSituation()) {
			return true;
		}
		return false;
	}

	public function IsNoChange() {
		if(!$this->IsExclusive()) {
			return false;
		}
		return true;
	}

	public function IsChargeable() {
		if($this->IsDeductable() || $this->IsNoChange()) {
			return false;
		}
		else {
			return true;
		}
	}
// ######################################## *** standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)

	public function onBeforeWrite() {
		parent::onBeforeWrite();
	}

// ######################################## *** AJAX related functions

// ######################################## *** debug functions


	function DebugMessage () {
		if(Director::isDev()) {return $this->debugMessage;}
	}

}













