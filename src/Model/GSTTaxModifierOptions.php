<?php

namespace Sunnysideup\EcommerceTax\Model;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DatabaseAdmin;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Model\Address\EcommerceCountry;
use Sunnysideup\Ecommerce\Model\Extensions\EcommerceRole;

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 **/

class GSTTaxModifierOptions extends DataObject
{
    /**
     * standard SS variable
     * @var string
     */
    private static $table_name = 'GSTTaxModifierOptions';

    private static $db = [
        'CountryCode' => 'Varchar(3)',
        'Code' => 'Varchar(12)',
        'Name' => 'Varchar(175)',
        'LegalNotice' => 'Varchar(255)',
        'InclusiveOrExclusive' => "Enum('Inclusive,Exclusive', 'Inclusive')",
        'Rate' => 'Double',
        'DoesNotApplyToAllProducts' => 'Boolean',
        'AppliesToAllCountries' => 'Boolean',
    ];

    /**
     * standard SS variable
     * @var array
     */
    private static $defaults = [
        'InclusiveOrExclusive' => 'Inclusive',
    ];

    /**
     * standard SS variable
     * @var array
     */
    private static $casting = [
        'CountryName' => 'Varchar',
        'PercentageNice' => 'Varchar',
    ];

    /**
     * standard SS variable
     * @var array
     */
    private static $indexes = [
        'Code' => true,
    ];

    /**
     * standard SS variable
     * @var array
     */
    private static $searchable_fields = [
        'CountryCode' => 'PartialMatchFilter',
        'Code' => 'PartialMatchFilter',
        'Name' => 'PartialMatchFilter',
    ];

    /**
     * standard SS variable
     * @var array
     */
    private static $field_labels = [
        'CountryName' => 'Country Name',
        'CountryCode' => 'Country Code',
        'Code' => 'Code for tax',
        'Name' => 'Name for tax',
        'InclusiveOrExclusive' => 'Inclusive/Exclusive',
        'LegalNotice' => 'Here you can put your GST number or VAT registration number',
        'Rate' => 'Rate (e.g. 0.125 = 12.5%)',
        'PercentageNice' => 'Percentage',
        'DoesNotApplyToAllProducts' => 'Added to individual products only',
    ];

    /**
     * standard SS variable
     * @var array
     */
    private static $summary_fields = [
        'CountryName' => 'Country',
        'Code' => 'Code',
        'Name' => 'Title',
        'InclusiveOrExclusive' => 'Type',
        'PercentageNice' => 'Percentage',
    ];

    /**
     * standard SS variable
     * @var string
     */
    private static $singular_name = 'Tax Option';

    /**
     * standard SS variable
     * @var string
     */
    private static $plural_name = 'Tax Options';

    public function i18n_singular_name()
    {
        return _t('GSTTaxModifierOptions.TAXOPTION', 'Tax Option');
    }

    public function i18n_plural_name()
    {
        return _t('GSTTaxModifierOptions.TAXOPTIONS', 'Tax Options');
    }

    /**
     * standard SS method
     * @param \SilverStripe\Security\Member $member|null
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, 'admin_permission_code'))) {
            return true;
        }
        return parent::canCreate($member);
    }

    /**
     * standard SS method
     * @param \SilverStripe\Security\Member $member|null
     * @return bool
     */
    public function canView($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, 'admin_permission_code'))) {
            return true;
        }
        return parent::canCreate($member);
    }

    /**
     * standard SS method
     * @param \SilverStripe\Security\Member $member|null
     * @return bool
     */
    public function canEdit($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, 'admin_permission_code'))) {
            return true;
        }
        return parent::canEdit($member);
    }

    /**
     * standard SS method
     * @param \SilverStripe\Security\Member $member|null
     * @return bool
     */
    public function canDelete($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, 'admin_permission_code'))) {
            return true;
        }
        return parent::canDelete($member);
    }

    /**
     * standard SS method
     * @return \SilverStripe\Forms\FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fieldLabels = $this->Config()->get('field_labels');
        $fields->replaceField('CountryCode', new DropdownField('CountryCode', $fieldLabels['CountryCode'], EcommerceCountry::get_country_dropdown()));
        $InclusiveOrExclusive = 'Inclusive';
        if (EcommerceConfig::inst()->ShopPricesAreTaxExclusive) {
            $InclusiveOrExclusive = 'Exclusive';
        }
        $fields->replaceField(
            'InclusiveOrExclusive',
            ReadonlyField::create(
                'InclusiveOrExclusive',
                'This tax is: ' . $InclusiveOrExclusive . ', you can change this setting in the e-commerce configuration.'
            )
        );
        return $fields;
    }

    public function Title()
    {
        return $this->getTitle();
    }

    public function getTitle()
    {
        if ($this->AppliesToAllCountries) {
            $country = _t('GSTTExModifierOption.WORLDWIDE', 'world-wide');
        } else {
            $country = $this->CountryCode;
        }
        return $this->Name . " (${country}, " . number_format($this->Rate * 100, 2) . '%)';
    }

    /**
     * standard SS method
     */
    public function populateDefaults()
    {
        //can only run after first dev/build
        if (Security::database_is_ready()) {
            $controller = Controller::curr();
            if ($controller instanceof DatabaseAdmin) {
                //cant do this now.
            } else {
                if (EcommerceConfig::inst()->ShopPricesAreTaxExclusive) {
                    $this->InclusiveOrExclusive = 'Exclusive';
                } else {
                    $this->InclusiveOrExclusive = 'Inclusive';
                }
            }
        }
        return parent::populateDefaults();
    }

    /**
     * standard SS method
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (EcommerceConfig::inst()->ShopPricesAreTaxExclusive) {
            $this->InclusiveOrExclusive = 'Exclusive';
        } else {
            $this->InclusiveOrExclusive = 'Inclusive';
        }
    }

    // /**
    //  * standard SS method
    //  */
    // public function requireDefaultRecords()
    // {
    //     parent::requireDefaultRecords();
    //     DB::query("
    //         UPDATE \"GSTTaxModifierOptions\"
    //         SET \"InclusiveOrExclusive\" = 'Inclusive'
    //         WHERE \"InclusiveOrExclusive\" <> 'Exclusive'"
    //     );
    // }

    public function CountryName()
    {
        return $this->getCountryName();
    }

    public function getCountryName()
    {
        return EcommerceCountry::find_title($this->CountryCode);
    }

    public function PercentageNice()
    {
        return $this->getPercentageNice();
    }

    public function getPercentageNice()
    {
        return DBField::create_field('Text', ($this->Rate * 100) . '%');
    }
}
