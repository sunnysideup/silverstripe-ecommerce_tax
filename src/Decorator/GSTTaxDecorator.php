<?php

namespace Sunnysideup\EcommerceTax\Decorator;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBMoney;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Model\Config\EcommerceDBConfig;
use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;
use Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions;

/**
 * Class \Sunnysideup\EcommerceTax\Decorator\GSTTaxDecorator
 *
 * @property \Sunnysideup\Ecommerce\Model\OrderModifierDescriptor|\Sunnysideup\Ecommerce\Pages\Product|\Sunnysideup\EcommerceTax\Decorator\GSTTaxDecorator $owner
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions[] ExcludedFrom()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions[] AdditionalTax()
 */
class GSTTaxDecorator extends DataExtension
{
    /**
     * standard SS method.
     *
     * @return array
     */
    private static $many_many = [
        'ExcludedFrom' => GSTTaxModifierOptions::class,
        'AdditionalTax' => GSTTaxModifierOptions::class,
    ];

    /**
     * for variations, use product for data.
     *
     * @return \SilverStripe\ORM\DataList
     */
    public function BuyableCalculatedExcludedFrom()
    {
        if (is_a($this->owner, 'ProductVariation')) {
            $product = $this->getOwner()->Product();
            if ($product) {
                return $product->ExcludedFrom();
            }
        }

        return $this->getOwner()->ExcludedFrom();
    }

    /**
     * for variations, use product for data.
     *
     * @return \SilverStripe\ORM\DataList
     */
    public function BuyableCalculatedAdditionalTax()
    {
        if (is_a($this->owner, 'ProductVariation')) {
            $product = $this->getOwner()->Product();
            if ($product) {
                return $product->AdditionalTax();
            }
        }

        return $this->getOwner()->AdditionalTax();
    }

    /**
     * standard SS method.
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
            if ($additionalOptions->exists()) {
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
            if ($excludedOptions->exists()) {
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
        if ($defaultOptions->exists()) {
            $fields->addFieldToTab(
                $tabName,
                new ReadonlyField('AlwaysApplies', '+ ' . implode(', ', $defaultOptions->map()->toArray()) . '.')
            );
        }
    }

    /**
     * returns the calculated price for the buyable including tax.
     *
     * @return float
     */
    public function TaxInclusivePrice()
    {
        $owner = $this->getOwner();
        $price = $owner->getCalculatedPrice();
        if (! is_numeric($price)) {
            $price = 0;
        }
        if (EcommerceConfig::inst()->ShopPricesAreTaxExclusive) {
            return $price * $this->getDefaultTaxMultiplier();
        }
        return $price;
    }

    /**
     * returns the calculated price for the buyable excluding tax.
     *
     * @return float
     */
    public function TaxExclusivePrice()
    {
        $owner = $this->getOwner();
        $price = $owner->getCalculatedPrice();
        if (! is_numeric($price)) {
            $price = 0;
        }
        if (EcommerceConfig::inst()->ShopPricesAreTaxExclusive) {
            return $price;
        }
        return $price - ($price * (1 - (1 / $this->getDefaultTaxMultiplier())));
    }

    /**
     * how do we get from the price excluding GST to the price including GST?
     *
     * returns something like 1.15 for 15% GST.
     */
    public function getDefaultTaxMultiplier(): float
    {
        $taxRate = EcommerceConfig::inst()->DefaultTaxRate;
        if (! is_numeric($taxRate)) {
            $taxRate = 1;
        }
        $taxRate = (float) $taxRate;
        if ($taxRate === 0.0) {
            $taxRate = Config::inst()->get(EcommerceDBConfig::class, 'Defaults')['DefaultTaxRate'] = 0.15; // 15% GST
        }
        if ($taxRate === -1) {
            $taxRate = 0;
        }
        return $taxRate + 1; // 1.15 for 15% GST
    }

    public function CalculatedPriceAsMoneyExclTax(): DBMoney
    {
        return EcommerceCurrency::get_money_object_from_order_currency($this->getOwner()->TaxExclusivePrice());
    }

    public function CalculatedDifferenceAsMoney(): DBMoney
    {
        $owner = $this->getOwner();
        return EcommerceCurrency::get_money_object_from_order_currency($owner->TaxInclusivePrice() - $owner->TaxExclusivePrice());
    }

    public function CalculatedPriceAsMoneyInclTax(): DBMoney
    {
        return EcommerceCurrency::get_money_object_from_order_currency($this->getOwner()->TaxInclusivePrice());
    }
}
