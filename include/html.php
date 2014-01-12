<?php

$_indent_level = 0;

function do_indent($str, $v = 0)
{
    global $_indent_level;

    if($v < 0)
        $_indent_level += $v;

    if($_indent_level > 0)
        $str =  str_repeat("  ", $_indent_level) . $str;

    if($v > 0)
        $_indent_level += $v;

    return $str . "\n";
}

function do_html_tr($t, $arr, $class, $extra)
{
    if(strlen($class))
        $class = " class=\"$class\"";

    /* $extra contains parameters to <tr>, such as valign="top" */
    if(strlen($extra))
        $extra = " $extra";

    $str = do_indent("<tr$class$extra>", 1);
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
            $str .= do_indent($val);
        }
        else
        {
            $str .= do_indent("<$t$class$extra> ".trim($val)." </$t>", 0);
        }
    }
    $str .= do_indent("</tr>", -1);

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
    return do_indent("<table $extra>", 1);
}

function html_table_end()
{
    return do_indent("</table>", -1);
}


// HTML HTML
function html_begin()
{
    return do_indent("<html>", 1);
}

function html_end()
{
    return do_indent("</html>", -1);
}


// HTML HEAD
function html_head($title, $stylesheet = 0)
{
    $str  = do_indent("<head>", 1);
    $str .= do_indent("<title> $title </title>", 0);
    if($stylesheet)
        $str .= do_indent("<link rel=\"stylesheet\" ".
                  "href=\"$stylesheet\" type=\"text/css\">", 0);
    $str .= do_indent("</head>", -1);

    return $str;
}


// HTML BODY
function html_body_begin()
{
    return do_indent("<body>", 1);
}

function html_body_end()
{
    return do_indent("</body>", -1);
}

// HTML A HREF
function html_ahref($label, $url, $extra = "")
{
    $label = stripslashes($label);
    if (!$label and $url)
    {
        return do_indent(" <a href=\"$url\" $extra>$url</a> ");
    }
    else if (!$label)
    {
        return do_indent(" &nbsp; ");
    }
    else
    {
        return do_indent(" <a href=\"$url\" $extra>$label</a> ");
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

    if ($width) { $width = 'width="'.$width.'"'; }

$str = '<table '.$width.' border="0" class="mainTable" cellpadding="0" cellspacing="0" align="center">'."\n";

if ($title)
{
$str .= '
<tr><td colspan=3><table width="100%" border=0 cellpadding=0 cellspacing=0>
<tr><td>
    <table width="100%" border="0" cellpadding="0" cellspacing="0" class="topMenu">
      <tr>
        <td width="100%" rowspan="3" align="center"><span class="menuTitle">'.$title.'</span></td>
      </tr>
    </table>
</td></tr>
</table></td></tr>
';
}

$str .= '
<tr>
<td><img src="'.BASE.'images/blank.gif" width="5" height="1" alt=""></td>
<td width="100%"><table width="100%" border=0 cellpadding=0 cellspacing=0>
    <tr><td class=topMenu>
        <table width="100%" border=0 cellpadding="'.$innerPad.'" cellspacing="1" '.$extra.'><tr><td class=white>
';

    return $str;
}

function html_frame_end($text = "")
{
    
$str = '
        </td></tr></table></td></tr>
    </table>
</td>
<td><img src="'.BASE.'images/blank.gif" width="5" height="1" alt=""></td>
</tr>
</table>
<br>
';
    
    return $str;
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
    return '<p>&nbsp;&nbsp; <a href="'.htmlentities($url).'">&lt;&lt; Back</a></p>'."\n";
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
