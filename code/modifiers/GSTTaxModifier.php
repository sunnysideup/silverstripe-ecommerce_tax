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

	/**
	 *
	 * @var Boolean
	 */
	private static $show_in_cart_table = true;

	/**
	 * message explaining how GST is based on a sale
	 * to a particular country ...
	 * @var string
	 */
	private static $based_on_country_note = "";
// ######################################## *** model defining static variables (e.g. $db, $has_one)

	/**
	 * standard SS variable
	 *
	 * @var Array
	 */
	private static $db = array(
		'DefaultCountry' => 'Varchar(3)',
		'Country' => 'Varchar(3)',
		'DefaultRate' => 'Double',
		'CurrentRate' => 'Double',
		'TaxType' => "Enum('Exclusive, Inclusive','Inclusive')",
		'DebugString' => 'HTMLText',
		'RawTableValue' => 'Currency'
	);

	private static $many_many = array(
		"GSTTaxModifierOptions" => "GSTTaxModifierOptions"
	);

	/**
	 * standard SS variable
	 * @var String
	 */
	private static $singular_name = "Tax Charge";
		function i18n_singular_name() { return _t("GSTTaxModifier.TAXCHARGE", "Tax Charge");}


	/**
	 * standard SS variable
	 * @var String
	 */
	private static $plural_name = "Tax Charges";
		function i18n_plural_name() { return _t("GSTTaxModifier.TAXCHARGES", "Tax Charges");}


// ######################################## *** cms variables + functions (e.g. getCMSFields, $searchableFields)



	/**
	 * standard SS method
	 * @return FieldList for CMS
	 */
	function getCMSFields(){
		$fields = parent::getCMSFields();
		$fields->replaceField("Country", new DropDownField("Country", "based on a sale to ", EcommerceCountry::get_country_dropdown()));
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

// ######################################## *** other (non) static variables (e.g. private static $special_name_for_something, protected $order)
	/**
	 * default country for tax calculations
	 * IMPORTANT: we need this variable - because in case of INCLUSIVE prices,
	 * we need to know on what country the prices are based as to be able
	 * to remove the tax for other countries.
	 * @var String
	 */
	private static $default_country_code = "";
		protected static function get_default_country_code_combined() {
			$country = Config::inst()->get("GSTTaxModifier", "default_country_code");
			if(!$country) {
				$country = EcommerceConfig::get('EcommerceCountry', 'default_country_code');
			}
			return $country;
		}

	/**
	 * wording in cart for prices that are tax exclusive (tax added on top of prices)
	 * @var String
	 */
	private static $exclusive_explanation = "";

	/**
	 * wording in cart for prices that are tax inclusive (tax is part of the prices)
	 * @var String
	 */
	private static $inclusive_explanation = "";

	/**
	 * wording in cart for prices that are include a tax refund.
	 * A refund situation applies when the prices are tax inclusive
	 * but NO tax applies to the country to which the goods are sold.
	 * E.g. for a UK shop no VAT is charged to buyers outside the EU.
	 * @var String
	 */
	private static $refund_title = "Tax Exemption";

	/**
	 * wording in cart for prices that are tax exempt (no tax applies)
	 * @var String
	 */
	private static $no_tax_description = "tax-exempt";

	/**
	 * name of the method in the buyable OrderItem that works out the
	 * portion without tax. You can use this method by creating your own
	 * OrderItem class and adding a method there.  This is by far the most
	 * flexible way to work out the tax on products with complex tax rules.
	 * @var String
	 */
	private static $order_item_function_for_tax_exclusive_portion = "portionWithoutTax";//PortionWithoutTax

	/**
	 * Use this variable IF:
	 *
	 * a. you have localised prices for countries
	 * other than the default country
	 *
	 * b. prices on the website are TAX INCLUSIVE
	 *
	 * If not, the tax for an international for a
	 * site with tax inclusive prices will firstly
	 * deduct the default tax and then add the tax
	 * of the country at hand.
	 *
	 * @var Boolean
	 */
	private static $alternative_country_prices_already_include_their_own_tax = false;//PortionWithoutTax

	/**
	 * contains all the applicable DEFAULT tax objects
	 * @var Object
	 */
	private static $default_tax_objects = null;


	/**
	 * tells us the default tax objects tax rate
	 * @var Float | Null
	 */
	private static $default_tax_objects_rate = null;


	/**
	 * contains all the applicable tax objects for the current order
	 * @var Object
	 */
	private static $current_tax_objects = null;

	/**
	 * tells us the current tax objects tax rate
	 * @var NULL | Float
	 */
	private static $current_tax_objects_rate = null;

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


// ######################################## *** form functions (e. g. Showform and getform)
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
		return $this->Config()->get("show_in_cart_table");
	}


