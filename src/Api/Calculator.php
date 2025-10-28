<?php

namespace Sunnysideup\EcommerceTax\Api;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Model\Address\EcommerceCountry;
use Sunnysideup\Ecommerce\Model\OrderModifier;
use Sunnysideup\Ecommerce\Model\OrderModifierDescriptor;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\EcommerceTax\Decorator\GSTTaxDecorator;
use Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions;

/**
 *
 * @property string $DefaultCountry
 * @property string $Country
 * @property float $DefaultRate
 * @property float $CurrentRate
 * @property string $TaxType
 * @property string $DebugString
 * @property float $RawTableValue
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions[] GSTTaxModifierOptions()
 */
class Calculator
{

    use Configurable;
    use Injectable;
    use Extensible;

    /**
     * any calculation messages are added to the Debug Message.
     *
     * @var string
     */
    protected $debugMessage = '';


    /**
     * message explaining how GST is based on a sale
     * to a particular country ...
     *
     * @var string
     */
    private static $based_on_country_note = '';


    // ######################################## *** other (non) static variables (e.g. private static $special_name_for_something, protected $order)

    /**
     * default country for tax calculations
     * IMPORTANT: we need this variable - because in case of INCLUSIVE prices,
     * we need to know on what country the prices are based as to be able
     * to remove the tax for other countries.
     *
     * @var string
     */
    private static $default_country_code = '';

    /**
     * wording in cart for prices that are tax exclusive (tax added on top of prices).
     *
     * @var string
     */
    private static $exclusive_explanation = '';

    /**
     * wording in cart for prices that are tax inclusive (tax is part of the prices).
     *
     * @var string
     */
    private static $inclusive_explanation = '';

    /**
     * wording in cart for prices that are include a tax refund.
     * A refund situation applies when the prices are tax inclusive
     * but NO tax applies to the country to which the goods are sold.
     * E.g. for a UK shop no VAT is charged to buyers outside the EU.
     *
     * @var string
     */
    private static $refund_title = 'Tax Exemption';

    /**
     * wording in cart for prices that are tax exempt (no tax applies).
     *
     * @var string
     */
    private static $no_tax_description = 'Tax-exempt';

    /**
     * name of the method in the buyable OrderItem that works out the
     * portion without tax. You can use this method by creating your own
     * OrderItem class and adding a method there.  This is by far the most
     * flexible way to work out the tax on products with complex tax rules.
     *
     * @var string
     */
    private static $order_item_function_for_tax_exclusive_portion = 'portionWithoutTax'; //PortionWithoutTax

    /**
     * Use this variable IF:.
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
     * @var bool
     */
    private static $alternative_country_prices_already_include_their_own_tax = false; //PortionWithoutTax

    /**
     * contains all the applicable DEFAULT tax objects.
     *
     * @var \SilverStripe\ORM\DataList
     */
    protected static $default_tax_objects;

    /**
     * tells us the default tax objects tax rate.
     *
     * @var float
     */
    protected static $default_tax_objects_rate;

    /**
     * contains all the applicable tax objects for the current order.
     *
     * @var \SilverStripe\ORM\DataList
     */
    protected static $current_tax_objects;

    /**
     * tells us the current tax objects tax rate.
     *
     * @var float
     */
    protected static $current_tax_objects_rate;

    private static $field_or_method_to_use_for_sub_title = '';

    private static $debug = false;




    /**
     * this method is a bit of a hack.
     * if a product variation does not have any specific tax rules
     * but the product does, then it uses the rules from the product.
     *
     * @param DataObject $buyable
     */
    public function findDefiningProduct($buyable)
    {
        if (Product::is_product_variation($buyable)) {
            if (! $buyable->hasExtension(GSTTaxDecorator::class)) {
                $parent = $buyable->ParentGroup();
                if ($parent) {
                    if ($parent->hasExtension(GSTTaxDecorator::class)) {
                        $buyable = $parent;
                    }
                }
            }
        }

        return $buyable;
    }


    // takes and amount inclusive of tax and returns the tax amount
    public function simpleTaxCalculation($buyable, $amountInclTax, $rate = 0, $country = ''): float
    {
        // @todo  figure if if prices include or exclude tax
        $actualCalculationRate = $this->turnRateIntoCalculationRate($rate);
        return $buyable->getCalculatedPrice() * $actualCalculationRate;
    }

