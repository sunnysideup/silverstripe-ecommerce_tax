<?php

/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_tax
 * @description: allows adding  GST / VAT / any aother tax to an order
 *
 *
 *
 */

class GSTTaxModifier extends OrderModifier {

// ######################################## *** model defining static variables (e.g. $db, $has_one)

	/**
	 * standard SS variable
	 *
	 * @var Array
	 */
	static $db = array(
		'DefaultCountry' => 'Varchar(3)',
		'Country' => 'Varchar(3)',
		'DefaultRate' => 'Double',
		'CurrentRate' => 'Double',
		'TaxType' => "Enum('Exclusive, Inclusive','Inclusive')",
		'DebugString' => 'HTMLText',
		'RawTableValue' => 'Currency'
	);

	/**
	 * standard SS variable
	 * @var String
	 */
	public static $singular_name = "Tax Charge";
		function i18n_singular_name() { return _t("GSTTaxModifier.TAXCHARGE", "Tax Charge");}


	/**
	 * standard SS variable
	 * @var String
	 */
	public static $plural_name = "Tax Charges";
		function i18n_plural_name() { return _t("GSTTaxModifier.TAXCHARGES", "Tax Charges");}


// ######################################## *** cms variables + functions (e.g. getCMSFields, $searchableFields)



	/**
	 * standard SS method
	 * @return Object FieldSet for CMS
	 */
	function getCMSFields(){
		$fields = parent::getCMSFields();
		$fields->replaceField("Country", new DropDownField("Country", "based on a sale to ", Geoip::getCountryDropDown()));
		$fields->replaceField("Root.Main", new DropdownField("TaxType", "Tax Type", singleton($this->ClassName)->dbObject('TaxType')->enumValues()));

		$fields->removeByName("DefaultCountry");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("DefaultCountryShown", "Prices are based on sale to", $this->DefaultCountry));

		$fields->removeByName("DefaultRate");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("DefaultRateShown", "Default rate", $this->DefaultRate));

		$fields->removeByName("CurrentRate");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("CurrentRateShown", "Rate for current order", $this->CurrentRate));

		$fields->removeByName("RawTableValue");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("RawTableValueShown", "Raw table value", $this->RawTableValue));

		$fields->removeByName("DebugString");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("DebugStringShown", "Debug String", $this->DebugString));
		return $fields;
	}

// ######################################## *** other (non) static variables (e.g. protected static $special_name_for_something, protected $order)
	/**
	 * default country for tax calculations
	 * IMPORTANT: we need this variable - because in case of INCLUSIVE prices,
	 * we need to know on what country the prices are based as to be able
	 * to remove the tax for other countries.
	 * @var String
	 */
	protected static $default_country_code = "";
		static function set_default_country_code($s) {self::$default_country_code = $s;}
		static function get_default_country_code() {
			$country = self::$default_country_code;
			if(!$country) {
				$country = Geoip::$default_country_code;
			}
			return $country;
		}

	/**
	 * wording in cart for prices that are tax exclusive (tax added on top of prices)
	 * @var String
	 */
	protected static $exclusive_explanation = " (added to the prices) ";
		static function set_exclusive_explanation($s) {self::$exclusive_explanation = $s;}
		static function get_exclusive_explanation() {return self::$exclusive_explanation;}

	/**
	 * wording in cart for prices that are tax inclusive (tax is part of the prices)
	 * @var String
	 */
	protected static $inclusive_explanation = " (included in the prices) ";
		static function set_inclusive_explanation($s) {self::$inclusive_explanation = $s;}
		static function get_inclusive_explanation() {return self::$inclusive_explanation;}

	/**
	 * wording in cart for tax being based on
	 * @var String
	 */
	protected static $based_on_country_note = " - based on a sale to: ";
		static function set_based_on_country_note($s) {self::$based_on_country_note = $s;}
		static function get_based_on_country_note() {return self::$based_on_country_note;}

	/**
	 * wording in cart for prices that are include a tax refund.
	 * A refund situation applies when the prices are tax inclusive
	 * but NO tax applies to the country to which the goods are sold.
	 * E.g. for a UK shop no VAT is charged to buyers outside the EU.
	 * @var String
	 */
	protected static $refund_title = "Tax Exemption";
		static function set_refund_title($s) {self::$refund_title = $s;}
		static function get_refund_title() {return self::$refund_title;}

	/**
	 * wording in cart for prices that are tax exempt (no tax applies)
	 * @var String
	 */
	protected static $no_tax_description = "tax-exempt";
		static function set_no_tax_description($s) {self::$no_tax_description = $s;}
		static function get_no_tax_description() {return self::$no_tax_description;}

	/**
	 * name of the method in the buyable OrderItem that works out the
	 * portion without tax. You can use this method by creating your own
	 * OrderItem class and adding a method there.  This is by far the most
	 * flexible way to work out the tax on products with complex tax rules.
	 * @var String
	 */
	protected static $order_item_function_for_tax_exclusive_portion = "portionWithoutTax";//PortionWithoutTax
		static function set_order_item_function_for_tax_exclusive_portion($s) {self::$order_item_function_for_tax_exclusive_portion = $s;}
		static function get_order_item_function_for_tax_exclusive_portion() {return self::$order_item_function_for_tax_exclusive_portion;}

	/**
	 * contains all the applicable DEFAULT tax objects
	 * @var Object
	 */
	protected static $default_tax_objects = null;


	/**
	 * tells us the default tax objects tax rate
	 * @var Float
	 */
	protected static $default_tax_objects_rate = 0;


	/**
	 * contains all the applicable tax objects for the current order
	 * @var Object
	 */
	protected static $current_tax_objects = null;

	/**
	 * tells us the current tax objects tax rate
	 * @var Float
	 */
	protected static $current_tax_objects_rate = 0;

	/**
	 * any calculation messages are added to the Debug Message
	 * @var String
	 */
	protected $debugMessage = '';


