<?php
/**
 * Basic HTML functions
 *  FIXME -- get rid of the frame and table functions
 */

function do_html_tr($t, $arr, $class, $extra)
{
    if(strlen($class))
        $class = " class=\"$class\"";

    /* $extra contains parameters to <tr>, such as valign="top" */
    if(strlen($extra))
        $extra = " $extra";

    $str = "<tr$class$extra>";
    for($i = 0; $i < sizeof($arr); $i++)
    {
        /* If it is not an array, it contains the entire table cell.  If it
           is an array, [0] holds the main content and [1] the options like
           valign="top" */
        if(is_array($arr[$i]))
        {
            $val = $arr[$i][0];
            $extra = " ".$arr[$i][1];
        }
        else
        {
            $val = $arr[$i];
            $extra = "";
        }

        if (! $val)
        {
            $val = "&nbsp;";
        }

        if(stristr($val, "<$t"))
        {
            $str .= $val;
        }
        else
        {
            $str .= "<$t$class$extra> ".trim($val)." </$t>";
        }
    }
    $str .= "</tr>";

    return $str;
}

// HTML TR
function html_tr($arr, $class = "", $extra = "")
{
    return do_html_tr("td", $arr, $class, $extra);
}

// HTML TABLE
function html_table_begin($extra = "")
{
    return "<table $extra>";
}

function html_table_end()
{
    return "</table>";
}

// HTML A HREF
function html_ahref($label, $url, $extra = "")
{
    $label = stripslashes($label);
    if (!$label and $url)
    {
        return " <a href=\"$url\" $extra>$url</a> ";
    }
    else if (!$label)
    {
        return " &nbsp; ";
    }
    else
    {
        return " <a href=\"$url\" $extra>$label</a> ";
    }
}

function html_imagebutton($text, $url, $extra = "")
{
    static $i = 1;

    $i++;
    $img1 = apidb_url("util/button.php?text=".urlencode($text)."&amp;pressed=0");
    $img2 = apidb_url("util/button.php?text=".urlencode($text)."&amp;pressed=1");

    $java  = "onMouseDown = 'document.img$i.src = \"$img2\"; return true;' ";
    $java .= "onMouseUp = 'document.img$i.src = \"$img1\"; return true;' ";

    return "\n<a href=\"$url\" $extra $java>\n <img src=\"$img1\" name=\"img$i\" alt=\"$text\"> </a>\n";
}


function html_frame_start($title = "", $width = "", $extra = "", $innerPad = 0)
{
    $style = "";
    if ($width or $innerPad)
    {
        $style .= 'style="';
        if ($width)
            $style .= "width:{$width};";
        if ($innerPad)
            $style .= "padding:{$innerPad}px;";
        $style .= '"';
    }
    $str = "<div class=\"html_frame\" {$style} {$extra}>\n";
    if ($title)
    {
        $str .= "<div class=\"html_frame_title\">{$title}</div>\n";
    }
    return $str;
}

function html_frame_end($text = "")
{
    return "</div>\n";
}


function html_select($name, $values, $default = null, $descs = null)
{
    $str = "<select name='$name'>\n";
    while(list($idx, $value) = each($values))
    {
        $desc = $value;
        if($descs)
        $desc = $descs[$idx];

        if($value == $default)
        $str .= "  <option selected value='$value'>$desc\n";
        else
        $str .= "  <option value='$value'>$desc\n";
    }
    $str .= "</select>\n";

    return $str;
}

function html_back_link($howmany = 1, $url = "")
{
    if (!$url)
    {
        $url = 'javascript:history.back('.$howmany.');';
    }
    return '<p><a href="'.htmlentities($url).'" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a></p>'."\n";
}


function p()
{
    return "\n<p>&nbsp;</p>\n";
}

function add_br($text = "")
{
    $text = str_replace("\n","<br>\n",$text);
    return $text;
}

function make_dll_option_list($varname, $dllid = -1)
{
    $db = new ApiDB();

    echo "<select name='$varname'>\n";
    //echo "<option value='ALL'>ALL\n";
    $list = $db->get_dll_names();
    while(list($name, $id) = each($list))
    {
        if($dllid == $id)
        echo "<option value=$id selected>$name  ($id)\n";
        else
        echo "<option value=$id>$name  ($id)\n";
    }
    echo "</select>\n";
}


function make_inx_option_list($varname, $inx = null)
{
    $list = array("yes", "no", "stub", "unknown");
    echo "<select name='$varname'>\n";
    while(list($idx, $value) = each($list))
        {
            if($value == $inx)
                echo "<option value=$value selected>$value\n";
            else
                echo "<option value=$value>$value\n";
        }
    echo "</select>\n";

}

/* Displays a note box */
function html_note($shTitle, $shBody)
{
    $shRet = '<div class="note_container">';
    $shRet .= '<div class="note_title">';
    $shRet .= $shTitle;
    $shRet .= '</div>';
    $shRet .= '<div class="note_body">';
    $shRet .= $shBody;
    $shRet .= '</div></div>';

    return $shRet;
}

function html_radiobuttons($aIds, $aOptions, $sName, $sDefault = '', $bLineBreaks = true)
{
    $shRet = '';

    for($i = 0; $i < sizeof($aIds); $i++)
    {
        if($aIds[$i] == $sDefault)
            $shChecked = ' checked="checked"';
        else
            $shChecked = '';

        $shRet .= '<input type="radio" name="'.$sName.'" value="'.$aIds[$i]."\"$shChecked> " . $aOptions[$i];
        
        if($bLineBreaks)
            $shRet .= '<br />';
    }

    return $shRet;
}

function html_checkbox($sName, $sValue, $shText, $bChecked)
{
    if($bChecked)
        $sSelected = ' checked="checked"';
    else
        $sSelected = '';

    return "<input type=\"checkbox\" name=\"$sName\" value=\"$sValue\"$sSelected /> $shText\n";
}

function html_checkboxes($sName, $aValues, $aTexts, $aSelected)
{
    $shRet = '';

    for($i = 0; $i < sizeof($aValues); $i++)
        $shRet .= html_checkbox($sName.$i, $aValues[$i], $aTexts[$i], $aSelected[$i]).'<br />';

    return $shRet;
}

function html_read_input_series($sName, $aInput, $iCount)
{
    $aRet = array();

    for($i = 0; $i < $iCount; $i++)
    {
        $aRet[] = getInput($sName.$i, $aInput);
    }

    return $aRet;
}

?>