    public function getTotalTax($buyable, $rate = 0, $country = ''): float
    {
        if (! $country) {
            $country = $this->Country;
        }
        $price = $buyable->getCalculatedPrice();
        $actualCalculationRate = $this->getProductSpecificRate($buyable, $rate, $country);
        if ($this->IsDebug()) {
            $this->debugMessage .= "<hr /><b>{$rate}</b> turned into " . round($actualCalculationRate, 2) . " for a total of <b>{$price}</b> on " . $buyable->ClassName . '.' . $buyable->ID;
        }

        return floatval($price) * $actualCalculationRate;
    }


    protected static function get_default_country_code_combined()
    {
        $country = Config::inst()->get(Calculator::class, 'default_country_code');
        if (! $country) {
            $country = EcommerceConfig::get(EcommerceCountry::class, 'default_country_code');
        }

        return $country;
    }

    // ######################################## ***  inner calculations.... USES CALCULATED VALUES

    /**
     * works out what taxes apply in the default setup.
     * we need this, because prices may include tax
     * based on the default tax rate.
     *
     * @return null|\SilverStripe\ORM\DataList of applicable taxes in the default country
     */
    protected function defaultTaxObjects()
    {
        if (null === self::$default_tax_objects) {
            $defaultCountryCode = self::get_default_country_code_combined();
            if ($defaultCountryCode) {
                if ($this->IsDebug()) {
                    $this->debugMessage .= '<hr />There is a current live DEFAULT country code: ' . $defaultCountryCode;
                }
                self::$default_tax_objects = GSTTaxModifierOptions::get()
                    ->filter(
                        [
                            'CountryCode' => $defaultCountryCode,
                            'DoesNotApplyToAllProducts' => 0,
                        ]
                    );
                if (self::$default_tax_objects->exists()) {
                    if ($this->IsDebug()) {
                        $this->debugMessage .= '<hr />there are DEFAULT tax objects available for ' . $defaultCountryCode;
                    }
                } else {
                    self::$default_tax_objects = null;
                    if ($this->IsDebug()) {
                        $this->debugMessage .= '<hr />there are no DEFAULT tax object available for ' . $defaultCountryCode;
                    }
                }
            } else {
                if ($this->IsDebug()) {
                    $this->debugMessage .= '<hr />There is no current live DEFAULT country';
                }
            }
        }
        if (null === self::$default_tax_objects_rate) {
            self::$default_tax_objects_rate = $this->workOutSumRate(self::$default_tax_objects);
        }

        return self::$default_tax_objects;
    }

    /**
     * returns an ArrayList of all applicable tax options.
     *
     * @return null|\SilverStripe\ORM\DataList
     */
    protected function currentTaxObjects()
    {
        if (null === self::$current_tax_objects) {
            $this->GSTTaxModifierOptions()->removeAll();
            $countryCode = $this->Country();
            if ($countryCode) {
                if ($this->IsDebug()) {
                    $this->debugMessage .= '<hr />There is a current live country: ' . $countryCode;
                }
                self::$current_tax_objects = GSTTaxModifierOptions::get()->where("(\"CountryCode\" = '" . $countryCode . "' OR \"AppliesToAllCountries\" = 1) AND \"DoesNotApplyToAllProducts\" = 0");
                GSTTaxModifierOptions::get()
                    ->where(
                        "(\"CountryCode\" = '" . $countryCode . "' OR \"AppliesToAllCountries\" = 1) AND \"DoesNotApplyToAllProducts\" = 0"
                    )
                ;
                if (self::$current_tax_objects->exists()) {
                    $this->GSTTaxModifierOptions()->addMany(self::$current_tax_objects->columnUnique());
                    if ($this->IsDebug()) {
                        $this->debugMessage .= '<hr />There are tax objects available for ' . $countryCode;
                    }
                } else {
                    self::$current_tax_objects = null;
                    if ($this->IsDebug()) {
                        $this->debugMessage .= '<hr />there are no tax objects available for ' . $countryCode;
                    }
                }
            } else {
                if ($this->IsDebug()) {
                    $this->debugMessage .= '<hr />there is no current live country code';
                }
            }
        }
        if (null === self::$current_tax_objects_rate) {
            self::$current_tax_objects_rate = $this->workOutSumRate(self::$current_tax_objects);
        }

        return self::$current_tax_objects;
    }

