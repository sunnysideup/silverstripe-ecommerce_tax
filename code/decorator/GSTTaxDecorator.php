<?php


/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_tax
 * @description: special tax rules for individual buyables
 *
 *
 */


class GSTTaxDecorator extends DataObjectDecorator {

	/**
	 * standard SS method
	 * @return Array
	 */
	function extraStatics() {
		return array(
			"many_many" => array(
				"ExcludedFrom" => "GSTTaxModifierOptions",
				"AdditionalTax" => "GSTTaxModifierOptions"
			)
		);
	}

	function BuyableCalculatedExcludedFrom(){
		if($this->owner InstanceOf ProductVariation) {
			if($product = $this->owner->Product()) {
				return $product->ExcludedFrom();
			}
		}
		return $this->owner->ExcludedFrom();
	}

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
	 * @param Object - $fields (FieldSet)
	 * @return Object - FieldSet
	 */
	function updateCMSFields(&$fields) {
		$additionalWhereForDefault = "";
		$fields->removeByName("ExcludedFrom");
		$fields->removeByName("AdditionalTax");
		if($this->owner instanceOf SiteTree) {
			$tabName = "Root.Content.Tax";
		}
		else {
			$tabName = "Root.Tax";
		}
		if($this->owner instanceOf ProductVariation) {
			$fields->addFieldToTab(
				$tabName,
				new LiteralField("SeeProductForAdditionalTax", "See parent Product for Additional Tax")
			);
		}
		else {
			//additional taxes
			$additionalOptions = GSTTaxModifierOptions::get()->filter(array("DoesNotApplyToAllProducts" => 1));
			if($additionalOptions->count()) {
				$additionalOptionsList = $additionalOptions->toDropdownMap();
				$fields->addFieldToTab(
					$tabName,
					new CheckboxSetField(
						"AdditionalTax", "Additional taxes ...", $additionalOptionsList
					)
				);
			}
		}
		if($this->owner instanceOf ProductVariation) {
			$fields->addFieldToTab(
				$tabName,
				new LiteralField("SeeProductForExcludedFrom", "See parent Product for Excluded taxes")
			);
		}
		else {
			//excluded options
			$excludedOptions = GSTTaxModifierOptions::get()->filter(array("DoesNotApplyToAllProducts" => 0));
			if($excludedOptions->count()) {
				$excludedOptionsList = $excludedOptions->toDropdownMap();
				$fields->addFieldToTab(
					$tabName,
					new CheckboxSetField(
						"ExcludedFrom", "Taxes that do not apply ...", $excludedOptionsList
					)
				);
				$additionalWhereForDefault = "  AND \"GSTTaxModifierOptions\".\"ID\" NOT IN (".implode(", ", $excludedOptions->map("ID", "ID")).")";
			}
		}
		//default options
		$defaultOptions = GSTTaxModifierOptions::get()->filter(array("DoesNotApplyToAllProducts" => 0))->where($additionalWhereForDefault);
		if($defaultOptions->count()) {
			$fields->addFieldToTab(
				$tabName,
				new ReadonlyField("AlwaysApplies", "+ ".implode(", ", $defaultOptions->toDropdownMap()).".")
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