// ######################################## *** CRUD functions (e.g. canEdit)
// ######################################## *** init and update functions
	/**
	 * updates database fields
	 * @param Bool $force - run it, even if it has run already
	 * @return void
	 */
	public function runUpdate($force = true) {
		//order is important!
		$this->checkField("DefaultCountry");
		$this->checkField("Country");
		$this->checkField("DefaultRate");
		$this->checkField("CurrentRate");
		$this->checkField("TaxType");
		$this->checkField("RawTableValue");
		$this->checkField("DebugString");
		parent::runUpdate($force);
	}


// ######################################## *** form functions (e. g. showform and getform)
// ######################################## *** template functions (e.g. ShowInTable, TableTitle, etc...) ... USES DB VALUES

	/**
	 * Can the user remove this modifier?
	 * standard OrderModifier Method
	 * @return Bool
	 */
	public function CanBeRemoved() {
		return false;
	}

	/**
	 * Show the GSTTaxModifier in the Cart?
	 * standard OrderModifier Method
	 * @return Bool
	 */
	public function ShowInTable() {
		return true;
	}


// ######################################## ***  inner calculations.... USES CALCULATED VALUES

	/**
	 * works out what taxes apply in the default setup.
	 * we need this, because prices may include tax
	 * based on the default tax rate.
	 *
	 *@return Object|Null - DataObjectSet of applicable taxes in the default country.
	 */
	protected function defaultTaxObjects() {
		if(!self::$default_tax_objects) {
			$defaultCountryCode = GSTTaxModifier::get_default_country_code();
			if($defaultCountryCode) {
				$this->debugMessage .= "<hr />There is a current live DEFAULT country code: ".$defaultCountryCode;
				self::$default_tax_objects = DataObject::get(
					"GSTTaxModifierOptions",
					"\"CountryCode\" = '".$defaultCountryCode."'
					AND \"DoesNotApplyToAllProducts\" = 0"
				);
				if(self::$default_tax_objects) {
					$this->debugMessage .= "<hr />there are DEFAULT tax objects available for ".$defaultCountryCode;
				}
				else {
					$this->debugMessage .= "<hr />there are no DEFAULT tax object available for ".$defaultCountryCode;
				}
			}
			else {
				$this->debugMessage .= "<hr />There is no current live DEFAULT country";
			}
		}
		self::$default_tax_objects_rate = $this->workOutSumRate(self::$default_tax_objects);
		return self::$default_tax_objects;
	}


	/**
	 * returns a data object set of all applicable tax options
	 * @return Null | Object (DataObjectSet)
	 */
	protected function currentTaxObjects() {
		if(!self::$current_tax_objects) {
			if($countryCode = $this->LiveCountry()) {
				$this->debugMessage .= "<hr />There is a current live country: ".$countryCode;
				self::$current_tax_objects = DataObject::get(
					"GSTTaxModifierOptions",
					"(\"CountryCode\" = '".$countryCode."' OR \"AppliesToAllCountries\" = 1) AND \"DoesNotApplyToAllProducts\" = 0"
				);
				if(self::$current_tax_objects) {
					$this->debugMessage .= "<hr />There are tax objects available for ".$countryCode;
				}
				else {
					$this->debugMessage .= "<hr />there are no tax objects available for ".$countryCode;
				}
			}
			else {
				$this->debugMessage .= "<hr />there is no current live country code";
			}
		}
		self::$current_tax_objects_rate = $this->workOutSumRate(self::$current_tax_objects);
		return self::$current_tax_objects;
	}

	/**
	 * returns the sum of rates for the given taxObjects
	 * @param Object - dataobjectset of tax options
	 * @return Float
	 */
	protected function workOutSumRate($taxObjects) {
		$sumRate = 0;
		if($taxObjects) {
			foreach($taxObjects as $obj) {
				$this->debugMessage .= "<hr />found ".$obj->Title();
				$sumRate += floatval($obj->Rate);
			}
		}
		else {
			$this->debugMessage .= "<hr />could not find a rate";
		}
		return $sumRate;
	}

	/**
	 * tells us if the tax for the current order is exclusive
	 * default: false
	 * @return Bool
	 */
	protected function isExclusive() {
		return $this->isInclusive() ? false : true;
	}


	/**
	 * tells us if the tax for the current order is inclusive
	 * default: true
	 * @return Bool
	 */
	protected function isInclusive() {
		$sc = SiteConfig::current_site_config();
		if($sc && isset($sc->ShopPricesAreTaxExclusive)) {
			return $sc->ShopPricesAreTaxExclusive ? false : true;
		}
		//this code is here to support e-commerce versions that
		//do not have the DB field SiteConfig.ShopPricesAreTaxExclusive
		$array = array();
		//here we have to take the default tax objects
		//because we want to know for the default country
		//that is the actual country may not have any prices
		//associated with it!
		if($objects = $this->defaultTaxObjects()) {
			foreach($objects as $obj) {
				$array[$obj->InclusiveOrExclusive] = $obj->InclusiveOrExclusive;
			}
		}
		if(count($array) < 1) {
			return true;
		}
		elseif(count($array) > 1) {
			user_error("you can not have a collection of tax objects that is both inclusive and exclusive", E_USER_WARNING);
			return true;
		}
		else {
			foreach($array as $item) {
				return $item == "Inclusive" ? true : false;
			}
		}
	}

	/**
	 * turns a standard rate into a calculation rate.
	 * That is, 0.125 for exclusive is 1/9 for inclusive rates
	 * default: true
	 * @param float $rate - input rate (e.g. 0.125 equals a 12.5% tax rate)
	 * @return float
	 */
	protected function turnRateIntoCalculationRate($rate) {
		return $this->isExclusive() ? $rate : (1 - (1 / (1 + $rate)));
	}

	/**
	 * works out the tax to pay for the order items,
	 * based on a rate and a country
	 * @param float $rate
	 * @param string $country
	 * @return float - amount of tax to pay
	 */
	protected function workoutOrderItemsTax($rate, $country) {
		$order = $this->Order();
		$itemsTotal = 0;
		if($order) {
			$items = $this->Order()->Items();
			if($items) {
				$functionName = self::$order_item_function_for_tax_exclusive_portion;
				foreach($items as $itemIndex => $item) {
					//resetting actual rate...
					$actualRate = $rate;
					$buyable = $item->Buyable();
					if($buyable) {
						$this->dealWithProductVariationException($buyable);
						if($buyable->hasExtension("GSTTaxDecorator")) {
							$excludedTaxes = $buyable->ExcludedFrom();
							$additionalTaxes = $buyable->AdditionalTax();
							if($excludedTaxes) {
								foreach($excludedTaxes as $tax) {
									$this->debugMessage .= "<hr />found tax to exclude for ".$buyable->Title.": ".$tax->Title();
									$actualRate -= $tax->Rate;
								}
							}
							if($additionalTaxes) {
								foreach($additionalTaxes as $tax) {
									if($tax->AppliesToAllCountries || $tax->CountryCode == $country) {
										$this->debugMessage .= "<hr />found tax to add for ".$buyable->Title.": ".$tax->Title();
										$actualRate += $tax->Rate;
									}
								}
							}
						}
					}
					$totalForItem = $item->Total();
					if($functionName){
						if(method_exists($item, $functionName)) {
							$totalForItem -= $item->$functionName();
						}
					}
					//turnRateIntoCalculationRate is really important -
					//a 10% rate is different for inclusive than for an exclusive tax
					$actualCalculationRate = $this->turnRateIntoCalculationRate($actualRate);
					$itemsTotal += floatval($totalForItem) * $actualCalculationRate;
				}
			}
		}
		return $itemsTotal;
	}

	/**
	 * this method is a bit of a hack.
	 * if a product variation does not have any specific tax rules
	 * but the product does, then it uses the rules from the product.
	 */
	function dealWithProductVariationException(&$buyable) {
		if($buyable instanceOf ProductVariation) {
			if(!$buyable->hasExtension("GSTTaxDecorator")) {
				if($parent = $buyable->Parent()) {
					if($parent->hasExtension("GSTTaxDecorator")) {
						$buyable = $parent;
					}
				}
			}
		}
	}

	/**
	 * works out the tax to pay for the order modifiers,
	 * based on a rate
	 * @param float $rate
	 * @return float - amount of tax to pay
	 */
	protected function workoutModifiersTax($rate) {
		$modifiersTotal = 0;
		$order = $this->Order();
		if($order) {
			if($modifiers = $order->Modifiers()) {
				$functionName = self::$order_item_function_for_tax_exclusive_portion;
				foreach($modifiers as $modifier) {
					if(!$modifier->IsRemoved()) { //we just double-check this...
						if($modifier instanceOf GSTTaxModifier) {
							//do nothing
						}
						else {
							$totalForModifier = $modifier->CalculationTotal();
							if($functionName){
								if(method_exists($modifier, $functionName)) {
									$totalForModifier -= $item->$functionName();
								}
							}
							//turnRateIntoCalculationRate is really important -
							//a 10% rate is different for inclusive than for an exclusive tax
							$calculationRate = $this->turnRateIntoCalculationRate($rate);
							$modifiersTotal += floatval($totalForModifier) * $calculationRate;
						}
					}
				}
			}
		}
		return $modifiersTotal;
	}

	/**
	 * Are there Any taxes that do not apply to all products
	 * @return Boolean
	 */
	protected function hasExceptionTaxes(){
		return DataObject::get_one("GSTTaxModifierOptions", "\"DoesNotApplyToAllProducts\" = 1") ? false : true;
	}

