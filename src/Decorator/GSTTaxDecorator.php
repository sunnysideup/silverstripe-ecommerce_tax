<?php

namespace Sunnysideup\EcommerceTax\Decorator;


use ProductVariation;





use Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;




/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_tax
 * @description: special tax rules for individual buyables
 *
 *
 */



/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD:  extends DataExtension (ignore case)
  * NEW:  extends DataExtension (COMPLEX)
  * EXP: Check for use of $this->anyVar and replace with $this->anyVar[$this->owner->ID] or consider turning the class into a trait
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class GSTTaxDecorator extends DataExtension
{

    /**
     * standard SS method
     * @return Array
     */

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * OLD: private static $many_many = (case sensitive)
  * NEW: 
    private static $table_name = '[SEARCH_REPLACE_CLASS_NAME_GOES_HERE]';

    private static $many_many = (COMPLEX)
  * EXP: Check that is class indeed extends DataObject and that it is not a data-extension!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    
    private static $table_name = 'GSTTaxDecorator';

    private static $many_many = array(
        "ExcludedFrom" => GSTTaxModifierOptions::class,
        "AdditionalTax" => GSTTaxModifierOptions::class
    );

    /**
     * for variations, use product for data
     * @return DataList
     */
    public function BuyableCalculatedExcludedFrom()
    {
        if ($this->owner instanceof ProductVariation) {
            if ($product = $this->owner->Product()) {
                return $product->ExcludedFrom();
            }
        }
        return $this->owner->ExcludedFrom();
    }

    /**
     * for variations, use product for data
     * @return DataList
     */
    public function BuyableCalculatedAdditionalTax()
    {
        if ($this->owner instanceof ProductVariation) {
            if ($product = $this->owner->Product()) {
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
    public function updateCMSFields(FieldList $fields)
    {
        $additionalWhereForDefault = "";
        $fields->removeByName("ExcludedFrom");
        $fields->removeByName("AdditionalTax");
        $tabName = "Root.Tax";
        if ($this->owner instanceof ProductVariation) {
            $fields->addFieldToTab(
                $tabName,
                new LiteralField(
                    "SeeProductForAdditionalTax",
                    _t("GSTTaxModifier.SEE_PARENT", "See parent Product for Additional Tax")
                )
            );
        } else {
            //additional taxes
            $additionalOptions = GSTTaxModifierOptions::get()->filter(array("DoesNotApplyToAllProducts" => 1));
            if ($additionalOptions->count()) {
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
        if ($this->owner instanceof ProductVariation) {
            $fields->addFieldToTab(
                $tabName,
                new LiteralField(
                    "SeeProductForExcludedFrom",
                    _t("GSTTaxModifier.SEE_PARRENT", "See parent product for excluded taxes")
                )
            );
        } else {
            //excluded options
            $excludedOptions = GSTTaxModifierOptions::get()->filter(array("DoesNotApplyToAllProducts" => 0));
            if ($excludedOptions->count()) {
                $excludedOptionsList = $excludedOptions->map()->toArray();
                $fields->addFieldToTab(
                    $tabName,
                    new CheckboxSetField(
                        "ExcludedFrom",
                        _t("GSTTaxMofidifier.EXCLUDE_TAXES", "Taxes that do not apply ..."),
                        $excludedOptionsList
                    )
                );
                $additionalWhereForDefault = " \"GSTTaxModifierOptions\".\"ID\" NOT IN (".implode(", ", $excludedOptions->map("ID", "ID")->toArray()).")";
            }
        }
        //default options
        $defaultOptions = GSTTaxModifierOptions::get()
            ->filter(array("DoesNotApplyToAllProducts" => 0))
            ->where($additionalWhereForDefault);
        if ($defaultOptions->count()) {
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
    public function TaxInclusivePrice()
    {
        user_error("to be completed");
    }

    /**
     * returns the calculated price for the buyable excluding tax
     * @return float
     *
     */
    public function TaxExclusivePrice()
    {
        user_error("to be completed");
    }
}