// ######################################## ***  inner calculations.... USES CALCULATED VALUES

	/**
	 * works out what taxes apply in the default setup.
	 * we need this, because prices may include tax
	 * based on the default tax rate.
	 *
	 * @return ArrayList | FALSE of applicable taxes in the default country.
	 */
	protected function defaultTaxObjects() {
		if(self::$default_tax_objects === null) {
			$defaultCountryCode = self::get_default_country_code_combined();
			if($defaultCountryCode) {
				$this->debugMessage .= "<hr />There is a current live DEFAULT country code: ".$defaultCountryCode;
				self::$default_tax_objects = GSTTaxModifierOptions::get()
					->filter(
						array(
							"CountryCode" => $defaultCountryCode,
							"DoesNotApplyToAllProducts" => 0
						)
					);
				if(self::$default_tax_objects->count()) {
					$this->debugMessage .= "<hr />there are DEFAULT tax objects available for ".$defaultCountryCode;
				}
				else {
					self::$default_tax_objects = false;
					$this->debugMessage .= "<hr />there are no DEFAULT tax object available for ".$defaultCountryCode;
				}
			}
			else {
				$this->debugMessage .= "<hr />There is no current live DEFAULT country";
			}
		}
		if(self::$default_tax_objects_rate === null) {
			self::$default_tax_objects_rate = $this->workOutSumRate(self::$default_tax_objects);
		}
		return self::$default_tax_objects;
	}


	/**
	 * returns an ArrayList of all applicable tax options
	 * @return ArrayList | Null
	 */
	protected function currentTaxObjects() {
		if(self::$current_tax_objects === null) {
			$this->GSTTaxModifierOptions()->removeAll();
			if($countryCode = $this->LiveCountry()) {
				$this->debugMessage .= "<hr />There is a current live country: ".$countryCode;
				self::$current_tax_objects = GSTTaxModifierOptions::get()->where("(\"CountryCode\" = '".$countryCode."' OR \"AppliesToAllCountries\" = 1) AND \"DoesNotApplyToAllProducts\" = 0");
				GSTTaxModifierOptions::get()
					->where(
						"(\"CountryCode\" = '".$countryCode."' OR \"AppliesToAllCountries\" = 1) AND \"DoesNotApplyToAllProducts\" = 0"
					);
				if(self::$current_tax_objects->count()) {
					$this->GSTTaxModifierOptions()->addMany(self::$current_tax_objects->map("ID", "ID")->toArray());
					$this->debugMessage .= "<hr />There are tax objects available for ".$countryCode;
				}
				else {
					self::$current_tax_objects = null;
					$this->debugMessage .= "<hr />there are no tax objects available for ".$countryCode;
				}
			}
			else {
				$this->debugMessage .= "<hr />there is no current live country code";
			}

		}
		if(self::$current_tax_objects_rate === null) {
			self::$current_tax_objects_rate = $this->workOutSumRate(self::$current_tax_objects);
		}
		return self::$current_tax_objects;
	}

	/**
	 * returns the sum of rates for the given taxObjects
	 * @param Object - ArrayList of tax options
	 * @return Float
	 */
	protected function workOutSumRate($taxObjects) {
		$sumRate = 0;
		if($taxObjects && $taxObjects->count()) {
			foreach($taxObjects as $obj) {
				$this->debugMessage .= "<hr />found ".$obj->Title();
				$sumRate += floatval($obj->Rate);
			}
		}
		else {
			$this->debugMessage .= "<hr />could not find a rate";
		}
		$this->debugMessage .= "<hr />sum rate for tax objects: ".$sumRate;
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
		return $this->EcomConfig()->ShopPricesAreTaxExclusive ? false : true;
		//this code is here to support e-commerce versions that
		//do not have the DB field EcomConfig()->ShopPricesAreTaxExclusive
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
				foreach($items as $itemIndex => $item) {
					//resetting actual rate...
					$actualRate = $rate;
					$buyable = $item->Buyable();
					if($buyable) {
						$this->dealWithProductVariationException($buyable);
						if($buyable->hasExtension("GSTTaxDecorator")) {
							$excludedTaxes = $buyable->BuyableCalculatedExcludedFrom();
							$additionalTaxes = $buyable->BuyableCalculatedAdditionalTax();
							if($excludedTaxes) {
								foreach($excludedTaxes as $tax) {
									if(!$tax->DoesNotApplyToAllProducts) {
										$this->debugMessage .= "<hr />found tax to exclude for ".$buyable->Title.": ".$tax->Title();
										$actualRate -= $tax->Rate;
									}
								}
							}
							if($additionalTaxes) {
								foreach($additionalTaxes as $tax) {
									if($tax->DoesNotApplyToAllProducts) {
										if($tax->AppliesToAllCountries || $tax->CountryCode == $country) {
											$this->debugMessage .= "<hr />found tax to add for ".$buyable->Title.": ".$tax->Title();
											$actualRate += $tax->Rate;
										}
									}
								}
							}
						}
					}
					$totalForItem = $item->Total();
					$functionName = $this->config()->get("order_item_function_for_tax_exclusive_portion");
					if($functionName){
						if($item->hasMethod($functionName)) {
							$this->debugMessage .= "<hr />running $functionName on ".$item->ClassName.".".$item->ID;
							$totalForItem -= $item->$functionName();
						}
					}
					//turnRateIntoCalculationRate is really important -
					//a 10% rate is different for inclusive than for an exclusive tax
					$actualCalculationRate = $this->turnRateIntoCalculationRate($actualRate);
					$this->debugMessage .= "<hr /><b>$actualRate</b> turned into ".round($actualCalculationRate, 2)." for a total of <b>$totalForItem</b> on ".$item->ClassName.".".$item->ID;
					$itemsTotal += floatval($totalForItem) * $actualCalculationRate;
				}
			}
		}
		$this->debugMessage .= "<hr />Total order items tax: \$ ".round($itemsTotal, 4);
		return $itemsTotal;
	}

	/**
	 * this method is a bit of a hack.
	 * if a product variation does not have any specific tax rules
	 * but the product does, then it uses the rules from the product.
	 * @param DataObject $buyable
	 */
	function dealWithProductVariationException($buyable) {
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
	protected function workoutModifiersTax($rate, $country) {
		$modifiersTotal = 0;
		$order = $this->Order();
		if($order) {
			if($modifiers = $order->Modifiers()) {
				foreach($modifiers as $modifier) {
					if($modifier->IsRemoved()) {
						//do nothing
						//we just double-check this...
					}
					else {
						if($modifier instanceOf GSTTaxModifier) {
							//do nothing
						}
						else {
							$actualRate = $rate;
							$modifierDescriptor = OrderModifier_Descriptor::get()
								->filter(array("ModifierClassName" => $modifier->ClassName))
								->First();
							if($modifierDescriptor) {
								if($modifierDescriptor->hasExtension("GSTTaxDecorator")) {
									$excludedTaxes = $modifierDescriptor->ExcludedFrom();
									$additionalTaxes = $modifierDescriptor->AdditionalTax();
									if($excludedTaxes) {
										foreach($excludedTaxes as $tax) {
											if(!$tax->DoesNotApplyToAllProducts) {
												$this->debugMessage .= "<hr />found tax to exclude for ".$modifier->Title.": ".$tax->Title();
												$actualRate -= $tax->Rate;
											}
										}
									}
									if($additionalTaxes) {
										foreach($additionalTaxes as $tax) {
											if($tax->DoesNotApplyToAllProducts) {
												if($tax->AppliesToAllCountries || $tax->CountryCode == $country) {
													$this->debugMessage .= "<hr />found adtax to add for ".$modifier->Title.": ".$tax->Title();
													$actualRate += $tax->Rate;
												}
											}
										}
									}
								}
								else {
									$this->debugMessage .= "<hr />".$modifierDescriptor->ClassName." does not have the GSTTaxDecorator extension";
								}
							}
							$totalForModifier = $modifier->CalculationTotal();
							$functionName = $this->config()->get("order_item_function_for_tax_exclusive_portion");
							if($functionName){
								if($modifier->hasMethod($functionName)) {
									$totalForModifier -= $item->$functionName();
									$this->debugMessage .= "<hr />running $functionName on ".$modifier->ClassName.".".$modifier->ID;
								}
							}
							//turnRateIntoCalculationRate is really important -
							//a 10% rate is different for inclusive than for an exclusive tax
							$actualRateCalculationRate = $this->turnRateIntoCalculationRate($actualRate);
							$this->debugMessage .= "<hr />rate of $actualRate' turned into calculation rate of ".round($actualRateCalculationRate, 2)." for the total of $totalForModifier' on ".$modifier->ClassName.".".$modifier->ID;
							$modifiersTotal += floatval($totalForModifier) * $actualRateCalculationRate;
						}
					}
				}
			}
		}
		$this->debugMessage .= "<hr />Total order modifiers tax: \$ ".round($modifiersTotal, 4);
		return $modifiersTotal;
	}

	/**
	 * Are there Any taxes that do not apply to all products
	 * @return Boolean
	 */
	protected function hasExceptionTaxes(){
		return GSTTaxModifierOptions::get()->filter(array("DoesNotApplyToAllProducts" => 1))->First() ? false : true;
	}

// ######################################## *** calculate database fields: protected function Live[field name]  ... USES CALCULATED VALUES


	/**
	 * Used to save DefaultCountry to database
	 *
	 * determines value for DB field: Country
	 * @return String
	 */
	protected function LiveDefaultCountry() {
		return self::get_default_country_code_combined();
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
	 * determines value for the default rate
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
	 * temporary store of data for additional speed.
	 * @var Array
	 */

	private static $temp_raw_table_value = array();

	/**
	 * Used to save RawTableValue to database
	 *
	 * In case of a an exclusive rate, show what is actually added.
	 * In case of inclusive rate, show what is actually included.
	 * @return float
	 */
	protected function LiveRawTableValue() {
		if(!isset(self::$temp_raw_table_value[$this->OrderID])) {
			$currentRate = $this->LiveCurrentRate();
			$currentCountry = $this->LiveCountry();
			$itemsTax = $this->workoutOrderItemsTax($currentRate, $currentCountry);
			$modifiersTax = $this->workoutModifiersTax($currentRate, $currentCountry);
			self::$temp_raw_table_value[$this->OrderID] = $itemsTax + $modifiersTax;
		}
		return self::$temp_raw_table_value[$this->OrderID];
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
		$finalString = _t("OrderModifier.TAXCOULDNOTBEDETERMINED", "tax could not be determined");
		$countryCode = $this->LiveCountry();
		$startString = '';
		$name = '';
		$endString = '';
		$taxObjects = $this->currentTaxObjects();
		if($taxObjects) {
			$objectArray = array();
			foreach($taxObjects as $object) {
				$objectArray[] = $object->Name;
			}
			if(count($objectArray)) {
				$name = implode(", ", $objectArray);
			}
			if($this->config()->get("exclusive_explanation") && $this->isExclusive()) {
				$endString = $this->config()->get("exclusive_explanation");
			}
			elseif($this->Config()->get("inclusive_explanation") && $this->isInclusive()) {
				$endString = $this->Config()->get("inclusive_explanation");
			}
			if($name) {
				$finalString = $startString.$name.$endString;
			}
		}
		else {
			if($this->hasExceptionTaxes()) {
				$finalString = $this->Config()->get("no_tax_description");
			}
		}
		if($countryCode && $finalString) {
			$countryName = EcommerceCountry::find_title($countryCode);
			if($this->Config()->get("based_on_country_note") && $countryName  && $countryCode != self::get_default_country_code_combined()) {
				$finalString .= $this->Config()->get("based_on_country_note").' '.$countryName;
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
			if(Config::inst()->get('GSTTaxModifier', "alternative_country_prices_already_include_their_own_tax")) {
				return 0;
			}
			else {
				$currentCountry = $this->LiveCountry();
				$defaultCountry = $this->LiveDefaultCountry();
				if($currentCountry != $defaultCountry) {

					//already calculated into prices:
					$defaultRate = $this->LiveDefaultRate();
					$defaultItemsTax = $this->workoutOrderItemsTax($defaultRate, $defaultCountry);
					$defaultModifiersTax = $this->workoutModifiersTax($defaultRate, $defaultCountry);
					$shownToPay = $defaultItemsTax + $defaultModifiersTax;

					//what should have actually been show in prices:
					$actualNeedToPay = $this->LiveRawTableValue();

					//use what actually needs to be paid in tax minus what is already showing in prices
					//for example, if the shop is tax inclusive
					//and it is based in NZ (tax = 0.15) and a sale is made to AU (tax = 0.1)
					//and the shop also charges tax in AU then the Calculated TOTAL
					//is: AUTAX - NZTAX
					return $actualNeedToPay - $shownToPay;
				}
				else {
					return 0;
				}
			}
		}
	}

	private static $field_or_method_to_use_for_title = "Code";

	function getTableSubTitle() {
		$title = $this->stat('field_or_method_to_use_for_title');
		if($title) {
			$taxObjects = $this->currentTaxObjects();
			if($taxObjects) {
				$taxObject = $taxObjects->First();
				if($taxObject) {
					return $taxObject->hasMethod($title) ? $taxObject->$title() : $taxObject->$title;
				}
			}
		}
	}

// ######################################## *** Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)

// ######################################## *** standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)

// ######################################## *** AJAX related functions

// ######################################## *** debug functions



}