    /**
     * returns the sum of rates for the given taxObjects.
     *
     * @param object $taxObjects - ArrayList of tax options
     *
     * @return float
     */
    protected function workOutSumRate($taxObjects)
    {
        $sumRate = 0;
        if ($taxObjects && $taxObjects->exists()) {
            foreach ($taxObjects as $obj) {
                if ($this->IsDebug()) {
                    $this->debugMessage .= '<hr />found ' . $obj->Title();
                }
                $sumRate += floatval($obj->Rate);
            }
        } else {
            if ($this->IsDebug()) {
                $this->debugMessage .= '<hr />could not find a rate';
            }
        }
        if ($this->IsDebug()) {
            $this->debugMessage .= '<hr />sum rate for tax objects: ' . $sumRate;
        }

        return $sumRate;
    }

    /**
     * tells us if the tax for the current order is exclusive
     * default: false.
     *
     * @return bool
     */
    protected function isExclusive()
    {
        return $this->isInclusive() ? false : true;
    }

    /**
     * tells us if the tax for the current order is inclusive
     * default: true.
     *
     * @return bool
     */
    protected function isInclusive()
    {
        return EcommerceConfig::inst()->ShopPricesAreTaxExclusive ? false : true;
        //this code is here to support e-commerce versions that
        //do not have the DB field EcomConfig()->ShopPricesAreTaxExclusive
        // $array = [];
        // //here we have to take the default tax objects
        // //because we want to know for the default country
        // //that is the actual country may not have any prices
        // //associated with it!
        // $objects = $this->defaultTaxObjects();
        // if ($objects) {
        //     foreach ($objects as $obj) {
        //         $array[$obj->InclusiveOrExclusive] = $obj->InclusiveOrExclusive;
        //     }
        // }
        // if (count($array) < 1) {
        //     return true;
        // }
        // if (count($array) > 1) {
        //     user_error('you can not have a collection of tax objects that is both inclusive and exclusive', E_USER_WARNING);
        //
        //     return true;
        // }
        // foreach ($array as $item) {
        //     return 'Inclusive' === $item ? true : false;
        // }
    }

    /**
     * turns a standard rate into a calculation rate.
     * That is, 0.125 for exclusive is 1/9 for inclusive rates
     * default: true.
     *
     * @param float $rate - input rate (e.g. 0.125 equals a 12.5% tax rate)
     *
     * @return float
     */
    protected function turnRateIntoCalculationRate($rate)
    {
        return $this->isExclusive() ? $rate : 1 - (1 / (1 + $rate));
    }


    protected function getProductSpecificRate($buyable, $rate, $country): float
    {
        //resetting actual rate...
        $actualRate = $rate;
        if ($buyable) {
            $this->findDefiningProduct($buyable);
            if ($buyable->hasExtension(GSTTaxDecorator::class)) {
                $excludedTaxes = $buyable->BuyableCalculatedExcludedFrom();
                $additionalTaxes = $buyable->BuyableCalculatedAdditionalTax();
                if ($excludedTaxes) {
                    foreach ($excludedTaxes as $tax) {
                        if (! $tax->DoesNotApplyToAllProducts) {
                            if ($this->IsDebug()) {
                                $this->debugMessage .= '<hr />found tax to exclude for ' . $buyable->Title . ': ' . $tax->Title();
                            }
                            $actualRate -= $tax->Rate;
                        }
                    }
                }
                if ($additionalTaxes) {
                    foreach ($additionalTaxes as $tax) {
                        if ($tax->DoesNotApplyToAllProducts) {
                            if ($tax->AppliesToAllCountries || $tax->CountryCode === $country) {
                                if ($this->IsDebug()) {
                                    $this->debugMessage .= '<hr />found tax to add for ' . $buyable->Title . ': ' . $tax->Title();
                                }
                                $actualRate += $tax->Rate;
                            }
                        }
                    }
                }
            }
        }

        return $actualRate;
    }



    /**
     * Are there Any taxes that do not apply to all products.
     *
     * @return bool
     */
    protected function hasExceptionTaxes(): bool
    {
        return DataObject::get_one(
            GSTTaxModifierOptions::class,
            ['DoesNotApplyToAllProducts' => 1]
        ) ? false : true;
    }



    protected function IsDebug()
    {
        return $this->Config()->get('debug');
    }

    /**
     * Used to save Country to database.
     *
     * determines value for DB field: Country
     *
     * @return string
     */
    protected function Country()
    {
        return EcommerceCountry::get_country();
    }
}
