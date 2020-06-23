<?php

namespace Sunnysideup\EcommerceTax\Model;

use DataObject;
use Permission;
use Config;
use DropdownField;
use EcommerceCountry;
use ReadonlyField;
use Security;
use Controller;
use DatabaseAdmin;
use EcommerceDBConfig;
use DBField;


/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 **/

class GSTTaxModifierOptions extends DataObject
{

    /**
     * standard SS variable
     * @var Array
     */

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * OLD: private static $db (case sensitive)
  * NEW: 
    private static $table_name = '[SEARCH_REPLACE_CLASS_NAME_GOES_HERE]';

    private static $db (COMPLEX)
  * EXP: Check that is class indeed extends DataObject and that it is not a data-extension!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    
    private static $table_name = 'GSTTaxModifierOptions';


/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: private static $db = (case sensitive)
  * NEW: private static $db = (COMPLEX)
  * EXP: Make sure to add a private static $table_name!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    private static $db = array(
        "CountryCode" => "Varchar(3)",
        "Code" => "Varchar(12)",
        "Name" => "Varchar(175)",
        "LegalNotice" => "Varchar(255)",
        "InclusiveOrExclusive" => "Enum('Inclusive,Exclusive', 'Inclusive')",
        "Rate" => "Double",
        "DoesNotApplyToAllProducts" => "Boolean",
        "AppliesToAllCountries" => "Boolean"
    );

    /**
     * standard SS variable
     * @var Array
     */
    private static $defaults = array(
        "InclusiveOrExclusive" => "Inclusive"
    );

    /**
     * standard SS variable
     * @var Array
     */
    private static $casting = array(
        "CountryName" => "Varchar",
        "PercentageNice" => "Varchar"
    );

    /**
     * standard SS variable
     * @var Array
     */
    private static $indexes = array(
        "Code" => true
    );

    /**
     * standard SS variable
     * @var Array
     */
    private static $searchable_fields = array(
        "CountryCode" => "PartialMatchFilter",
        "Code" => "PartialMatchFilter",
        "Name" => "PartialMatchFilter"
    );

    /**
     * standard SS variable
     * @var Array
     */
    private static $field_labels = array(
        "CountryName" => "Country Name",
        "CountryCode" => "Country Code",
        "Code" => "Code for tax",
        "Name" => "Name for tax",
        "InclusiveOrExclusive" => "Inclusive/Exclusive",
        "LegalNotice" => "Here you can put your GST number or VAT registration number",
        "Rate" => "Rate (e.g. 0.125 = 12.5%)",
        "PercentageNice" => "Percentage",
        "DoesNotApplyToAllProducts" => "Added to individual products only"
    );


    /**
     * standard SS variable
     * @var Array
     */
    private static $summary_fields = array(
        "CountryName" => 'Country',
        "Code" => 'Code',
        "Name" => 'Title',
        "InclusiveOrExclusive"  => 'Type',
        "PercentageNice" => 'Percentage'
    );

    /**
     * standard SS variable
     * @var String
     */
    private static $singular_name = "Tax Option";
    public function i18n_singular_name()
    {
        return _t("GSTTaxModifierOptions.TAXOPTION", "Tax Option");
    }


    /**
     * standard SS variable
     * @var String
     */
    private static $plural_name = "Tax Options";
    public function i18n_plural_name()
    {
        return _t("GSTTaxModifierOptions.TAXOPTIONS", "Tax Options");
    }

    /**
     * standard SS method
     * @param Member | NULL
     * @return Boolean
     */
    public function canCreate($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
            return true;
        }
        return parent::canCreate($member);
    }

    /**
     * standard SS method
     * @param Member | NULL
     * @return Boolean
     */
    public function canView($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
            return true;
        }
        return parent::canCreate($member);
    }

    /**
     * standard SS method
     * @param Member | NULL
     * @return Boolean
     */
    public function canEdit($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
            return true;
        }
        return parent::canEdit($member);
    }

    /**
     * standard SS method
     * @param Member | NULL
     * @return Boolean
     */
    public function canDelete($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
            return true;
        }
        return parent::canDelete($member);
    }

    /**
     * standard SS method
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fieldLabels = $this->Config()->get("field_labels");
        $fields->replaceField("CountryCode", new DropdownField("CountryCode", $fieldLabels["CountryCode"], EcommerceCountry::get_country_dropdown()));
        $InclusiveOrExclusive = "Inclusive";
        if ($this->EcomConfig()->ShopPricesAreTaxExclusive) {
            $InclusiveOrExclusive = "Exclusive";
        }
        $fields->replaceField("InclusiveOrExclusive", new ReadonlyField("InclusiveOrExclusive", "This tax is: ..., you can change this setting in the e-commerce configuration."));
        return $fields;
    }

    public function Title()
    {
        return $this->getTitle();
    }
    public function getTitle()
    {
        if ($this->AppliesToAllCountries) {
            $country = _t("GSTTExModifierOption.WORLDWIDE", "world-wide");
        } else {
            $country = $this->CountryCode;
        }
        return $this->Name." ($country, ".number_format($this->Rate * 100, 2) . '%)';
    }

    /**
     * standard SS method
     */
    public function populateDefaults()
    {
        parent::populateDefaults();
        //can only run after first dev/build
        if (Security::database_is_ready()) {
            $controller = Controller::curr();
            if ($controller instanceof DatabaseAdmin) {
                //cant do this now.
            } else {
                if ($this->EcomConfig()->ShopPricesAreTaxExclusive) {
                    $this->InclusiveOrExclusive = "Exclusive";
                } else {
                    $this->InclusiveOrExclusive = "Inclusive";
                }
            }
        }
    }

    /**
     * standard SS method
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->EcomConfig()->ShopPricesAreTaxExclusive) {
            $this->InclusiveOrExclusive = "Exclusive";
        } else {
            $this->InclusiveOrExclusive = "Inclusive";
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

    /**
     * returns the instance of EcommerceDBConfig
     *
     * @return EcommerceDBConfig
     **/
    public function EcomConfig()
    {
        return EcommerceDBConfig::current_ecommerce_db_config();
    }

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
        return DBField::create_field("Text", ($this->Rate * 100)."%");
    }
}

