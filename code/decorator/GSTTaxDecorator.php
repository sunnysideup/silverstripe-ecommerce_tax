<?php


/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_tax
 * @description: special tax rules for individual buyables
 *
 *
 */


class GSTTaxDecorator extends DataExtension {

	/**
	 * standard SS method
	 * @return Array
	 */
	private static $many_many = array(
		"ExcludedFrom" => "GSTTaxModifierOptions",
		"AdditionalTax" => "GSTTaxModifierOptions"
	);

	/**
	 * for variations, use product for data
	 * @return DataList
	 */
	function BuyableCalculatedExcludedFrom(){
		if($this->owner InstanceOf ProductVariation) {
			if($product = $this->owner->Product()) {
				return $product->ExcludedFrom();
			}
		}
		return $this->owner->ExcludedFrom();
	}

	/**
	 * for variations, use product for data
	 * @return DataList
	 */
	function BuyableCalculatedAdditionalTax(){
		if($this->owner InstanceOf ProductVariation) {
			if($product = $this->owner->Product()) {
				return $product->AdditionalTax();
			}
		}
		return $this->owner->AdditionalTax();
	}


	/**
	 * standard SS method
	 * @param Object - $fields (FieldList)
	 * @return Object - FieldList
	 */
	function updateCMSFields(FieldList $fields) {
		$additionalWhereForDefault = "";
		$fields->removeByName("ExcludedFrom");
		$fields->removeByName("AdditionalTax");
		$tabName = "Root.Tax";
		if($this->owner instanceOf ProductVariation) {
			$fields->addFieldToTab(
				$tabName,
				new LiteralField(
					"SeeProductForAdditionalTax",
					_t("GSTTaxModifier.SEE_PARENT", "See parent Product for Additional Tax")
				)
			);
		}
		else {
			//additional taxes
			$additionalOptions = GSTTaxModifierOptions::get()->filter(array("DoesNotApplyToAllProducts" => 1));
			if($additionalOptions->count()) {
				$additionalOptionsList = $additionalOptions->map()->toArray();
				$fields->addFieldToTab(
					$tabName,
					new CheckboxSetField(
						"AdditionalTax",
						_t("GSTTaxMofidifier.ADDITIONAL_TAXES", "Additional taxes ..."),
						$additionalOptionsList
					)
				);
			}
		}
		if($this->owner instanceOf ProductVariation) {
			$fields->addFieldToTab(
				$tabName,
				new LiteralField(
					"SeeProductForExcludedFrom",
					_t("GSTTaxModifier.SEE_PARRENT", "See parent product for excluded taxes")
				)
			);
		}
		else {
			//excluded options
			$excludedOptions = GSTTaxModifierOptions::get()->filter(array("DoesNotApplyToAllProducts" => 0));
			if($excludedOptions->count()) {
				$excludedOptionsList = $excludedOptions->map()->toArray();
				$fields->addFieldToTab(
					$tabName,
					new CheckboxSetField(
						"ExcludedFrom",
						_t("GSTTaxMofidifier.EXCLUDE_TAXES", "Taxes that do not apply ..."),
						$excludedOptionsList
					)
				);
				$additionalWhereForDefault = "  AND \"GSTTaxModifierOptions\".\"ID\" NOT IN (".implode(", ", $excludedOptions->map("ID", "ID")->toArray()).")";
			}
		}
		//default options
		$defaultOptions = GSTTaxModifierOptions::get()
			->filter(array("DoesNotApplyToAllProducts" => 0))
			->where($additionalWhereForDefault);
		if($defaultOptions->count()) {
			$fields->addFieldToTab(
				$tabName,
				new ReadonlyField("AlwaysApplies", "+ ".implode(", ", $defaultOptions->map()->toArray()).".")
			);
		}
	}

	/**
	 * returns the calculated price for the buyable including tax
	 * @return float
	 *
	 */
	function TaxInclusivePrice(){
		user_error("to be completed");
	}

	/**
	 * returns the calculated price for the buyable excluding tax
	 * @return float
	 *
	 */
	function TaxExclusivePrice(){
		user_error("to be completed");
	}


}
