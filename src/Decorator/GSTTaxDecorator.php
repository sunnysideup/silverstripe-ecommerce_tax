<?php

namespace Sunnysideup\EcommerceTax\Decorator;

use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions;

/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_tax
 * @description: special tax rules for individual buyables
 */
class GSTTaxDecorator extends DataExtension
{
    /**
     * standard SS method
     * @return array
     */
    private static $many_many = [
        'ExcludedFrom' => GSTTaxModifierOptions::class,
        'AdditionalTax' => GSTTaxModifierOptions::class,
    ];

    /**
     * for variations, use product for data
     * @return \SilverStripe\ORM\DataList
     */
    public function BuyableCalculatedExcludedFrom()
    {
        if (is_a($this->owner, 'ProductVariation')) {
            if ($product = $this->owner->Product()) {
                return $product->ExcludedFrom();
            }
        }
        return $this->owner->ExcludedFrom();
    }

    /**
     * for variations, use product for data
     * @return \SilverStripe\ORM\DataList
     */
    public function BuyableCalculatedAdditionalTax()
    {
        if (is_a($this->owner, 'ProductVariation')) {
            if ($product = $this->owner->Product()) {
                return $product->AdditionalTax();
            }
        }
        return $this->owner->AdditionalTax();
    }

    /**
     * standard SS method
     */
    public function updateCMSFields(FieldList $fields)
    {
        $additionalWhereForDefault = '';
        $fields->removeByName('ExcludedFrom');
        $fields->removeByName('AdditionalTax');
        $tabName = 'Root.Tax';
        if (is_a($this->owner, 'ProductVariation')) {
            $fields->addFieldToTab(
                $tabName,
                new LiteralField(
                    'SeeProductForAdditionalTax',
                    _t('GSTTaxModifier.SEE_PARENT', 'See parent Product for Additional Tax')
                )
            );
        } else {
            //additional taxes
            $additionalOptions = GSTTaxModifierOptions::get()->filter(['DoesNotApplyToAllProducts' => 1]);
            if ($additionalOptions->count()) {
                $additionalOptionsList = $additionalOptions->map()->toArray();
                $fields->addFieldToTab(
                    $tabName,
                    new CheckboxSetField(
                        'AdditionalTax',
                        _t('GSTTaxMofidifier.ADDITIONAL_TAXES', 'Additional taxes ...'),
                        $additionalOptionsList
                    )
                );
            }
        }
        if (is_a($this->owner, 'ProductVariation')) {
            $fields->addFieldToTab(
                $tabName,
                new LiteralField(
                    'SeeProductForExcludedFrom',
                    _t('GSTTaxModifier.SEE_PARRENT', 'See parent product for excluded taxes')
                )
            );
        } else {
            //excluded options
            $excludedOptions = GSTTaxModifierOptions::get()->filter(['DoesNotApplyToAllProducts' => 0]);
            if ($excludedOptions->count()) {
                $excludedOptionsList = $excludedOptions->map()->toArray();
                $fields->addFieldToTab(
                    $tabName,
                    new CheckboxSetField(
                        'ExcludedFrom',
                        _t('GSTTaxMofidifier.EXCLUDE_TAXES', 'Taxes that do not apply ...'),
                        $excludedOptionsList
                    )
                );
                $additionalWhereForDefault = ' "GSTTaxModifierOptions"."ID" NOT IN (' . implode(', ', $excludedOptions->columnUnique()) . ')';
            }
        }
        //default options
        $defaultOptions = GSTTaxModifierOptions::get()
            ->filter(['DoesNotApplyToAllProducts' => 0])
            ->where($additionalWhereForDefault);
        if ($defaultOptions->count()) {
            $fields->addFieldToTab(
                $tabName,
                new ReadonlyField('AlwaysApplies', '+ ' . implode(', ', $defaultOptions->map()->toArray()) . '.')
            );
        }
    }

    /**
     * returns the calculated price for the buyable including tax
     * @return float
     */
    public function TaxInclusivePrice()
    {
        user_error('to be completed');
        return 99999;
    }

    /**
     * returns the calculated price for the buyable excluding tax
     * @return float
     */
    public function TaxExclusivePrice()
    {
        user_error('to be completed');
        return 99999;
    }
}