// ######################################## *** calculate database fields: protected function Live[field name]  ... USES CALCULATED VALUES


	/**
	 * Used to save DefaultCountry to database
	 *
	 * determines value for DB field: Country
	 * @return String
	 */
	protected function LiveDefaultCountry() {
		return self::get_default_country_code();
	}

	/**
	 * Used to save Country to database
	 *
	 * determines value for DB field: Country
	 * @return String
	 */
	protected function LiveCountry() {
		return EcommerceCountry::get_country();
	}

	/**
	 * determines value for DB field: Country
	 * @return Float
	 */
	protected function LiveDefaultRate() {
		$this->defaultTaxObjects();
		return self::$default_tax_objects_rate;
	}

	/**
	 * Used to save CurrentRate to database
	 *
	 * determines value for DB field: Country
	 * @return Float
	 */
	protected function LiveCurrentRate() {
		$this->currentTaxObjects();
		return self::$current_tax_objects_rate;
	}

	/**
	 * Used to save TaxType to database
	 *
	 * determines value for DB field: TaxType
	 * @return String (Exclusive|Inclusive)
	 */
	protected function LiveTaxType() {
		if($this->isExclusive()) {
			return "Exclusive";
		}
		return "Inclusive";
	}



	/**
	 * Used to save RawTableValue to database
	 *
	 * In case of a an exclusive rate, show what is actually added.
	 * In case of inclusive rate, show what is actually included.
	 * @return float
	 */
	protected function LiveRawTableValue() {
		$currentRate = $this->LiveCurrentRate();
		$currentCountry = $this->LiveCountry();
		$itemsTax = $this->workoutOrderItemsTax($currentRate, $currentCountry);
		$modifiersTax = $this->workoutModifiersTax($currentRate);
		return $itemsTax + $modifiersTax;
	}



	/**
	 * Used to save DebugString to database
	 * @return float
	 */
	protected function LiveDebugString() {
		return $this->debugMessage;
	}


	/**
	 * Used to save TableValue to database
	 *
	 * @return float
	 */
	protected function LiveTableValue() {
		return $this->LiveRawTableValue();
	}

	/**
	 * Used to save Name to database
	 * @return String
	 */
	protected function LiveName() {
		$finalString = "tax could not be determined";
		$countryCode = $this->LiveCountry();
		$start = '';
		$name = '';
		$end = '';
		$taxObjects = $this->currentTaxObjects();
		if($taxObjects) {
			$objectArray = array();
			foreach($taxObjects as $object) {
				$objectArray[] = $object->Name;
			}
			if(count($objectArray)) {
				$name = implode(", ", $objectArray);
			}
			if($rate = $this->LiveCurrentRate()) {
				$startString = number_format($this->LiveCurrentRate() * 100, 2) . '% ';
			}
			if( $this->isExclusive()) {
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
			if($this->hasExceptionTaxes()) {
				$finalString = self::$no_tax_description;
			}
		}
		if($countryCode && $finalString) {
			$countryName = Geoip::countryCode2name($countryCode);
			if(self::$based_on_country_note && $countryName  && $countryCode != self::get_default_country_code()) {
				$finalString .= self::$based_on_country_note.$countryName;
			}
		}
		return $finalString;
	}


	/**
	 * Used to save CalculatedTotal to database

	 * works out the actual amount that needs to be deducted / added.
	 * The exclusive case is easy: just add the applicable tax
	 *
	 * The inclusive case: work out what was included and then work out what is applicable
	 * (current), then work out the difference.
	 *
	 * @return Float
	 */
	protected function LiveCalculatedTotal() {
		if($this->isExclusive()) {
			return $this->LiveRawTableValue();
		}
		else {
			$defaultRate = $this->LiveDefaultRate();
			$defaultCountry = $this->LiveDefaultCountry();
			$defaultItemsTax = $this->workoutOrderItemsTax($defaultRate, $defaultCountry);
			$defaultModifiersTax = $this->workoutModifiersTax($defaultRate);
			$shownToPay = $defaultItemsTax + $defaultModifiersTax;
			$currentRate = $this->LiveCurrentRate();
			$currentCountry = $this->LiveCountry();
			$currentItemsTax = $this->workoutOrderItemsTax($currentRate, $currentCountry);
			$currentModifiersTax = $this->workoutModifiersTax($currentRate);
			$actualNeedToPay = $currentItemsTax + $currentModifiersTax;
			//show what actually needs to be paid, minus what is already showing.
			return $actualNeedToPay - $shownToPay;
		}
	}


// ######################################## *** Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)

// ######################################## *** standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)

// ######################################## *** AJAX related functions

// ######################################## *** debug functions


	function DebugMessage () {
		if(Director::isDev()) {return $this->debugMessage;}
	}


	/**
	 * DEPRECIATED
	 */
	protected static $fixed_country_code = "";
		static function set_fixed_country_code($s) {
			user_error("GSTTaxModifier::fixed_country_code is no longer in use, please use EcommerceCountry::set_fixed_country_code", E_USER_NOTICE);
			EcommerceCountry::set_fixed_country_code($s);
		}
		static function get_fixed_country_code() {return EcommerceCountry::get_fixed_country_code();}

	/**
	 * DEPRECIATED
	 */
	static function override_country($s) {
		user_error("GSTTaxModifier::override_country is no longer in use, please use EcommerceCountry::set_fixed_country_code", E_USER_NOTICE);
		EcommerceCountry::set_fixed_country_code($s);
	}

}













