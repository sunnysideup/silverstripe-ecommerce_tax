<?php

/**
 * @see http://www.zip2tax.com/developers/z2t_developers_example.asp?language=PHP&file=php&db=mysql
 */
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;
/**
 * @see http://www.zip2tax.com/developers/z2t_developers_example.asp?language=PHP&file=php&db=mysql
 */
class Zip2Tax
{
    use Extensible;
    use Injectable;
    use Configurable;
    public static function get_taxes($zip)
    {
        $server = 'db.Zip2Tax.com';
        $username = 'z2t_link';
        $password = 'H^2p6~r';
        $database = 'zip2tax';
        $connection = mysql_connect($server, $username, $password, 0, 65536);
        if (!$connection) {
            return;
        }
        $selected = mysql_select_db($database, $connection);
        if (!$selected) {
            return;
        }
        $username = 'sample';
        $password = 'password';
        $query = mysql_query("CALL {$database}.z2t_lookup('{$zip}','{$username}','{$password}')");
        if (!$query) {
            return;
        }
        while ($row = mysql_fetch_array($query, MYSQL_ASSOC)) {
            echo "Zip Code: " . $row['Zip_Code'] . "<br>";
            echo "Sales Tax Rate: " . $row['Sales_Tax_Rate'] . "<br>";
            echo "Post Office City: " . $row['Post_Office_City'] . "<br>";
            echo "County: " . $row['County'] . "<br>";
            echo "State: " . $row['State'] . "<br>";
            echo "Shipping Taxable: " . $row['Shipping_Taxable'] . "<br>";
        }
        mysql_free_result($query);
        mysql_close($connection);
    }
}
