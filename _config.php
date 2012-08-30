<?php


/**
 * developed by www.sunnysideup.co.nz
 * author: Nicolaas - modules [at] sunnysideup.co.nz
**/


//copy the lines between the START AND END line to your /mysite/_config.php file and choose the right settings

//===================---------------- START ecommerce_tax MODULE ----------------===================
//MUST SET
/**
 * ADD TO ECOMMERCE.YAML:
Order:
	modifiers: [
		...
		GSTTaxModifier
	]
StoreAdmin:
	managed_models: [
		...
		GSTTaxModifierOptions
	]

*/

//MAY SET TO CREATE EXCEPTIONS (E.G. NO TAX ON DONATIONS)
//Object::add_extension('Product', 'GSTTaxDecorator');
//Object::add_extension('ProductVariation', 'GSTTaxDecorator');
//Object::add_extension('OrderModifier_Descriptor', 'GSTTaxDecorator');

//MAY SET
//GSTTaxModifier::set_exclusive_explanation(" (to be added to prices above)");
//GSTTaxModifier::set_inclusive_explanation(" (included in prices above)");
//GSTTaxModifier::set_based_on_country_note(" - based on a sale to: ");
//GSTTaxModifier::set_no_tax_description("tax-exempt");
//GSTTaxModifier::set_refund_title("Tax Exemption");
//GSTTaxModifier::set_order_item_function_for_tax_exclusive_portion("PortionWithoutTax");
//GSTTaxModifier::set_default_country_code("NZ");

//ALSO CONSIDER SETTING ... (as these will influence this module)
//Geoip::$default_country_code = "NZ";
//===================---------------- END ecommerce_tax MODULE ----------------===================
