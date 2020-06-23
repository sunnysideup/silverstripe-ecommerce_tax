2020-06-24 10:18

# running php upgrade upgrade see: https://github.com/silverstripe/silverstripe-upgrader
cd /var/www/upgrades/ecommerce_tax
php /var/www/upgrader/vendor/silverstripe/upgrader/bin/upgrade-code upgrade /var/www/upgrades/ecommerce_tax/ecommerce_tax  --root-dir=/var/www/upgrades/ecommerce_tax --write -vvv
Writing changes for 6 files
Running upgrades on "/var/www/upgrades/ecommerce_tax/ecommerce_tax"
[2020-06-24 10:18:16] Applying RenameClasses to EcommerceTaxTest.php...
[2020-06-24 10:18:16] Applying ClassToTraitRule to EcommerceTaxTest.php...
[2020-06-24 10:18:16] Applying RenameClasses to Zip2Tax.php...
[2020-06-24 10:18:16] Applying ClassToTraitRule to Zip2Tax.php...
[2020-06-24 10:18:16] Applying UpdateConfigClasses to config.yml...
[2020-06-24 10:18:16] Applying RenameClasses to GSTTaxModifier.php...
[2020-06-24 10:18:16] Applying ClassToTraitRule to GSTTaxModifier.php...
[2020-06-24 10:18:16] Applying RenameClasses to GSTTaxDecorator.php...
[2020-06-24 10:18:16] Applying ClassToTraitRule to GSTTaxDecorator.php...
[2020-06-24 10:18:16] Applying RenameClasses to GSTTaxModifierOptions.php...
[2020-06-24 10:18:16] Applying ClassToTraitRule to GSTTaxModifierOptions.php...
[2020-06-24 10:18:16] Applying RenameClasses to _config.php...
[2020-06-24 10:18:16] Applying ClassToTraitRule to _config.php...
modified:	tests/EcommerceTaxTest.php
@@ -1,4 +1,6 @@
 <?php
