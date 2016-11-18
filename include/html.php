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
    return ' <a href="'.htmlentities($url).'" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a> '."\n";
}

/* Displays a note box using bootstrap panel */
/*  mode: default, primary, success, info, warning, danger */
function html_note($heading = '', $body = '', $footer = '', $mode = 'default')
{
    return "<div class=\"panel panel-{$mode}\">\n".
           ($heading ? "<div class=\"panel-heading\">{$heading}</div>\n" : '').
           ($body ? "<div class=\"panel-body\">{$body}</div>\n" : '').
           ($footer ? "<div class=\"panel-heading\">{$footer}</div>\n" : '').
           "</div>\n";
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

?>
