<?php
/**
 * htmlmenu
 *    simple menu rendering
 */
class htmlmenu
{
    // constructor
    public function __construct($name, $form = null)
    {
        if (!empty($form))
            echo "<form action=\"$form\" method=\"post\">\n";
        echo '<li class="top"><p>'.$name.'</p></li>';
    }

    // add a table row
    public function add($sName, $shUrl = null, $sAlign = "left")
    {
        $oTableRow = new TableRow();

        if($shUrl)
        {
            echo "<li><p><a href=\"{$shUrl}\">{$sName}</a></p></li>\n";
        }
        else
        {
            echo "<li><p>{$sName}</a></li>\n";
        }
    }

    // add misc row
    public function addmisc($sStuff, $sAlign = "left")
    {
        echo "<li><p style=\"text-align: $sAlign\">$sStuff</p></li>\n";
    }

    // menu complete
    public function done($form = null)
    {
        echo '<li class="bot"></li>';

        if (!empty($form))
            echo "</form>\n";
    }
}
// done
?>