+
+use SilverStripe\Dev\SapphireTest;

 class EcommerceTaxTest extends SapphireTest
 {

modified:	_dev/Zip2Tax.php
@@ -3,33 +3,37 @@
 /**
  * @see http://www.zip2tax.com/developers/z2t_developers_example.asp?language=PHP&file=php&db=mysql
  */
-class Zip2Tax extends Object
+use SilverStripe\Core\Extensible;
+use SilverStripe\Core\Injector\Injectable;
+use SilverStripe\Core\Config\Configurable;
+/**
+ * @see http://www.zip2tax.com/developers/z2t_developers_example.asp?language=PHP&file=php&db=mysql
+ */
+class Zip2Tax
 {
+    use Extensible;
+    use Injectable;
+    use Configurable;
     public static function get_taxes($zip)
     {
         $server = 'db.Zip2Tax.com';
         $username = 'z2t_link';
         $password = 'H^2p6~r';
         $database = 'zip2tax';
-
         $connection = mysql_connect($server, $username, $password, 0, 65536);
-        if (! $connection) {
+        if (!$connection) {
             return;
         }
-
         $selected = mysql_select_db($database, $connection);
-        if (! $selected) {
+        if (!$selected) {
             return;
         }
-
         $username = 'sample';
         $password = 'password';
-
-        $query = mysql_query("CALL $database.z2t_lookup('$zip','$username','$password')");
-        if (! $query) {
+        $query = mysql_query("CALL {$database}.z2t_lookup('{$zip}','{$username}','{$password}')");
+        if (!$query) {
             return;
         }
-
         while ($row = mysql_fetch_array($query, MYSQL_ASSOC)) {
             echo "Zip Code: " . $row['Zip_Code'] . "<br>";
             echo "Sales Tax Rate: " . $row['Sales_Tax_Rate'] . "<br>";
@@ -38,7 +42,6 @@
             echo "State: " . $row['State'] . "<br>";
             echo "Shipping Taxable: " . $row['Shipping_Taxable'] . "<br>";
         }
-
         mysql_free_result($query);
         mysql_close($connection);
     }

modified:	_config/config.yml
@@ -3,24 +3,20 @@
 Before: 'app/*'
 After: 'framework/*','cms/*','ecommerce/*'
 ---
-
 OrderModifier_Descriptor:
   extensions:
-    - GSTTaxDecorator
-
-Product:
+    - Sunnysideup\EcommerceTax\Decorator\GSTTaxDecorator
+Sunnysideup\Ecommerce\Pages\Product:
   extensions:
-    - GSTTaxDecorator
-
-StoreAdmin:
+    - Sunnysideup\EcommerceTax\Decorator\GSTTaxDecorator
+Sunnysideup\Ecommerce\Cms\StoreAdmin:
   managed_models:
-    - GSTTaxModifierOptions
-
+    - Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions
 ---
 Only:
   classexists: 'ProductVariation'
 ---
 ProductVariation:
   extensions:
-    - GSTTaxDecorator
+    - Sunnysideup\EcommerceTax\Decorator\GSTTaxDecorator


modified:	src/Modifiers/GSTTaxModifier.php
@@ -2,15 +2,26 @@

 namespace Sunnysideup\EcommerceTax\Modifiers;

-use OrderModifier;
-use DropdownField;
-use EcommerceCountry;
-use ReadonlyField;
-use Config;
-use EcommerceConfig;
-use GSTTaxModifierOptions;
+
+
+
+
+
+
+
 use ProductVariation;
-use DataObject;
+
+use Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions;
+use Sunnysideup\Ecommerce\Model\Address\EcommerceCountry;
+use SilverStripe\Forms\DropdownField;
+use SilverStripe\Forms\ReadonlyField;
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\EcommerceTax\Modifiers\GSTTaxModifier;
+use Sunnysideup\Ecommerce\Config\EcommerceConfig;
+use Sunnysideup\EcommerceTax\Decorator\GSTTaxDecorator;
+use SilverStripe\ORM\DataObject;
+use Sunnysideup\Ecommerce\Model\OrderModifier;
+


 /**
@@ -84,7 +95,7 @@


     private static $many_many = array(
-        "GSTTaxModifierOptions" => "GSTTaxModifierOptions"
+        "GSTTaxModifierOptions" => GSTTaxModifierOptions::class
     );

     /**
@@ -160,9 +171,9 @@
     private static $default_country_code = "";
     protected static function get_default_country_code_combined()
     {
-        $country = Config::inst()->get("GSTTaxModifier", "default_country_code");
+        $country = Config::inst()->get(GSTTaxModifier::class, "default_country_code");
         if (!$country) {
-            $country = EcommerceConfig::get('EcommerceCountry', 'default_country_code');
+            $country = EcommerceConfig::get(EcommerceCountry::class, 'default_country_code');
         }
         return $country;
     }
@@ -463,7 +474,7 @@
                     $buyable = $item->Buyable();
                     if ($buyable) {
                         $this->dealWithProductVariationException($buyable);
-                        if ($buyable->hasExtension("GSTTaxDecorator")) {
+                        if ($buyable->hasExtension(GSTTaxDecorator::class)) {
                             $excludedTaxes = $buyable->BuyableCalculatedExcludedFrom();
                             $additionalTaxes = $buyable->BuyableCalculatedAdditionalTax();
                             if ($excludedTaxes) {
@@ -515,9 +526,9 @@
     public function dealWithProductVariationException($buyable)
     {
         if ($buyable instanceof ProductVariation) {
-            if (!$buyable->hasExtension("GSTTaxDecorator")) {
+            if (!$buyable->hasExtension(GSTTaxDecorator::class)) {
                 if ($parent = $buyable->Parent()) {
-                    if ($parent->hasExtension("GSTTaxDecorator")) {
+                    if ($parent->hasExtension(GSTTaxDecorator::class)) {
                         $buyable = $parent;
                     }
                 }
@@ -551,7 +562,7 @@
                                 array("ModifierClassName" => $modifier->ClassName)
                             );
                             if ($modifierDescriptor) {
-                                if ($modifierDescriptor->hasExtension("GSTTaxDecorator")) {
+                                if ($modifierDescriptor->hasExtension(GSTTaxDecorator::class)) {
                                     $excludedTaxes = $modifierDescriptor->ExcludedFrom();
                                     $additionalTaxes = $modifierDescriptor->AdditionalTax();
                                     if ($excludedTaxes) {
@@ -605,7 +616,7 @@
     protected function hasExceptionTaxes()
     {
         return DataObject::get_one(
-            'GSTTaxModifierOptions',
+            GSTTaxModifierOptions::class,
             array("DoesNotApplyToAllProducts" => 1)
         ) ? false : true;
     }
@@ -774,7 +785,7 @@
         if ($this->isExclusive()) {
             return $this->LiveRawTableValue();
         } else {
-            if (Config::inst()->get('GSTTaxModifier', 'alternative_country_prices_already_include_their_own_tax')) {
+            if (Config::inst()->get(GSTTaxModifier::class, 'alternative_country_prices_already_include_their_own_tax')) {
                 return 0;
             } else {
                 $currentCountry = $this->LiveCountry();

modified:	src/Decorator/GSTTaxDecorator.php
@@ -2,13 +2,20 @@

 namespace Sunnysideup\EcommerceTax\Decorator;

-use DataExtension;
+
 use ProductVariation;
-use FieldList;
-use LiteralField;
-use GSTTaxModifierOptions;
-use CheckboxSetField;
-use ReadonlyField;
+
+
+
+
+
+use Sunnysideup\EcommerceTax\Model\GSTTaxModifierOptions;
+use SilverStripe\Forms\FieldList;
+use SilverStripe\Forms\LiteralField;
+use SilverStripe\Forms\CheckboxSetField;
+use SilverStripe\Forms\ReadonlyField;
+use SilverStripe\ORM\DataExtension;
+



@@ -53,8 +60,8 @@
     private static $table_name = 'GSTTaxDecorator';

     private static $many_many = array(
-        "ExcludedFrom" => "GSTTaxModifierOptions",
-        "AdditionalTax" => "GSTTaxModifierOptions"
+        "ExcludedFrom" => GSTTaxModifierOptions::class,
+        "AdditionalTax" => GSTTaxModifierOptions::class
     );

     /**

modified:	src/Model/GSTTaxModifierOptions.php
@@ -2,17 +2,30 @@

 namespace Sunnysideup\EcommerceTax\Model;

-use DataObject;
-use Permission;
-use Config;
-use DropdownField;
-use EcommerceCountry;
-use ReadonlyField;
-use Security;
-use Controller;
-use DatabaseAdmin;
-use EcommerceDBConfig;
-use DBField;
+
+
+
+
+
+
+
+
+
+
+
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\Ecommerce\Model\Extensions\EcommerceRole;
+use SilverStripe\Security\Permission;
+use Sunnysideup\Ecommerce\Model\Address\EcommerceCountry;
+use SilverStripe\Forms\DropdownField;
+use SilverStripe\Forms\ReadonlyField;
+use SilverStripe\Security\Security;
+use SilverStripe\Control\Controller;
+use SilverStripe\ORM\DatabaseAdmin;
+use Sunnysideup\Ecommerce\Model\Config\EcommerceDBConfig;
+use SilverStripe\ORM\FieldType\DBField;
+use SilverStripe\ORM\DataObject;
+


 /**
@@ -157,7 +170,7 @@
         if ($extended !== null) {
             return $extended;
         }
-        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
+        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
             return true;
         }
         return parent::canCreate($member);
@@ -174,7 +187,7 @@
         if ($extended !== null) {
             return $extended;
         }
-        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
+        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
             return true;
         }
         return parent::canCreate($member);
@@ -191,7 +204,7 @@
         if ($extended !== null) {
             return $extended;
         }
-        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
+        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
             return true;
         }
         return parent::canEdit($member);
@@ -208,7 +221,7 @@
         if ($extended !== null) {
             return $extended;
         }
-        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
+        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
             return true;
         }
         return parent::canDelete($member);

Writing changes for 6 files
✔✔✔