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


	/**
	 * standard SS method
	 * @param Object - $fields (FieldSet)
	 * @return Object - FieldSet
	 */
	function updateCMSFields(&$fields) {
		$fields->removeByName("ExcludedFrom");
		$fields->removeByName("AdditionalTax");
		if($this->owner instanceOf SiteTree) {
			$tabName = "Root.Content.Tax";
		}
		else {
			$tabName = "Root.Tax";
		}
		//additional taxes
		$additionalOptions = DataObject::get("GSTTaxModifierOptions", "\"DoesNotApplyToAllProducts\" = 1");
		if($additionalOptions) {
			$additionalOptionsList = $additionalOptions->toDropdownMap();
			$fields->addFieldToTab(
				$tabName,
				new CheckboxSetField(
					"AdditionalTax", "Additional taxes ...", $additionalOptionsList
				)
			);
		}
		//excluded options
		$excludedOptions = DataObject::get("GSTTaxModifierOptions", "\"DoesNotApplyToAllProducts\" = 0");
		$additionalWhereForDefault = "";
		if($excludedOptions) {
			$excludedOptionsList = $excludedOptions->toDropdownMap();
			$fields->addFieldToTab(
				$tabName,
				new CheckboxSetField(
					"ExcludedFrom", "Taxes that do not apply ...", $excludedOptionsList
				)
			);
			$additionalWhereForDefault = "  AND \"GSTTaxModifierOptions\".\"ID\" NOT IN (".implode(", ", $excludedOptions->map("ID", "ID")).")";
		}
		//default options
		$defaultOptions = DataObject::get("GSTTaxModifierOptions", "\"DoesNotApplyToAllProducts\" = 0 $additionalWhereForDefault");
		if($defaultOptions) {
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
